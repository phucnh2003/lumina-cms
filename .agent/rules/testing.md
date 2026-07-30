# Testing Rules — PestPHP

> Path filter: `tests/**/*.php`, `plugins/*/tests/**/*.php`

## Pest DSL — bắt buộc

```php
<?php

declare(strict_types=1);

// ✅ ĐÚNG — Pest style
it('creates a product successfully', function (): void {
    // ...
});

// ✅ ĐÚNG — với describe
describe('CartController', function (): void {
    it('adds item to guest cart', function (): void {
        // ...
    });

    it('merges guest cart on login', function (): void {
        // ...
    });
});

// ❌ SAI — PHPUnit style (không dùng trong project này)
class CartTest extends TestCase
{
    public function testAddsItem(): void {}
}
```

## Test structure

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Lumina\Ecommerce\Models\Product;

// Setup
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['status' => 'active', 'stock' => 10]);
});

it('authenticated user can add product to cart', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 2,
        ]);

    $response->assertCreated()
             ->assertJsonPath('data.items.0.quantity', 2)
             ->assertJsonPath('data.items.0.unit_price', $this->product->price);

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $this->product->id,
        'quantity'   => 2,
    ]);
});
```

## Database isolation — bắt buộc

```php
// Dùng một trong hai, không cần cả hai:
uses(RefreshDatabase::class);        // Full reset mỗi test
uses(LazilyRefreshDatabase::class);  // Reset lazy (faster)
```

## Factories — không hardcode data

```php
// ❌ SAI
$user = User::create(['name' => 'John', 'email' => 'john@test.com', 'password' => '...']);

// ✅ ĐÚNG
$user = User::factory()->create();
$user = User::factory()->create(['email' => 'specific@test.com']); // override cần thiết
```

## Feature test > Unit test

Test qua HTTP requests để cover cả routing, middleware, validation:

```php
// Prefer này...
$this->postJson('/api/customer/register', $data)->assertCreated();

// ...hơn test internal method trực tiếp
$service = new CustomerAuthService();
$service->register($data);
```

## Auth trong tests

```php
// Sanctum token auth (customers)
$this->actingAs($user, 'sanctum')->getJson('/api/customer/me');

// Admin session auth
$this->actingAs($admin, 'admin')->getJson('/api/admin/dashboard');

// Guest (không authenticate)
$this->getJson('/api/cart'); // guest cart via session_token cookie
```

## Checklist coverage

Mỗi endpoint mới cần test:
- [ ] Happy path (200/201)
- [ ] Auth required (401 nếu cần login)
- [ ] Validation errors (422)
- [ ] Not found (404)
- [ ] Authorization (403 nếu có policy)
- [ ] Edge cases từ spec
