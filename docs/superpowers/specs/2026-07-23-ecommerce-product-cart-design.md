# E-commerce Plugin — Phase 1: Product + Cart

Date: 2026-07-23
Plugin: `plugins/e-commerce`

## Purpose

New plugin providing the foundation for an online store: products, a
customer account system, and a cart that works for both guests and logged-in
customers. Checkout/order management is explicitly out of scope for this
phase — it lands once this foundation is stable.

## Plugin scaffolding

Mirrors `plugins/cms`/`plugins/core`:
- `composer.json` — name `lumina/ecommerce`, PSR-4 `Lumina\Ecommerce\` → `src/`,
  path repository entry already covered by the root `plugins/*` repository
- `src/Providers/EcommerceServiceProvider.php` — registers the `customer`
  auth guard/provider (mirrors how `CmsServiceProvider` registers `admin`),
  loads migrations/routes
- `database/migrations/`, `routes/ecommerce.php`

## Models

### `Product`
- `use QueryBuilder` — immediately gets list/filter/search via the existing
  generic `/api/items/products` endpoint (`Lumina\Core\Controllers\ItemController`)
- Fields: `name` (multilingual via `$table->multilingual('name')`), `slug`
  (unique), `price` (integer, smallest currency unit — no float money), `stock`
  (integer), `status` (enum: `active`, `draft`)
- `protected array $translatable = ['name'];`

### `Customer`
- Separate table `customers`, own guard `customer` (session-based), mirrors
  `Admin`/`admin` guard setup: `EcommerceServiceProvider::register()` sets
  `auth.guards.customer`, `auth.providers.customers`,
  `auth.passwords.customers`
- Fields: `name`, `email` (unique), `password`, `email_verified_at`
- No SoftDeletes for this phase (not requested)

### `Cart`
- `customer_id` (nullable, FK to customers, null while guest)
- `session_token` (string, unique, nullable — set only while the cart has no
  customer_id)
- `hasMany(CartItem::class)`

### `CartItem`
- `cart_id`, `product_id`, `quantity` (int, min 1), `unit_price` (int,
  snapshot of `Product::price` at the time the item was added — so price
  changes on the product don't retroactively change an existing cart)

## Cart resolution (`ResolvesCart` trait, used by `CartController`)

```php
protected function currentCart(Request $request): Cart
{
    if ($customer = auth('customer')->user()) {
        return Cart::firstOrCreate(['customer_id' => $customer->id]);
    }

    $token = $request->cookie('cart_token') ?? Str::random(40);
    $cart = Cart::firstOrCreate(['session_token' => $token, 'customer_id' => null]);

    Cookie::queue('cart_token', $token, 60 * 24 * 30); // 30 days

    return $cart;
}
```

`firstOrCreate` on `customer_id` assumes one open cart per customer (no cart
history/abandonment tracking this phase — that's an order-phase concern).

## Guest → customer cart merge

On customer login (`Illuminate\Auth\Events\Login` for the `customer` guard,
listened to in `EcommerceServiceProvider::boot()`, mirroring how
`CmsServiceProvider` listens for admin `Login`/`Logout`):

1. Read the `cart_token` cookie, if present.
2. Find the guest cart by that token (if any).
3. Find-or-create the customer's cart.
4. For each guest cart item: if the customer's cart already has that
   `product_id`, add the quantities together; otherwise move the item over.
5. Delete the now-empty guest cart and forget the `cart_token` cookie.

If there's no `cart_token` cookie, or no matching guest cart, this is a
no-op.

## API (`/api/cart`, JSON, works for both guests and authenticated customers)

```
GET    /api/cart                  — current cart with items + line totals
POST   /api/cart/items            — body: { product_id, quantity } — add or increment
PUT    /api/cart/items/{item}     — body: { quantity } — set exact quantity (0 removes it)
DELETE /api/cart/items/{item}     — remove the item
```

`CartController` is hand-written (not `ItemController`-based), since cart
mutation is business logic (quantity merging, stock checks, price
snapshotting), not generic CRUD.

Response shape for `GET /api/cart` and after each mutation:
```json
{
  "data": {
    "id": 1,
    "items": [
      {"id": 5, "product_id": 2, "quantity": 3, "unit_price": 10000, "line_total": 30000, "product": {"id": 2, "name": "..."}}
    ],
    "total": 30000
  }
}
```

## Validation / edge cases

- `POST /api/cart/items`: 422 if `product_id` doesn't exist, is not
  `status = active`, or `quantity` exceeds `stock`
- `PUT /api/cart/items/{item}`: 404 if the item doesn't belong to the
  resolved cart (a customer/guest can't edit someone else's cart item by
  guessing an ID); `quantity: 0` deletes the item instead of erroring
- Adding a product already in the cart increments quantity rather than
  creating a duplicate `CartItem` row

## Testing

Feature tests covering: guest can add/update/remove items without being
authenticated; a `cart_token` cookie is set for guests; a logged-in customer
gets a DB-backed cart keyed by `customer_id`; login merges a guest cart into
the customer's cart (including quantity-adding when the same product exists
in both); stock/status validation on add; a customer can't touch another
customer's cart item by ID; `Product` is queryable through the existing
`/api/items/products` endpoint (filter/sort/search/multilingual all work
without any e-commerce-specific code, proving the reuse).

## Out of scope this phase

Checkout, Order/OrderItem models, payment integration, order management
UI/API, stock decrement on purchase, cart expiry/cleanup jobs, product
categories/variants/images.
