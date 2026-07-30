# JAM API Core (QueryBuilder + Generic Item CRUD/Trash) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a generic `/api/items/{resource}` JSON API to `plugins/core` with filter/sort/field-selection/pagination (per the JAM API Query Guide), plus reusable Create/Read/Update/Delete, soft-delete Trash (with the same query capabilities), and JSON import/export — all as controller traits any plugin model can opt into.

**Architecture:** A `QueryBuilder` model trait exposes `applyQuery()` as an Eloquent local scope. A new `Lumina\Core\Controllers\ItemController` combines `HasQueries` (resolves `{resource}` → model class and runs queries), `HasCrud`, `HasTrash`, and `HasImportExport` traits. Resource resolution is convention-based (`post-categories` → `PostCategory`) but only classes that themselves `use QueryBuilder` are resolvable — anything else 404s, so models are opt-in, not implicitly exposed. `plugins/core` gains its own `CoreServiceProvider` (it currently has none) to merge `configs/core.php` (`model_namespaces`) and load `routes/core.php`.

**Tech Stack:** Laravel 13, PHP 8.3, Pest 4 (`pestphp/pest`), SQLite in-memory for tests, existing `Lumina\Cms\Models\Admin` (already has `SoftDeletes`) as the real model used in feature tests.

---

## File Structure

- Create: `plugins/core/src/Traits/QueryBuilder.php` — model trait, `scopeApplyQuery`
- Create: `plugins/core/src/Traits/ClonesModelData.php` — shared clone helper
- Create: `plugins/core/src/Support/ResourceResolver.php` — resource string → model class, with the `QueryBuilder` guard
- Create: `plugins/core/src/Traits/HasQueries.php` — controller trait, resolves model + builds queries
- Create: `plugins/core/src/Traits/HasCrud.php` — controller trait, index/store/update/destroy/duplicate
- Create: `plugins/core/src/Traits/HasTrash.php` — controller trait, trashIndex/trashRestore/trashForceDelete
- Create: `plugins/core/src/Traits/HasImportExport.php` — controller trait, export/import (JSON)
- Create: `plugins/core/src/Controllers/ItemController.php` — wires all traits together
- Create: `plugins/core/src/Providers/CoreServiceProvider.php`
- Create: `plugins/core/configs/core.php` — `model_namespaces` config
- Create: `plugins/core/routes/core.php` — registers `/api/items/{resource}` routes
- Modify: `plugins/core/composer.json` — register `CoreServiceProvider` in `extra.laravel.providers`
- Modify: `plugins/cms/src/Providers/CmsServiceProvider.php` — register `Lumina\Cms\Models` in `core.model_namespaces`
- Modify: `plugins/cms/src/Models/Admin.php` — `use QueryBuilder` (opts Admin into the generic API for testing)
- Create: `tests/Feature/Core/QueryBuilderTest.php`
- Create: `tests/Feature/Core/ItemApiCrudTest.php`
- Create: `tests/Feature/Core/ItemApiTrashTest.php`
- Create: `tests/Feature/Core/ItemApiImportExportTest.php`
- Create: `tests/Feature/Core/ResourceResolverTest.php`

---

### Task 1: `QueryBuilder` model trait

**Files:**
- Create: `plugins/core/src/Traits/QueryBuilder.php`
- Test: `tests/Feature/Core/QueryBuilderTest.php`
- Modify: `plugins/cms/src/Models/Admin.php`

- [ ] **Step 1: Add `use QueryBuilder;` to `Admin`**

In `plugins/cms/src/Models/Admin.php`, add the import and trait:

```php
use Lumina\Core\Traits\QueryBuilder;
```

```php
class Admin extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, QueryBuilder, SoftDeletes, TwoFactorAuthenticatable;
```

(`QueryBuilder.php` doesn't exist yet, so this will fatal-error until Step 3 — that's expected, it's what makes the next test step meaningful.)

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Core/QueryBuilderTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

it('filters with _eq and _like operators', function () {
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);

    $results = Admin::query()->applyQuery(['filter' => ['status' => ['_eq' => 'active']]])->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Alice');

    $results = Admin::query()->applyQuery(['filter' => ['name' => ['_like' => 'ali']]])->get();
    expect($results)->toHaveCount(1);
});

