# Cart & Order: morphMap + metadata/shipping/currency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Register a morphMap for cart/order polymorphic relations, add variant `metadata` to cart items, add `shipping_fee`/`currency` to carts with dedicated update endpoints, and mirror `shipping_fee`/`currency`/`metadata` onto `Order` while replacing its `guest_*` columns and making `total` a computed value.

**Architecture:** All schema changes are made by editing the existing (not-yet-shipped) `create_carts_table` and `create_orders_table` migrations in place — there is no production data for these tables yet, per the spec. `CartController` gains two new endpoints (`updateShipping`, `updateCurrency`) and its `addItem`/`present` logic is extended. A new `routes/cart.php` file is added (no cart routes currently exist anywhere in the app) and registered from `EcommerceServiceProvider::boot()`. `Order::total()` becomes a computed method instead of a stored column.

**Tech Stack:** Laravel 11, PHPUnit Feature tests, SQLite in-memory test DB.

---

### Task 1: Ecommerce config file (`default_currency`)

**Files:**
- Create: `plugins/e-commerce/configs/ecommerce.php`
- Modify: `plugins/e-commerce/src/Providers/EcommerceServiceProvider.php`

- [ ] **Step 1: Create the config file**

```php
<?php

return [
    'default_currency' => 'VND',
];
```

- [ ] **Step 2: Merge it in the provider**

In `plugins/e-commerce/src/Providers/EcommerceServiceProvider.php`, add to `register()`:

```php
public function register(): void
{
    config([
        'core.model_namespaces' => array_merge(config('core.model_namespaces', []), ['Lumina\\Ecommerce\\Models']),
    ]);

    $this->mergeConfigFrom(__DIR__.'/../../configs/ecommerce.php', 'ecommerce');
}
```

- [ ] **Step 3: Commit**

```bash
git add plugins/e-commerce/configs/ecommerce.php plugins/e-commerce/src/Providers/EcommerceServiceProvider.php
git commit -m "feat(ecommerce): add ecommerce config with default_currency"
```

---

### Task 2: Register morphMap for cart/order purchasables

**Files:**
- Modify: `plugins/e-commerce/src/Providers/EcommerceServiceProvider.php`
- Test: `plugins/e-commerce/tests/Feature/CartMorphMapTest.php`

- [ ] **Step 1: Write the failing test**

Create `plugins/e-commerce/tests/Feature/CartMorphMapTest.php`:

```php
<?php

namespace Lumina\Ecommerce\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lumina\Ecommerce\Models\Product;
use Tests\TestCase;

class CartMorphMapTest extends TestCase
{
    public function test_product_is_registered_under_the_product_morph_alias(): void
    {
        $this->assertSame(Product::class, Relation::getMorphedModel('product'));
    }

    public function test_product_resolves_to_its_short_alias_not_the_fqcn(): void
    {
        $this->assertSame('product', Relation::getMorphAlias(Product::class));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CartMorphMapTest`
Expected: FAIL — `Relation::getMorphedModel('product')` returns `null` because no morph map is registered yet.

- [ ] **Step 3: Register the morph map**

In `plugins/e-commerce/src/Providers/EcommerceServiceProvider.php`, add to `boot()`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;
use Lumina\Ecommerce\Models\Product;

