---
name: new-api-endpoint
description: >
  Create a complete API endpoint in a Lumina plugin: Route + FormRequest + Controller + Service + Feature test.
  Use this skill when asked to add a new API endpoint to any plugin.
---

# Skill: Tạo API Endpoint Mới

## Input cần biết

- Plugin: `plugins/<name>/`
- HTTP method + path: `POST /api/<name>/<action>`
- Input fields: gì cần validate
- Output: trả về gì

## Bước 1 — Route

`plugins/<name>/routes/<name>.php`:

```php
Route::prefix('api/<name>')
     ->middleware(['api'])
     ->group(function (): void {
         // Protected routes
         Route::middleware('auth:sanctum')->group(function (): void {
             Route::post('/<action>', [<Action>Controller::class, '<method>'])
                  ->name('<name>.<action>');
         });

         // Public routes (nếu cần)
         Route::post('/login', [AuthController::class, 'login'])
              ->name('<name>.login');
     });
```

## Bước 2 — FormRequest

`plugins/<name>/src/Requests/<Action>Request.php`:

```php
<?php

declare(strict_types=1);

namespace Lumina\<Name>\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class <Action>Request extends FormRequest
{
    public function rules(): array
    {
        return [
            'field'  => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

## Bước 3 — Controller

`plugins/<name>/src/Controllers/<Action>Controller.php`:

```php
<?php

declare(strict_types=1);

namespace Lumina\<Name>\Controllers;

use Illuminate\Http\JsonResponse;
use Lumina\<Name>\Requests\<Action>Request;
use Lumina\<Name>\Services\<Name>Service;

final class <Action>Controller extends Controller
{
    public function __construct(
        private readonly <Name>Service $service
    ) {}

    public function <method>(<Action>Request $request): JsonResponse
    {
        $result = $this->service-><action>($request->validated());

        return response()->json($result, 201);
    }
}
```

## Bước 4 — Service

`plugins/<name>/src/Services/<Name>Service.php`:

```php
<?php

declare(strict_types=1);

namespace Lumina\<Name>\Services;

use Illuminate\Database\Eloquent\Model;

final class <Name>Service
{
    public function <action>(array $data): Model
    {
        // Business logic ở đây
        return <Model>::create($data);
    }
}
```

## Bước 5 — Feature Test

`tests/Feature/<Name>/<Action>Test.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('<describes happy path>', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/<name>/<action>', [
            'field' => 'value',
        ]);

    $response->assertCreated()
             ->assertJsonStructure(['data' => ['id', 'field']]);
});

it('returns 422 when validation fails', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
         ->postJson('/api/<name>/<action>', [])
         ->assertUnprocessable();
});

it('returns 401 for unauthenticated requests', function (): void {
    $this->postJson('/api/<name>/<action>', ['field' => 'value'])
         ->assertUnauthorized();
});
```

## Checklist

- [ ] Route đúng method + middleware
- [ ] FormRequest với đủ validation rules
- [ ] Controller slim (chỉ gọi Service)
- [ ] Service chứa logic
- [ ] Feature test: happy path + 422 + 401
- [ ] `composer lint && composer types:check` pass
