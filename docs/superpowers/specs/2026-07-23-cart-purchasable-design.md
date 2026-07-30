# Cart & Order: morphMap + metadata/shipping/currency

## Context

`CartItem`/`OrderItem` use a polymorphic `cartable`/`orderable` relation (`morphs('cartable')`) that currently stores the fully-qualified class name (e.g. `Lumina\Ecommerce\Models\Product`) in the `*_type` column. The plugin only has one purchasable today (`Product`), resolved dynamically via `ResourceResolver` from an API `resource` string (`plugins/core/src/Support/ResourceResolver.php`).

Separately, carts need to support: per-item variant selection (metadata), a manually-set shipping fee, and a selected currency label.

## 1. morphMap for polymorphic types

Register a morph map in `EcommerceServiceProvider::boot()`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;
use Lumina\Ecommerce\Models\Product;

Relation::morphMap([
    'product' => Product::class,
]);
Relation::enforceMorphMap(Relation::morphMap());
```

- `cartable_type` / `orderable_type` will store `'product'` instead of the FQCN.
- No changes needed to `CartItem::cartable()`, `OrderItem::orderable()`, or `CartController` — Eloquent translates the alias to/from the class transparently once the map is registered.
- `enforceMorphMap` rejects writes using an unmapped/raw class name, catching future purchasables that forget to register.
- New purchasable models (combo, menu, service, …) get added to this same array when introduced. No dynamic/extensible registration mechanism is being built now (YAGNI) — a plugin author adds a line to this array.

**Data migration note:** no existing production cart/order data uses the FQCN format yet (confirmed with the user — this is pre-launch), so no backfill migration is needed. If that assumption changes, a data migration converting `cartable_type='Lumina\Ecommerce\Models\Product'` → `'product'` (and same for `orderable_type`) would be required before enabling `enforceMorphMap`.

## 2. `cart_items.metadata`

- New column: `cart_items.metadata` — `json`, nullable.
- `CartItem::casts()` adds `'metadata' => 'array'`.
- `CartItem::$fillable` adds `'metadata'`.
- `CartController::addItem`:
  - Accepts an optional `metadata` field in the request: `'metadata' => ['nullable', 'array']`.
  - The existing item-lookup query (used to decide whether to increment an existing row's quantity vs. insert a new one) must also match on `metadata`, so that two adds of the same product with different variant selections (e.g. different size) create separate rows instead of merging quantities. Compare as JSON: `->where('metadata', json_encode($metadata))` (Eloquent will need the metadata normalized/sorted consistently, or compared via a raw JSON equality check depending on DB driver — implementation detail for the plan).
- No validation of metadata's internal shape (product-specific, out of scope).

## 3. `carts.shipping_fee`

- New column: `carts.shipping_fee` — `unsignedInteger`, default `0`. Same unit convention as `unit_price` (smallest currency unit, e.g. cents/đồng).
- `Cart::casts()` adds `'shipping_fee' => 'integer'`.
- New route + controller method: `PATCH /cart/{type}/shipping` → `CartController::updateShipping(Request $request, string $type)`.
  - Validates `['shipping_fee' => ['required', 'integer', 'min:0']]`.
  - Loads the cart via `resolveCart()`, updates `shipping_fee`, returns `present($cart->fresh('items.cartable'))`.
- `CartController::present()` adds `'shipping_fee' => $cart->shipping_fee` to the response, and `total` becomes `$items->sum('line_total') + $cart->shipping_fee`.

## 4. `carts.currency`

- New column: `carts.currency` — `string`, not nullable.
- New config key: `config('ecommerce.default_currency')` (new `plugins/e-commerce/config/ecommerce.php`, default e.g. `'VND'`).
- `CartController::resolveCart()`: when `firstOrCreate`-ing a cart, set `currency` from the config default on creation (only applies to the `create` branch — use `firstOrCreate(['session_token' => $token, 'type' => $type], ['currency' => config('ecommerce.default_currency')])`).
- New route + controller method: `PATCH /cart/{type}/currency` → `CartController::updateCurrency(Request $request, string $type)`.
  - Validates `['currency' => ['required', 'string', 'size:3']]`.
  - Loads the cart via `resolveCart()`, updates `currency`, returns `present(...)`.
  - Can be called any time, including after items exist. No conversion of existing item `unit_price` values happens — `currency` is a stored label only, not a conversion trigger.
- `present()` adds `'currency' => $cart->currency`.

## Migration

Single new migration:

```php
Schema::table('cart_items', function (Blueprint $table) {
    $table->json('metadata')->nullable();
});

Schema::table('carts', function (Blueprint $table) {
    $table->unsignedInteger('shipping_fee')->default(0);
    $table->string('currency');
});

Schema::table('orders', function (Blueprint $table) {
    $table->json('metadata')->nullable();
    $table->unsignedInteger('shipping_fee')->default(0);
    $table->string('currency');
    $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'guest_address', 'total']);
});
```

(`currency` has no DB default — always set explicitly in `resolveCart()` at creation time so it reflects `config('ecommerce.default_currency')` at the time of creation, not a hardcoded schema default.)

## 5. Order: computed total, shipping_fee, currency, metadata (replacing guest_* columns)

Mirrors the Cart changes onto `Order`, which currently stores `total` as a plain fillable/cast integer and spreads guest contact info across four columns (`guest_name`, `guest_email`, `guest_phone`, `guest_address`).

- **Remove** `guest_name`, `guest_email`, `guest_phone`, `guest_address` from the `orders` table and `Order::$fillable`. (No checkout/order-creation controller exists yet in this codebase, so there's no call site to update for this — future checkout work builds directly against the new shape.)
- **Add** `orders.metadata` — `json`, nullable. Holds guest contact/shipping info (`name`, `email`, `phone`, `address`) plus anything else checkout needs to capture (no fixed shape enforced, same as `cart_items.metadata`).
- **Add** `orders.shipping_fee` — `unsignedInteger`, default `0`, copied from the source cart's `shipping_fee` at checkout time.
- **Add** `orders.currency` — `string`, copied from the source cart's `currency` at checkout time.
- **`total` becomes computed, not stored:**
  - Remove `'total'` from `Order::$fillable` and from `casts()`.
  - Drop the `orders.total` column.
  - Add `Order::total(): int` method: `return $this->items->sum('line_total') + $this->shipping_fee;` (mirrors `Cart`'s total calc in `CartController::present()`).
  - Anywhere `total` was being set explicitly on order creation, stop passing it — it's derived from items + shipping_fee going forward.

## Out of scope

- Multi-currency conversion / exchange rates.
- Shipping method/zone-based fee calculation.
- Validation of `metadata`'s internal shape per product type.
- Extensible/dynamic morphMap registration across plugins.