it('filters with _in operator', function () {
    $a = Admin::factory()->create(['status' => 'active']);
    $b = Admin::factory()->create(['status' => 'locked']);
    Admin::factory()->create(['status' => 'active']);

    $results = Admin::query()->applyQuery(['filter' => ['id' => ['_in' => [$a->id, $b->id]]]])->get();
    expect($results)->toHaveCount(2);
});

it('sorts ascending and descending, including multi-column', function () {
    Admin::factory()->create(['name' => 'Bob', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);

    $results = Admin::query()->applyQuery(['sort' => 'name'])->get();
    expect($results->pluck('name')->all())->toBe(['Alice', 'Bob']);

    $results = Admin::query()->applyQuery(['sort' => '-name'])->get();
    expect($results->pluck('name')->all())->toBe(['Bob', 'Alice']);
});

it('selects only requested fields', function () {
    Admin::factory()->create(['name' => 'Alice']);

    $result = Admin::query()->applyQuery(['fields' => ['id', 'name']])->first();
    expect($result->getAttributes())->toHaveKeys(['id', 'name']);
    expect($result->getAttributes())->not->toHaveKey('email');
});

it('paginates by default and supports limit=-1 and paginate=false', function () {
    Admin::factory()->count(3)->create();

    $paginated = Admin::query()->applyQuery(['limit' => 2, 'page' => 1]);
    expect($paginated)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    expect($paginated->count())->toBe(2);

    $all = Admin::query()->applyQuery(['limit' => -1]);
    expect($all)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($all)->toHaveCount(3);

    $noPaginate = Admin::query()->applyQuery(['paginate' => 'false']);
    expect($noPaginate)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($noPaginate)->toHaveCount(3);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Core/QueryBuilderTest.php`
Expected: FAIL — `Class "Lumina\Core\Traits\QueryBuilder" not found` (or similar), since the trait file doesn't exist yet.

- [ ] **Step 4: Write the implementation**

Create `plugins/core/src/Traits/QueryBuilder.php`:

```php
<?php

namespace Lumina\Core\Traits;

use Illuminate\Database\Eloquent\Builder;

trait QueryBuilder
{
    protected static array $queryableOperators = [
        '_eq', '_neq', '_gt', '_gte', '_lt', '_lte', '_in', '_nin',
        '_like', '_nlike', '_startswith', '_endswith',
        '_is_null', '_is_not_null', '_is_empty', '_is_not_empty',
        'checked', 'unchecked', 'has', 'does_not_have',
    ];

    public function scopeApplyQuery(Builder $query, array $params): Builder|\Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
    {
        $this->applyFields($query, $params['fields'] ?? null);
        $this->applyFilters($query, $params['filter'] ?? []);
        $this->applySort($query, $params['sort'] ?? null);

        return $this->applyPagination($query, $params);
    }

    protected function applyFields(Builder $query, ?array $fields): void
    {
        if (! $fields) {
            return;
        }

        $columns = [];
        $relations = [];

        foreach ($fields as $field) {
            if (! str_contains($field, '.')) {
                $columns[] = $field;
                continue;
            }

            [$relation, $relationField] = explode('.', $field, 2);
            $relations[$relation][] = $relationField === '*' ? null : $relationField;
        }

        if ($columns) {
            $query->addSelect($columns);
        }

        foreach ($relations as $relation => $relationFields) {
            if (in_array(null, $relationFields, true)) {
                $query->with($relation);
                continue;
            }

            $query->with([$relation => fn ($q) => $q->select($relationFields)]);
        }
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $field => $conditions) {
            if (! is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $operator => $value) {
                $this->applyFilterCondition($query, $field, $operator, $value);
            }
        }
    }

    protected function applyFilterCondition(Builder $query, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            '_eq' => $query->where($field, '=', $value),
            '_neq' => $query->where($field, '!=', $value),
            '_gt' => $query->where($field, '>', $value),
            '_gte' => $query->where($field, '>=', $value),
            '_lt' => $query->where($field, '<', $value),
            '_lte' => $query->where($field, '<=', $value),
            '_in' => $query->whereIn($field, (array) $value),
            '_nin' => $query->whereNotIn($field, (array) $value),
            '_like' => $query->where($field, 'like', "%{$value}%"),
            '_nlike' => $query->where($field, 'not like', "%{$value}%"),
            '_startswith' => $query->where($field, 'like', "{$value}%"),
            '_endswith' => $query->where($field, 'like', "%{$value}"),
            '_is_null' => $query->whereNull($field),
            '_is_not_null' => $query->whereNotNull($field),
            '_is_empty' => $query->where($field, '=', ''),
            '_is_not_empty' => $query->where($field, '!=', ''),
            'checked' => $query->where($field, true),
            'unchecked' => $query->where($field, false),
            'has' => $query->has($field),
            'does_not_have' => $query->doesntHave($field),
            default => null,
        };
    }

    protected function applySort(Builder $query, ?string $sort): void
    {
        if (! $sort) {
            return;
        }

        foreach (explode(',', $sort) as $column) {
            $direction = str_starts_with($column, '-') ? 'desc' : 'asc';
            $column = ltrim($column, '-');
            $query->orderBy($column, $direction);
        }
    }

    protected function applyPagination(Builder $query, array $params): mixed
    {
        $limit = (int) ($params['limit'] ?? 15);
        $paginate = ($params['paginate'] ?? true);
        $paginate = ! in_array($paginate, [false, 'false', '0', 0], true);

        if ($limit === -1 || ! $paginate) {
            return $query->get();
        }

        return $query->paginate($limit, ['*'], 'page', (int) ($params['page'] ?? 1));
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Core/QueryBuilderTest.php`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add plugins/core/src/Traits/QueryBuilder.php plugins/cms/src/Models/Admin.php tests/Feature/Core/QueryBuilderTest.php
git commit -m "feat(core): add QueryBuilder model trait for filter/sort/fields/pagination"
```

---

### Task 2: `ClonesModelData` shared helper trait

**Files:**
- Create: `plugins/core/src/Traits/ClonesModelData.php`

This trait has no controller/route surface of its own, so it's covered by Task 3's and Task 4's tests (`duplicate` and `trashRestore`). No standalone test file.

- [ ] **Step 1: Write the implementation**

Create `plugins/core/src/Traits/ClonesModelData.php`:

```php
<?php

namespace Lumina\Core\Traits;

use Illuminate\Database\Eloquent\Model;

trait ClonesModelData
{
    protected function cloneModelData(Model $model): Model
    {
        $data = $model->getAttributes();

        unset(
            $data[$model->getKeyName()],
            $data['created_at'],
            $data['updated_at'],
            $data['deleted_at'],
        );

        $class = $model::class;

        return new $class($data);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add plugins/core/src/Traits/ClonesModelData.php
git commit -m "feat(core): add ClonesModelData helper trait"
```

---

### Task 3: `ResourceResolver` support class

**Files:**
- Create: `plugins/core/src/Support/ResourceResolver.php`
- Create: `plugins/core/configs/core.php`
- Create: `plugins/core/src/Providers/CoreServiceProvider.php`
- Modify: `plugins/core/composer.json`
- Modify: `plugins/cms/src/Providers/CmsServiceProvider.php`
- Test: `tests/Feature/Core/ResourceResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Core/ResourceResolverTest.php`:

```php
<?php

use Lumina\Cms\Models\Admin;
use Lumina\Core\Support\ResourceResolver;

it('resolves a known resource with QueryBuilder to its model class', function () {
    expect(ResourceResolver::resolve('admins'))->toBe(Admin::class);
});

it('resolves kebab-case plural resources', function () {
    config(['core.model_namespaces' => array_merge(config('core.model_namespaces', []), ['Tests\\Fixtures'])]);

    expect(ResourceResolver::resolve('unknown-things'))->toBeNull();
});

it('returns null for a class that does not use QueryBuilder', function () {
    // Passkey exists in Lumina\Core\Models but does not use QueryBuilder
    expect(ResourceResolver::resolve('passkeys'))->toBeNull();
});

it('returns null for a resource with no matching class at all', function () {
    expect(ResourceResolver::resolve('does-not-exist-anywhere'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Core/ResourceResolverTest.php`
Expected: FAIL — `Class "Lumina\Core\Support\ResourceResolver" not found`

- [ ] **Step 3: Write the implementation**

Create `plugins/core/configs/core.php`:

```php
<?php

return [
    'model_namespaces' => [],
];
```

Create `plugins/core/src/Support/ResourceResolver.php`:

```php
<?php

namespace Lumina\Core\Support;

use Illuminate\Support\Str;
use Lumina\Core\Traits\QueryBuilder;

class ResourceResolver
{
    public static function resolve(string $resource): ?string
    {
        $className = Str::studly(Str::singular($resource));

        foreach (config('core.model_namespaces', []) as $namespace) {
            $candidate = rtrim($namespace, '\\') . '\\' . $className;

            if (class_exists($candidate) && in_array(QueryBuilder::class, class_uses_recursive($candidate), true)) {
                return $candidate;
            }
        }

        return null;
    }
}
```

Create `plugins/core/src/Providers/CoreServiceProvider.php`:

```php
<?php

namespace Lumina\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Lumina\Core\Traits\RegistersPlugins;

class CoreServiceProvider extends ServiceProvider
{
    use RegistersPlugins;
}
```

Modify `plugins/core/composer.json` to add the provider (mirrors `plugins/notification/composer.json`):

```json
{
    "name": "lumina/core",
    "description": "Lumina Core Plugin",
    "type": "library",
    "autoload": {
        "psr-4": {
            "Lumina\\Core\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "extra": {
        "laravel": {
            "providers": [
                "Lumina\\Core\\Providers\\CoreServiceProvider"
            ]
        }
    }
}
```

Modify `plugins/cms/src/Providers/CmsServiceProvider.php` — in `register()`, after the existing `config([...])` call, register the CMS model namespace:

```php
config([
    'core.model_namespaces' => array_merge(config('core.model_namespaces', []), ['Lumina\\Cms\\Models']),
]);
```

- [ ] **Step 4: Run `composer dump-autoload` so the new provider config/autoload is picked up**

Run: `composer dump-autoload`
Expected: completes without error, `bootstrap/cache/packages.php` regenerated on next artisan call.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Core/ResourceResolverTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add plugins/core/composer.json plugins/core/configs/core.php plugins/core/src/Providers/CoreServiceProvider.php plugins/core/src/Support/ResourceResolver.php plugins/cms/src/Providers/CmsServiceProvider.php tests/Feature/Core/ResourceResolverTest.php composer.lock
git commit -m "feat(core): add CoreServiceProvider and convention-based ResourceResolver"
```

---

### Task 4: `HasQueries` controller trait + `ItemController` skeleton + routing

**Files:**
- Create: `plugins/core/src/Traits/HasQueries.php`
- Create: `plugins/core/src/Controllers/ItemController.php`
- Create: `plugins/core/routes/core.php`
- Test: `tests/Feature/Core/ItemApiCrudTest.php` (index only, for now)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Core/ItemApiCrudTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

it('returns 404 for an unresolvable resource', function () {
    $this->getJson('/api/items/does-not-exist')->assertNotFound();
});

it('lists a resource with data/meta shape', function () {
    Admin::factory()->count(3)->create();

    $response = $this->getJson('/api/items/admins?limit=-1');

    $response->assertOk();
    $response->assertJsonStructure(['data', 'meta' => ['total']]);
    expect($response->json('meta.total'))->toBe(3);
});

it('applies filters and sort through the query string', function () {
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);

    $response = $this->getJson('/api/items/admins?' . http_build_query([
        'filter' => ['status' => ['_eq' => 'active']],
        'sort' => 'name',
        'limit' => -1,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Alice');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Core/ItemApiCrudTest.php`
Expected: FAIL — 404/route not found for `/api/items/*` (no routes registered yet).

- [ ] **Step 3: Write `HasQueries`**

Create `plugins/core/src/Traits/HasQueries.php`:

```php
<?php

namespace Lumina\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Lumina\Core\Support\ResourceResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait HasQueries
{
    protected function resolveModelClass(string $resource): string
    {
        $modelClass = ResourceResolver::resolve($resource);

        if ($modelClass === null) {
            throw new NotFoundHttpException("Resource [{$resource}] not found.");
        }

        return $modelClass;
    }

    protected function query(string $resource, ?Builder $base = null): Builder
    {
        $modelClass = $this->resolveModelClass($resource);

        return $base ?? $modelClass::query();
    }

    protected function queryParams(Request $request): array
    {
        return $request->all();
    }
}
```

- [ ] **Step 4: Write `ItemController` (index only for now)**

Create `plugins/core/src/Controllers/ItemController.php`:

```php
<?php

namespace Lumina\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lumina\Core\Traits\ClonesModelData;
use Lumina\Core\Traits\HasQueries;

class ItemController extends Controller
{
    use ClonesModelData, HasQueries;

    public function index(Request $request, string $resource): JsonResponse
    {
        $result = $this->query($resource)->applyQuery($this->queryParams($request));

        if ($result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return response()->json([
                'data' => $result->items(),
                'meta' => [
                    'total' => $result->total(),
                    'page' => $result->currentPage(),
                    'limit' => $result->perPage(),
                ],
            ]);
        }

        return response()->json([
            'data' => $result->values(),
            'meta' => ['total' => $result->count()],
        ]);
    }
}
```

- [ ] **Step 5: Register routes**

Create `plugins/core/routes/core.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Core\Controllers\ItemController;

Route::prefix('api/items')->group(function () {
    Route::get('{resource}', [ItemController::class, 'index'])->name('items.index');
});
```

`RegistersPlugins::boot()` already loads `routes/{name}.php` (here `core.php`, since the plugin directory is `core`) under the `web` + `plugin.view` middleware — no changes needed there. `plugin.view` only overrides the Inertia root view string, which is a no-op for JSON responses.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/Core/ItemApiCrudTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add plugins/core/src/Traits/HasQueries.php plugins/core/src/Controllers/ItemController.php plugins/core/routes/core.php tests/Feature/Core/ItemApiCrudTest.php
git commit -m "feat(core): add HasQueries trait and generic ItemController index route"
```

---

### Task 5: `HasCrud` trait — store/update/destroy/duplicate

**Files:**
- Create: `plugins/core/src/Traits/HasCrud.php`
- Modify: `plugins/core/src/Controllers/ItemController.php`
- Modify: `plugins/core/routes/core.php`
- Modify: `tests/Feature/Core/ItemApiCrudTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Core/ItemApiCrudTest.php`:

```php
it('creates a record', function () {
    $response = $this->postJson('/api/items/admins', [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'password' => 'secret-password-123',
        'status' => 'active',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('admins', ['email' => 'new-admin@example.com']);
});

it('updates a record', function () {
    $admin = Admin::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/items/admins/{$admin->id}", ['name' => 'New Name']);

    $response->assertOk();
    $this->assertDatabaseHas('admins', ['id' => $admin->id, 'name' => 'New Name']);
});

it('soft deletes a record with SoftDeletes', function () {
    $admin = Admin::factory()->create();

    $response = $this->deleteJson("/api/items/admins/{$admin->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('admins', ['id' => $admin->id]);
});

it('duplicates an active record into a new one', function () {
    $admin = Admin::factory()->create(['name' => 'Original']);

    $response = $this->postJson("/api/items/admins/{$admin->id}/duplicate");

    $response->assertCreated();
    $newId = $response->json('data.id');
    expect($newId)->not->toBe($admin->id);
    $this->assertDatabaseHas('admins', ['id' => $newId, 'name' => 'Original']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Core/ItemApiCrudTest.php`
Expected: FAIL — routes for POST/PUT/DELETE `/api/items/{resource}[/{id}]` don't exist yet.

- [ ] **Step 3: Write `HasCrud`**

Create `plugins/core/src/Traits/HasCrud.php`:

```php
<?php

namespace Lumina\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HasCrud
{
    public function store(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);

        $model = $modelClass::create($request->all());

        return response()->json(['data' => $model], 201);
    }

    public function update(Request $request, string $resource, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $model = $modelClass::query()->findOrFail($id);
        $model->update($request->all());

        return response()->json(['data' => $model->fresh()]);
    }

    public function destroy(string $resource, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $model = $modelClass::query()->findOrFail($id);
        $model->delete();

        return response()->json(null, 204);
    }

    public function duplicate(string $resource, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);

        $copy = $this->cloneModelData($model);
        $copy->save();

        return response()->json(['data' => $copy], 201);
    }
}
```

- [ ] **Step 4: Wire into `ItemController` and routes**

Modify `plugins/core/src/Controllers/ItemController.php`, add `HasCrud` to the `use` statement:

```php
use ClonesModelData, HasCrud, HasQueries;
```

Modify `plugins/core/routes/core.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Core\Controllers\ItemController;

Route::prefix('api/items')->group(function () {
    Route::get('{resource}', [ItemController::class, 'index'])->name('items.index');
    Route::post('{resource}', [ItemController::class, 'store'])->name('items.store');
    Route::put('{resource}/{id}', [ItemController::class, 'update'])->name('items.update');
    Route::post('{resource}/{id}/duplicate', [ItemController::class, 'duplicate'])->name('items.duplicate');
    Route::delete('{resource}/{id}', [ItemController::class, 'destroy'])->name('items.destroy');
});
```

(`duplicate` is registered before the bare `{resource}/{id}` DELETE route only matters for method+path combos that could collide; here methods differ so order is not load-bearing, but keeping `duplicate` grouped with its sibling routes keeps the file readable.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Core/ItemApiCrudTest.php`
Expected: PASS (7 tests total)

- [ ] **Step 6: Commit**

```bash
git add plugins/core/src/Traits/HasCrud.php plugins/core/src/Controllers/ItemController.php plugins/core/routes/core.php tests/Feature/Core/ItemApiCrudTest.php
git commit -m "feat(core): add HasCrud trait (store/update/destroy/duplicate) to ItemController"
```

---

### Task 6: `HasTrash` trait — trashIndex/trashRestore/trashForceDelete

**Files:**
- Create: `plugins/core/src/Traits/HasTrash.php`
- Modify: `plugins/core/src/Controllers/ItemController.php`
- Modify: `plugins/core/routes/core.php`
- Create: `tests/Feature/Core/ItemApiTrashTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Core/ItemApiTrashTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

it('lists only trashed records with query support', function () {
    $active = Admin::factory()->create(['name' => 'Active One']);
    $trashed = Admin::factory()->create(['name' => 'Trashed One']);
    $trashed->delete();

    $response = $this->getJson('/api/items/admins/trash?limit=-1');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($trashed->id);
    expect($ids)->not->toContain($active->id);
});

it('supports filter and sort on the trash listing', function () {
    $a = Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    $b = Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);
    $a->delete();
    $b->delete();

    $response = $this->getJson('/api/items/admins/trash?' . http_build_query([
        'filter' => ['status' => ['_eq' => 'active']],
        'sort' => 'name',
        'limit' => -1,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Alice');
});

it('restore creates a new active record and leaves the trashed one untouched', function () {
    $admin = Admin::factory()->create(['name' => 'Deleted Guy']);
    $admin->delete();

    $response = $this->postJson("/api/items/admins/trash/{$admin->id}/restore");

    $response->assertCreated();
    $newId = $response->json('data.id');
    expect($newId)->not->toBe($admin->id);

    $this->assertDatabaseHas('admins', ['id' => $newId, 'name' => 'Deleted Guy', 'deleted_at' => null]);
    $this->assertSoftDeleted('admins', ['id' => $admin->id]);
});

it('force deletes a trashed record permanently', function () {
    $admin = Admin::factory()->create();
    $admin->delete();

    $response = $this->deleteJson("/api/items/admins/trash/{$admin->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Core/ItemApiTrashTest.php`
Expected: FAIL — `/api/items/admins/trash*` routes don't exist yet.

- [ ] **Step 3: Write `HasTrash`**

Create `plugins/core/src/Traits/HasTrash.php`:

```php
<?php

namespace Lumina\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HasTrash
{
    public function trashIndex(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $result = $modelClass::onlyTrashed()->applyQuery($this->queryParams($request));

        if ($result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return response()->json([
                'data' => $result->items(),
                'meta' => [
                    'total' => $result->total(),
                    'page' => $result->currentPage(),
                    'limit' => $result->perPage(),
                ],
            ]);
        }

        return response()->json([
            'data' => $result->values(),
            'meta' => ['total' => $result->count()],
        ]);
    }

    public function trashRestore(string $resource, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        /** @var Model $trashed */
        $trashed = $modelClass::onlyTrashed()->findOrFail($id);

        $copy = $this->cloneModelData($trashed);
        $copy->save();

        return response()->json(['data' => $copy], 201);
    }

    public function trashForceDelete(string $resource, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $modelClass::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 4: Wire into `ItemController` and routes**

Modify `plugins/core/src/Controllers/ItemController.php`:

```php
use ClonesModelData, HasCrud, HasQueries, HasTrash;
```

Modify `plugins/core/routes/core.php` — add the trash group **before** the generic `{resource}/{id}` routes so `trash` isn't swallowed as an `{id}`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Core\Controllers\ItemController;

Route::prefix('api/items')->group(function () {
    Route::get('{resource}/trash', [ItemController::class, 'trashIndex'])->name('items.trash.index');
    Route::post('{resource}/trash/{id}/restore', [ItemController::class, 'trashRestore'])->name('items.trash.restore');
    Route::delete('{resource}/trash/{id}', [ItemController::class, 'trashForceDelete'])->name('items.trash.forceDelete');

    Route::get('{resource}', [ItemController::class, 'index'])->name('items.index');
    Route::post('{resource}', [ItemController::class, 'store'])->name('items.store');
    Route::put('{resource}/{id}', [ItemController::class, 'update'])->name('items.update');
    Route::post('{resource}/{id}/duplicate', [ItemController::class, 'duplicate'])->name('items.duplicate');
    Route::delete('{resource}/{id}', [ItemController::class, 'destroy'])->name('items.destroy');
});
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Core/ItemApiTrashTest.php`
Expected: PASS (4 tests)

Run also: `php artisan test tests/Feature/Core/ItemApiCrudTest.php`
Expected: still PASS (route reordering must not break the earlier CRUD routes)

- [ ] **Step 6: Commit**

```bash
git add plugins/core/src/Traits/HasTrash.php plugins/core/src/Controllers/ItemController.php plugins/core/routes/core.php tests/Feature/Core/ItemApiTrashTest.php
git commit -m "feat(core): add HasTrash trait with query-aware trash listing, restore-as-duplicate, force delete"
```

---

### Task 7: `HasImportExport` trait — JSON export/import

**Files:**
- Create: `plugins/core/src/Traits/HasImportExport.php`
- Modify: `plugins/core/src/Controllers/ItemController.php`
- Modify: `plugins/core/routes/core.php`
- Create: `tests/Feature/Core/ItemApiImportExportTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Core/ItemApiImportExportTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

it('exports records as a JSON file honoring filters', function () {
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);

    $response = $this->get('/api/items/admins/export?' . http_build_query([
        'filter' => ['status' => ['_eq' => 'active']],
    ]));

    $response->assertOk();
    $payload = json_decode($response->streamedContent(), true);
    expect($payload)->toHaveCount(1);
    expect($payload[0]['name'])->toBe('Alice');
});

it('imports records from a JSON file and reports success/failure counts', function () {
    $rows = [
        ['name' => 'Imported One', 'email' => 'imported-one@example.com', 'password' => 'secret-password', 'status' => 'active'],
        ['name' => 'Imported Two', 'email' => 'imported-two@example.com', 'password' => 'secret-password', 'status' => 'active'],
    ];

    $file = UploadedFile::fake()->createWithContent('admins.json', json_encode($rows));

    $response = $this->postJson('/api/items/admins/import', ['file' => $file]);

    $response->assertOk();
    expect($response->json('imported'))->toBe(2);
    expect($response->json('failed'))->toBe(0);
    $this->assertDatabaseHas('admins', ['email' => 'imported-one@example.com']);
    $this->assertDatabaseHas('admins', ['email' => 'imported-two@example.com']);
});

it('counts failed rows without aborting the whole import', function () {
    $rows = [
        ['name' => 'Good Row', 'email' => 'good-row@example.com', 'password' => 'secret-password', 'status' => 'active'],
        ['name' => null, 'email' => null], // invalid row: NOT NULL violation
    ];

    $file = UploadedFile::fake()->createWithContent('admins.json', json_encode($rows));

    $response = $this->postJson('/api/items/admins/import', ['file' => $file]);

    $response->assertOk();
    expect($response->json('imported'))->toBe(1);
    expect($response->json('failed'))->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Core/ItemApiImportExportTest.php`
Expected: FAIL — `/api/items/admins/export` and `/api/items/admins/import` routes don't exist yet.

- [ ] **Step 3: Write `HasImportExport`**

Create `plugins/core/src/Traits/HasImportExport.php`:

```php
<?php

namespace Lumina\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HasImportExport
{
    public function export(Request $request, string $resource): StreamedResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $result = $modelClass::query()->applyQuery(array_merge($this->queryParams($request), ['limit' => -1]));

        $rows = $result instanceof \Illuminate\Support\Collection || $result instanceof \Illuminate\Database\Eloquent\Collection
            ? $result
            : collect($result->items());

        return response()->streamDownload(function () use ($rows) {
            echo $rows->toJson();
        }, "{$resource}-" . now()->format('Y-m-d-His') . '.json', ['Content-Type' => 'application/json']);
    }

    public function import(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);

        $request->validate(['file' => ['required', 'file']]);

        $rows = json_decode($request->file('file')->get(), true) ?? [];

        $imported = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $failed++;
                continue;
            }

            try {
                $modelClass::create($row);
                $imported++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return response()->json(['imported' => $imported, 'failed' => $failed]);
    }
}
```

- [ ] **Step 4: Wire into `ItemController` and routes**

Modify `plugins/core/src/Controllers/ItemController.php`:

```php
use ClonesModelData, HasCrud, HasImportExport, HasQueries, HasTrash;
```

Modify `plugins/core/routes/core.php` — add export/import **before** the generic `{resource}` routes for the same reason as `trash`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Core\Controllers\ItemController;

Route::prefix('api/items')->group(function () {
    Route::get('{resource}/trash', [ItemController::class, 'trashIndex'])->name('items.trash.index');
    Route::post('{resource}/trash/{id}/restore', [ItemController::class, 'trashRestore'])->name('items.trash.restore');
    Route::delete('{resource}/trash/{id}', [ItemController::class, 'trashForceDelete'])->name('items.trash.forceDelete');

    Route::get('{resource}/export', [ItemController::class, 'export'])->name('items.export');
    Route::post('{resource}/import', [ItemController::class, 'import'])->name('items.import');

    Route::get('{resource}', [ItemController::class, 'index'])->name('items.index');
    Route::post('{resource}', [ItemController::class, 'store'])->name('items.store');
    Route::put('{resource}/{id}', [ItemController::class, 'update'])->name('items.update');
    Route::post('{resource}/{id}/duplicate', [ItemController::class, 'duplicate'])->name('items.duplicate');
    Route::delete('{resource}/{id}', [ItemController::class, 'destroy'])->name('items.destroy');
});
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Core/ItemApiImportExportTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full new test suite together**

Run: `php artisan test tests/Feature/Core`
Expected: PASS — all tests in `QueryBuilderTest`, `ResourceResolverTest`, `ItemApiCrudTest`, `ItemApiTrashTest`, `ItemApiImportExportTest` pass together (checks route ordering and shared state don't conflict).

- [ ] **Step 7: Commit**

```bash
git add plugins/core/src/Traits/HasImportExport.php plugins/core/src/Controllers/ItemController.php plugins/core/routes/core.php tests/Feature/Core/ItemApiImportExportTest.php
git commit -m "feat(core): add HasImportExport trait (JSON export/import) to ItemController"
```

---

## Notes for whoever picks up the next pass

Deferred per the design doc (`docs/superpowers/specs/2026-07-23-crud-trash-importexport-traits-design.md`): multilingual field access (`title->vi`), attachment field types, `HasRevisions`, `Cacheable`, `HasRelations`/`HasValidation`. None of the code in this plan blocks adding those later — they layer on top of `QueryBuilder::applyFields`/`applyFilters` and the `ItemController` traits without changing their public shape.