public function boot(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    Relation::morphMap([
        'product' => Product::class,
    ]);
    Relation::enforceMorphMap(Relation::morphMap());
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CartMorphMapTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add plugins/e-commerce/src/Providers/EcommerceServiceProvider.php plugins/e-commerce/tests/Feature/CartMorphMapTest.php
git commit -m "feat(ecommerce): register morphMap for cart/order purchasables"
```

---

### Task 3: Cart routes (needed before any controller endpoint is reachable/testable)

No cart routes exist anywhere in the app today — `CartController` has no registered routes. This task wires up the existing endpoints (`show`, `addItem`, `updateItem`, `removeItem`) so Task 4+ can add and test new ones alongside them.

**Files:**
- Create: `plugins/e-commerce/routes/cart.php`
- Modify: `plugins/e-commerce/src/Providers/EcommerceServiceProvider.php`
- Test: `plugins/e-commerce/tests/Feature/CartControllerTest.php`

- [ ] **Step 1: Write the failing test**

Create `plugins/e-commerce/tests/Feature/CartControllerTest.php`:

```php
<?php

namespace Lumina\Ecommerce\Tests\Feature;

use Lumina\Ecommerce\Models\Product;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    public function test_show_returns_an_empty_cart(): void
    {
        $response = $this->getJson('/cart/cart');

        $response->assertOk();
        $response->assertJsonPath('data.type', 'cart');
        $response->assertJsonPath('data.items', []);
    }

    public function test_add_item_adds_a_product_to_the_cart(): void
    {
        $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);

        $response = $this->postJson('/cart/cart/items', [
            'resource' => 'products',
            'id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.total', 100000);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CartControllerTest`
Expected: FAIL — 404, no route matches `/cart/cart`.

- [ ] **Step 3: Create the routes file**

Create `plugins/e-commerce/routes/cart.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Ecommerce\Controllers\CartController;

Route::get('/cart/{type?}', [CartController::class, 'show']);
Route::post('/cart/{type}/items', [CartController::class, 'addItem']);
Route::patch('/cart/{type}/items/{itemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/{type}/items/{itemId}', [CartController::class, 'removeItem']);
```

The `{type?}` optional segment matches `CartController::show(Request $request, string $type = 'cart')`'s existing default-parameter signature — a plain `{type}` would require the segment to always be present in the URL.

- [ ] **Step 4: Register the routes file**

In `plugins/e-commerce/src/Providers/EcommerceServiceProvider.php`, add to `boot()`:

```php
public function boot(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $this->loadRoutesFrom(__DIR__.'/../../routes/cart.php');

    Relation::morphMap([
        'product' => Product::class,
    ]);
    Relation::enforceMorphMap(Relation::morphMap());
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=CartControllerTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add plugins/e-commerce/routes/cart.php plugins/e-commerce/src/Providers/EcommerceServiceProvider.php plugins/e-commerce/tests/Feature/CartControllerTest.php
git commit -m "feat(ecommerce): register cart routes"
```

---

### Task 4: `cart_items.metadata` (variant selections)

**Files:**
- Modify: `plugins/e-commerce/database/migrations/2026_07_24_000000_create_carts_table.php`
- Modify: `plugins/e-commerce/src/Models/CartItem.php`
- Modify: `plugins/e-commerce/src/Controllers/CartController.php`
- Test: `plugins/e-commerce/tests/Feature/CartControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `plugins/e-commerce/tests/Feature/CartControllerTest.php`:

```php
public function test_add_item_stores_metadata(): void
{
    $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);

    $response = $this->postJson('/cart/cart/items', [
        'resource' => 'products',
        'id' => $product->id,
        'quantity' => 1,
        'metadata' => ['size' => 'M', 'color' => 'red'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.items.0.quantity', 1);
    $this->assertSame(
        ['size' => 'M', 'color' => 'red'],
        \Lumina\Ecommerce\Models\CartItem::first()->metadata
    );
}

public function test_add_item_with_different_metadata_creates_a_separate_line(): void
{
    $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);

    $this->postJson('/cart/cart/items', [
        'resource' => 'products', 'id' => $product->id, 'quantity' => 1,
        'metadata' => ['size' => 'M'],
    ]);
    $response = $this->postJson('/cart/cart/items', [
        'resource' => 'products', 'id' => $product->id, 'quantity' => 1,
        'metadata' => ['size' => 'L'],
    ]);

    $response->assertCreated();
    $this->assertCount(2, \Lumina\Ecommerce\Models\CartItem::all());
}

public function test_add_item_with_matching_metadata_merges_quantity(): void
{
    $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);

    $this->postJson('/cart/cart/items', [
        'resource' => 'products', 'id' => $product->id, 'quantity' => 1,
        'metadata' => ['size' => 'M'],
    ]);
    $response = $this->postJson('/cart/cart/items', [
        'resource' => 'products', 'id' => $product->id, 'quantity' => 2,
        'metadata' => ['size' => 'M'],
    ]);

    $response->assertCreated();
    $this->assertCount(1, \Lumina\Ecommerce\Models\CartItem::all());
    $this->assertSame(3, \Lumina\Ecommerce\Models\CartItem::first()->quantity);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CartControllerTest`
Expected: FAIL — `metadata` column doesn't exist / mass-assignment error.

- [ ] **Step 3: Add the column to the migration**

In `plugins/e-commerce/database/migrations/2026_07_24_000000_create_carts_table.php`, update the `cart_items` block:

```php
Schema::create('cart_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
    $table->morphs('cartable');
    $table->unsignedInteger('quantity');
    $table->unsignedInteger('unit_price');
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Update `CartItem`**

In `plugins/e-commerce/src/Models/CartItem.php`:

```php
protected $fillable = ['cart_id', 'cartable_id', 'cartable_type', 'quantity', 'unit_price', 'metadata'];

protected function casts(): array
{
    return [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'metadata' => 'array',
    ];
}
```

- [ ] **Step 5: Update `CartController::addItem` to accept and match on metadata**

In `plugins/e-commerce/src/Controllers/CartController.php`, replace the `addItem` method body:

```php
public function addItem(Request $request, string $type = 'cart'): JsonResponse
{
    $validated = $request->validate([
        'resource' => ['required', 'string'],
        'id' => ['required', 'integer'],
        'quantity' => ['integer', 'min:1'],
        'metadata' => ['nullable', 'array'],
    ]);

    $quantity = $validated['quantity'] ?? 1;
    $metadata = $validated['metadata'] ?? null;
    $cartable = $this->resolveCartable($validated['resource'], $validated['id']);

    if (property_exists($cartable, 'stock') || isset($cartable->stock)) {
        if ($quantity > (int) $cartable->stock) {
            throw new UnprocessableEntityHttpException('Not enough stock available.');
        }
    }

    $cart = $this->resolveCart($request, $type);

    $item = $cart->items()
        ->where('cartable_type', $cartable::class)
        ->where('cartable_id', $cartable->id)
        ->where('metadata', $metadata === null ? null : json_encode($metadata))
        ->first();

    if ($item) {
        $item->update(['quantity' => $item->quantity + $quantity]);
    } else {
        $item = $cart->items()->create([
            'cartable_type' => $cartable::class,
            'cartable_id' => $cartable->id,
            'quantity' => $quantity,
            'unit_price' => (int) $cartable->price,
            'metadata' => $metadata,
        ]);
    }

    return response()->json(['data' => $this->present($cart->fresh('items.cartable'))], 201);
}
```

`->where('metadata', json_encode($metadata))` relies on the two requests sending keys in the same order to match (e.g. both `['size' => 'M']`). This is a known limitation — acceptable for now per the spec's "no fixed shape enforced" note; a future improvement could sort keys before encoding for order-independent matching, but that's out of scope here.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=CartControllerTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add plugins/e-commerce/database/migrations/2026_07_24_000000_create_carts_table.php plugins/e-commerce/src/Models/CartItem.php plugins/e-commerce/src/Controllers/CartController.php plugins/e-commerce/tests/Feature/CartControllerTest.php
git commit -m "feat(ecommerce): add cart item metadata for variant selections"
```

---

### Task 5: `carts.currency` (set on creation)

**Files:**
- Modify: `plugins/e-commerce/database/migrations/2026_07_24_000000_create_carts_table.php`
- Modify: `plugins/e-commerce/src/Models/Cart.php`
- Modify: `plugins/e-commerce/src/Controllers/CartController.php`
- Test: `plugins/e-commerce/tests/Feature/CartControllerTest.php`

- [ ] **Step 1: Write the failing test**

Add to `plugins/e-commerce/tests/Feature/CartControllerTest.php`:

```php
public function test_show_creates_a_cart_with_the_default_currency(): void
{
    config(['ecommerce.default_currency' => 'VND']);

    $response = $this->getJson('/cart/cart');

    $response->assertOk();
    $response->assertJsonPath('data.currency', 'VND');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CartControllerTest`
Expected: FAIL — `data.currency` key missing from response.

- [ ] **Step 3: Add the column to the migration**

In `plugins/e-commerce/database/migrations/2026_07_24_000000_create_carts_table.php`, update the `carts` block:

```php
Schema::create('carts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('customer_id')->nullable();
    $table->string('session_token')->nullable();
    $table->enum('type', ['cart', 'buy_now', 'wishlist'])->default('cart');
    $table->string('currency');
    $table->unsignedInteger('shipping_fee')->default(0);
    $table->timestamps();

    $table->unique(['session_token', 'type']);
});
```

- [ ] **Step 4: Update `Cart` model**

In `plugins/e-commerce/src/Models/Cart.php`:

```php
<?php

namespace Lumina\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['customer_id', 'session_token', 'type', 'currency', 'shipping_fee'];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
```

- [ ] **Step 5: Set currency on creation in `resolveCart`**

In `plugins/e-commerce/src/Controllers/CartController.php`, update `resolveCart`:

```php
protected function resolveCart(Request $request, string $type): Cart
{
    if (! in_array($type, self::TYPES, true)) {
        throw new NotFoundHttpException("Unknown cart type [{$type}].");
    }

    $token = $request->cookie('cart_token') ?? Str::random(40);

    Cookie::queue('cart_token', $token, self::COOKIE_DAYS);

    return Cart::query()->firstOrCreate(
        ['session_token' => $token, 'type' => $type],
        ['currency' => config('ecommerce.default_currency')]
    );
}
```

- [ ] **Step 6: Add `currency` to the presented cart**

In `plugins/e-commerce/src/Controllers/CartController.php`, update `present`:

```php
protected function present(Cart $cart): array
{
    $items = $cart->items->map(function (CartItem $item) {
        return [
            'id' => $item->id,
            'cartable_type' => $item->cartable_type,
            'cartable_id' => $item->cartable_id,
            'cartable' => $item->cartable,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => $item->lineTotal(),
        ];
    });

    return [
        'id' => $cart->id,
        'type' => $cart->type,
        'currency' => $cart->currency,
        'items' => $items,
        'total' => $items->sum('line_total'),
    ];
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=CartControllerTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add plugins/e-commerce/database/migrations/2026_07_24_000000_create_carts_table.php plugins/e-commerce/src/Models/Cart.php plugins/e-commerce/src/Controllers/CartController.php plugins/e-commerce/tests/Feature/CartControllerTest.php
git commit -m "feat(ecommerce): set cart currency from config on creation"
```

---

### Task 6: `PATCH /cart/{type}/currency` endpoint

**Files:**
- Modify: `plugins/e-commerce/src/Controllers/CartController.php`
- Modify: `plugins/e-commerce/routes/cart.php`
- Test: `plugins/e-commerce/tests/Feature/CartControllerTest.php`

- [ ] **Step 1: Write the failing test**

Add to `plugins/e-commerce/tests/Feature/CartControllerTest.php`:

```php
public function test_update_currency_changes_the_carts_currency(): void
{
    $this->getJson('/cart/cart'); // creates the cart with the default currency

    $response = $this->patchJson('/cart/cart/currency', ['currency' => 'USD']);

    $response->assertOk();
    $response->assertJsonPath('data.currency', 'USD');
}

public function test_update_currency_rejects_a_non_three_letter_code(): void
{
    $this->getJson('/cart/cart');

    $response = $this->patchJson('/cart/cart/currency', ['currency' => 'US']);

    $response->assertStatus(422);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CartControllerTest`
Expected: FAIL — 404, no route matches `/cart/cart/currency`.

- [ ] **Step 3: Add the route**

In `plugins/e-commerce/routes/cart.php`, add:

```php
Route::patch('/cart/{type}/currency', [CartController::class, 'updateCurrency']);
```

Full file:

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Ecommerce\Controllers\CartController;

Route::get('/cart/{type?}', [CartController::class, 'show']);
Route::post('/cart/{type}/items', [CartController::class, 'addItem']);
Route::patch('/cart/{type}/items/{itemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/{type}/items/{itemId}', [CartController::class, 'removeItem']);
Route::patch('/cart/{type}/currency', [CartController::class, 'updateCurrency']);
```

- [ ] **Step 4: Add the controller method**

In `plugins/e-commerce/src/Controllers/CartController.php`, add after `removeItem`:

```php
public function updateCurrency(Request $request, string $type): JsonResponse
{
    $validated = $request->validate(['currency' => ['required', 'string', 'size:3']]);

    $cart = $this->resolveCart($request, $type);
    $cart->update(['currency' => strtoupper($validated['currency'])]);

    return response()->json(['data' => $this->present($cart->fresh('items.cartable'))]);
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=CartControllerTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add plugins/e-commerce/src/Controllers/CartController.php plugins/e-commerce/routes/cart.php plugins/e-commerce/tests/Feature/CartControllerTest.php
git commit -m "feat(ecommerce): add PATCH /cart/{type}/currency endpoint"
```

---

### Task 7: `PATCH /cart/{type}/shipping` endpoint

**Files:**
- Modify: `plugins/e-commerce/src/Controllers/CartController.php`
- Modify: `plugins/e-commerce/routes/cart.php`
- Test: `plugins/e-commerce/tests/Feature/CartControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `plugins/e-commerce/tests/Feature/CartControllerTest.php`:

```php
public function test_update_shipping_sets_the_shipping_fee_and_it_is_included_in_total(): void
{
    $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);
    $this->postJson('/cart/cart/items', ['resource' => 'products', 'id' => $product->id, 'quantity' => 1]);

    $response = $this->patchJson('/cart/cart/shipping', ['shipping_fee' => 15000]);

    $response->assertOk();
    $response->assertJsonPath('data.shipping_fee', 15000);
    $response->assertJsonPath('data.total', 65000);
}

public function test_update_shipping_rejects_a_negative_fee(): void
{
    $this->getJson('/cart/cart');

    $response = $this->patchJson('/cart/cart/shipping', ['shipping_fee' => -1]);

    $response->assertStatus(422);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CartControllerTest`
Expected: FAIL — 404, no route matches `/cart/cart/shipping`.

- [ ] **Step 3: Add the route**

In `plugins/e-commerce/routes/cart.php`, add:

```php
Route::patch('/cart/{type}/shipping', [CartController::class, 'updateShipping']);
```

- [ ] **Step 4: Add the controller method**

In `plugins/e-commerce/src/Controllers/CartController.php`, add after `updateCurrency`:

```php
public function updateShipping(Request $request, string $type): JsonResponse
{
    $validated = $request->validate(['shipping_fee' => ['required', 'integer', 'min:0']]);

    $cart = $this->resolveCart($request, $type);
    $cart->update(['shipping_fee' => $validated['shipping_fee']]);

    return response()->json(['data' => $this->present($cart->fresh('items.cartable'))]);
}
```

- [ ] **Step 5: Include `shipping_fee` in `present()` and fold it into `total`**

In `plugins/e-commerce/src/Controllers/CartController.php`, update `present`:

```php
return [
    'id' => $cart->id,
    'type' => $cart->type,
    'currency' => $cart->currency,
    'shipping_fee' => $cart->shipping_fee,
    'items' => $items,
    'total' => $items->sum('line_total') + $cart->shipping_fee,
];
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=CartControllerTest`
Expected: PASS

- [ ] **Step 7: Run the full cart test file**

Run: `php artisan test --filter=CartControllerTest`
Expected: All tests in the file PASS (covers Tasks 3–7).

- [ ] **Step 8: Commit**

```bash
git add plugins/e-commerce/src/Controllers/CartController.php plugins/e-commerce/routes/cart.php plugins/e-commerce/tests/Feature/CartControllerTest.php
git commit -m "feat(ecommerce): add PATCH /cart/{type}/shipping endpoint"
```

---

### Task 8: Order — replace guest_* columns with metadata, add shipping_fee/currency, compute total

**Files:**
- Modify: `plugins/e-commerce/database/migrations/2026_07_24_000001_create_orders_table.php`
- Modify: `plugins/e-commerce/src/Models/Order.php`
- Test: `plugins/e-commerce/tests/Feature/OrderTest.php`

- [ ] **Step 1: Write the failing tests**

Create `plugins/e-commerce/tests/Feature/OrderTest.php`:

```php
<?php

namespace Lumina\Ecommerce\Tests\Feature;

use Lumina\Ecommerce\Models\Order;
use Lumina\Ecommerce\Models\OrderItem;
use Lumina\Ecommerce\Models\Product;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function test_order_stores_metadata_shipping_fee_and_currency(): void
    {
        $order = Order::create([
            'status' => 'pending',
            'currency' => 'VND',
            'shipping_fee' => 15000,
            'metadata' => ['name' => 'Nguyen Van A', 'phone' => '0900000000', 'address' => '123 Le Loi'],
        ]);

        $this->assertSame('VND', $order->currency);
        $this->assertSame(15000, $order->shipping_fee);
        $this->assertSame('Nguyen Van A', $order->fresh()->metadata['name']);
    }

    public function test_order_total_is_computed_from_items_and_shipping_fee(): void
    {
        $order = Order::create([
            'status' => 'pending',
            'currency' => 'VND',
            'shipping_fee' => 15000,
        ]);

        $product = Product::factory()->create(['price' => 50000]);

        OrderItem::create([
            'order_id' => $order->id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'item_name' => 'Test product',
            'quantity' => 2,
            'unit_price' => 50000,
            'line_total' => 100000,
        ]);

        $this->assertSame(115000, $order->fresh()->total());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=OrderTest`
Expected: FAIL — `guest_name` etc. required by schema (NOT NULL) but not provided / `metadata`, `shipping_fee`, `currency` columns don't exist / `total()` method doesn't exist.

- [ ] **Step 3: Update the orders migration**

In `plugins/e-commerce/database/migrations/2026_07_24_000001_create_orders_table.php`, replace the `orders` block:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('customer_id')->nullable();
    $table->json('metadata')->nullable();
    $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
    $table->string('currency');
    $table->unsignedInteger('shipping_fee')->default(0);
    $table->timestamps();
});
```

(This drops `guest_name`, `guest_email`, `guest_phone`, `guest_address`, and `total` from the original block — replaced by `metadata`, `currency`, `shipping_fee` and the computed `total()` method respectively.)

- [ ] **Step 4: Update the `Order` model**

Replace `plugins/e-commerce/src/Models/Order.php`:

```php
<?php

namespace Lumina\Ecommerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lumina\Core\Traits\QueryBuilder;

class Order extends Model
{
    use HasFactory, QueryBuilder;

    protected $fillable = [
        'customer_id',
        'metadata',
        'status',
        'currency',
        'shipping_fee',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'shipping_fee' => 'integer',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,processing,completed,cancelled'],
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function total(): int
    {
        return $this->items->sum('line_total') + $this->shipping_fee;
    }

    protected static function newFactory()
    {
        return \Database\Factories\OrderFactory::new();
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=OrderTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add plugins/e-commerce/database/migrations/2026_07_24_000001_create_orders_table.php plugins/e-commerce/src/Models/Order.php plugins/e-commerce/tests/Feature/OrderTest.php
git commit -m "feat(ecommerce): replace order guest_* columns with metadata, compute total from items+shipping_fee"
```

---

### Task 9: Full regression run

**Files:** none (verification only)

- [ ] **Step 1: Run the full e-commerce feature suite**

Run: `php artisan test --filter=Ecommerce`

If that filter doesn't match (depends on how PHPUnit discovers the plugin's `tests/` directory), instead run the whole suite:

Run: `php artisan test`

Expected: PASS, no regressions in `CartMorphMapTest`, `CartControllerTest`, `OrderTest`, or any other existing test.

- [ ] **Step 2: If the plugin's `tests/` directory isn't discovered by `phpunit.xml`, register it**

Check `phpunit.xml`'s `<testsuites>` — it currently only points at `tests/Unit` and `tests/Feature` at the repo root. If `plugins/e-commerce/tests` isn't picked up, add a testsuite entry:

```xml
<testsuite name="Ecommerce">
    <directory>plugins/e-commerce/tests/Feature</directory>
</testsuite>
```

Re-run `php artisan test` and confirm the new tests execute.

- [ ] **Step 3: Commit (only if `phpunit.xml` was changed)**

```bash
git add phpunit.xml
git commit -m "test: register e-commerce plugin test suite"
```
