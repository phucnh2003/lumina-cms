# Customer Accounts Implementation Plan (v2 — App\Models\User based)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. This project is not a git repository — skip any git add/commit step; just make sure the listed files exist with the specified content before moving to the next task.

**Goal:** Enable customer accounts on the existing `App\Models\User` (no separate `Customer` model), add `plugins/customer` (register/login/me/logout + `CustomerGroup` via taxonomies) and `plugins/social` (polymorphic Google/Facebook login, no dependency on `plugins/customer`), wired into the existing `customer_id` columns on `Cart`/`Order`.

**Architecture:** `config/auth.php`'s `web` guard already points at a `users` provider — it's just commented out; uncommenting it turns on `App\Models\User` as the customer identity. `User` gains `HasApiTokens` (Sanctum), `HasTaxonomies` (from `plugins/taxonomies`, for customer groups), and `HasSocialAccounts` (from `plugins/social`). `plugins/customer` provides auth endpoints and a `CustomerGroup extends Taxonomy` model — no migrations of its own. `plugins/social` provides `SocialAccount` (polymorphic `socialable_id`/`socialable_type`) and login endpoints that resolve a target model via `Relation::morphMap()` and an optional `?as=` query param (default `web` → `User`); the `web`/`admin` morph aliases are registered in `app/Providers/AppServiceProvider.php`, not in either plugin, so `plugins/social` has zero knowledge of `plugins/customer`.

**Tech Stack:** Laravel 13, Pest 4, `laravel/sanctum` (already installed — see "Already done" below), `google/apiclient` (already a root dependency, reused for Google id_token verification), Facebook Graph API via `Illuminate\Support\Facades\Http`.

---

## Already done (prior session, do not redo)

- `plugins/customer/composer.json` — PSR-4 `Lumina\Customer\`, requires `lumina/taxonomies`, registers `Lumina\Customer\Providers\CustomerServiceProvider`.
- `plugins/social/composer.json` — PSR-4 `Lumina\Social\`, no `require` block, registers `Lumina\Social\Providers\SocialServiceProvider`.
- Root `composer.json` — `require` includes `laravel/sanctum: ^4.0`, `lumina/customer: @dev`, `lumina/social: @dev`.
- `database/migrations/2026_07_28_132108_create_personal_access_tokens_table.php` and `config/sanctum.php` — published via `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`.
- `plugins/customer/src/Providers/CustomerServiceProvider.php` and `plugins/social/src/Providers/SocialServiceProvider.php` — currently minimal stub `ServiceProvider` subclasses (empty `register()`/`boot()`), created only to unblock Laravel's eager package-discovery instantiation. **Task 3 below replaces the customer one; Task 8 replaces the social one** — both are expected to be overwritten, not left as-is.
- The two migration files from an earlier plan revision (`create_customer_groups_table.php`, `create_customers_table.php`) have been **deleted** — this revision uses `App\Models\User` + the existing `taxonomies` table instead, so neither is needed.

Run `php artisan migrate` at the start of Task 1 below just to confirm the `personal_access_tokens` table exists in the dev database (it may or may not have been applied yet in this environment).

---

## Spec coverage check

Every section of `docs/superpowers/specs/2026-07-28-customer-accounts-design.md` (revised 2) maps to a task: auth wiring (`config/auth.php`, `User` traits) → Tasks 1–2; `CustomerGroup` → Task 3 (combined with `CustomerServiceProvider`); register/login → Tasks 4–5; me/logout → Task 6; `SocialAccount`/`HasSocialAccounts` → Task 7; `SocialServiceProvider`/config/morph map → Task 8; Google login → Task 9; Facebook login → Task 10; checkout/cart wiring → Task 11; e-commerce address migration → Task 12; `customer-groups` CRUD regression → Task 13; full end-to-end test → Task 14.

---

### Task 1: Enable the `web` guard + Sanctum on `App\Models\User`

**Files:**
- Modify: `config/auth.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Confirm the sanctum migration is applied**

Run: `php artisan migrate`
Expected: `personal_access_tokens` table exists (either just migrated, or "Nothing to migrate" if already applied).

- [ ] **Step 2: Uncomment the `users` provider in `config/auth.php`**

Find this commented-out block:
```php
// 'users' => [
//     'driver' => 'database',
//     'table' => 'users',
// ],
```
Replace it with:
```php
'users' => [
    'driver' => 'eloquent',
    'model' => env('AUTH_MODEL', \App\Models\User::class),
],
```
No other changes needed in this file — the `web` guard (`'web' => ['driver' => 'session', 'provider' => 'users']`) already points at this provider name.

- [ ] **Step 3: Add `HasApiTokens` to `App\Models\User`**

In `app/Models/User.php`, add the import:
```php
use Laravel\Sanctum\HasApiTokens;
```
and add `HasApiTokens` to the trait list:
```php
use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
```
(Alphabetical among the existing traits — matches the file's existing style.)

- [ ] **Step 4: Verify**

Run: `php artisan tinker --execute="echo class_exists(App\Models\User::class) && method_exists(App\Models\User::class, 'createToken') ? 'ok' : 'missing';"`
Expected: prints `ok`.

Run: `php artisan config:clear && php artisan tinker --execute="config('auth.providers.users'); echo 'ok';"`
Expected: prints `ok`, no "undefined array key" warnings.

---

### Task 2: `plugins/customer` — `CustomerGroup` model + real `CustomerServiceProvider`

**Files:**
- Create: `plugins/customer/src/Models/CustomerGroup.php`
- Modify: `plugins/customer/src/Providers/CustomerServiceProvider.php` (replace the Task-1-era stub)
- Test: `plugins/customer/tests/Feature/CustomerGroupTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Lumina\Customer\Models\CustomerGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a customer group is a taxonomy of type customer_group', function () {
    $group = CustomerGroup::create(['name' => 'VIP', 'slug' => 'vip', 'status' => 'active']);

    expect($group->fresh())
        ->name->toBe('VIP')
        ->type->toBe('customer_group');
});

test('customer groups are reachable through the generic items API', function () {
    CustomerGroup::create(['name' => 'VIP', 'slug' => 'vip', 'status' => 'active']);

    $response = $this->getJson('/api/items/customer-groups');

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'VIP']);
});
```

Check `plugins/taxonomies/src/Models/Taxonomy.php` and an existing subclass
(`plugins/taxonomies/src/Models/PostCategory.php`) before writing this —
`Taxonomy::rules()` requires `slug` and `status`, so the test data above
must include them or `CustomerGroup::create()` will fail validation if
`QueryBuilder`/model events enforce `rules()` on `create()` (check whether
`Taxonomy::booted()`'s `creating` hook auto-fills `type` the same way
`Post`'s does — if so, `type` doesn't need to be passed explicitly, matching
the first test's assertion that it's auto-set).

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/customer/tests/Feature/CustomerGroupTest.php`
Expected: FAIL — `Lumina\Customer\Models\CustomerGroup` not found.

- [ ] **Step 3: Write the model**

```php
<?php

namespace Lumina\Customer\Models;

use Lumina\Taxonomies\Models\Taxonomy;

class CustomerGroup extends Taxonomy
{
    // Auto sets type = "customer_group" and filters accordingly
    // (same pattern as Lumina\Taxonomies\Models\PostCategory)
}
```

- [ ] **Step 4: Replace the stub `CustomerServiceProvider`**

```php
<?php

namespace Lumina\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Lumina\Core\Traits\RegistersPlugins;

class CustomerServiceProvider extends ServiceProvider
{
    use RegistersPlugins {
        register as registerPlugins;
    }

    public function register(): void
    {
        $this->registerPlugins();

        config([
            'core.model_namespaces' => array_merge(config('core.model_namespaces', []), ['Lumina\\Customer\\Models']),
        ]);
    }
}
```
`RegistersPlugins::boot()` (inherited, unmodified) loads
`plugins/customer/database/migrations` (currently empty — that's fine, no
error results from an empty/missing directory) and
`plugins/customer/routes/customer.php` (doesn't exist yet — created in
Task 4). `register()`'s `core.model_namespaces` merge is what lets
`ResourceResolver`/`ItemController` find `CustomerGroup` for the generic
`/api/items/customer-groups` endpoint in the second test.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/customer/tests/Feature/CustomerGroupTest.php`
Expected: PASS (2 tests).

---

### Task 3: Register endpoint

**Files:**
- Create: `plugins/customer/src/Controllers/CustomerAuthController.php`
- Create: `plugins/customer/routes/customer.php`
- Test: `plugins/customer/tests/Feature/RegisterTest.php`

**Middleware note:** `RegistersPlugins::boot()` (shared by every plugin,
unmodified by this task) auto-loads `routes/customer.php` under
`['web', 'plugin.view:customer']` middleware — session-based,
CSRF-protected. That's fine for the CMS admin's own Inertia routes, but
these are Sanctum bearer-token API endpoints, so **inside**
`routes/customer.php`, the actual route definitions are nested one level
deeper in their own `Route::middleware('api')->group(...)` — same file,
same plugin-provider auto-loading, just an inner middleware group added
around the routes themselves.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a user can register as a customer', function () {
    $response = $this->postJson('/api/customer/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.email', 'jane@example.com');
    expect($response->json('token'))->not->toBeEmpty();
    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('registering with a duplicate email fails validation', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/customer/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

test('registering with a short password fails validation', function () {
    $response = $this->postJson('/api/customer/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
    ]);

    $response->assertJsonValidationErrors('password');
});
```

`App\Models\User::factory()` already exists in this codebase (used
throughout `tests/Feature/Auth/*`) — reuse it, don't create a new factory.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/customer/tests/Feature/RegisterTest.php`
Expected: FAIL — 404, route not found.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace Lumina\Customer\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class CustomerAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user = User::create($validated);

        return response()->json([
            'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }
}
```

Check `app/Models/User.php`'s `$fillable`/`#[Fillable(...)]` attribute
(it currently declares `['name', 'email', 'password']` via a `#[Fillable]`
PHP attribute, not the traditional `protected $fillable` property) before
assuming `User::create($validated)` mass-assigns correctly — if it doesn't
work as expected, inspect how `RegisteredUserController` (Fortify, used for
admin registration) constructs an `Admin`/`User` for the equivalent pattern
already in this codebase, and mirror it.

- [ ] **Step 4: Write the routes file**

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Customer\Controllers\CustomerAuthController;

Route::middleware('api')->prefix('api/customer')->group(function () {
    Route::post('/register', [CustomerAuthController::class, 'register']);
});
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/customer/tests/Feature/RegisterTest.php`
Expected: PASS (3 tests).

---

### Task 4: Login endpoint

**Files:**
- Modify: `plugins/customer/src/Controllers/CustomerAuthController.php`
- Modify: `plugins/customer/routes/customer.php`
- Test: `plugins/customer/tests/Feature/LoginTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a user can log in with correct credentials', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'password123']);

    $response = $this->postJson('/api/customer/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    expect($response->json('token'))->not->toBeEmpty();
});

test('login fails with the wrong password', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'password123']);

    $response = $this->postJson('/api/customer/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

test('login fails for an email that does not exist', function () {
    $response = $this->postJson('/api/customer/login', [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/customer/tests/Feature/LoginTest.php`
Expected: FAIL — 404, route not found.

- [ ] **Step 3: Add the `login` method**

Add to `CustomerAuthController`:
```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

public function login(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $validated['email'])->first();

    if (! $user || ! Hash::check($validated['password'], $user->password ?? '')) {
        throw ValidationException::withMessages(['email' => ['Invalid credentials.']])
            ->status(401);
    }

    return response()->json([
        'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        'token' => $user->createToken('api')->plainTextToken,
    ]);
}
```

- [ ] **Step 4: Wire the route**

Add inside the `Route::middleware('api')->prefix('api/customer')->group(...)` block in `plugins/customer/routes/customer.php`:
```php
Route::post('/login', [CustomerAuthController::class, 'login']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/customer/tests/Feature/LoginTest.php`
Expected: PASS (3 tests).

---

### Task 5: `me` and `logout`

**Files:**
- Modify: `plugins/customer/src/Controllers/CustomerAuthController.php`
- Modify: `plugins/customer/routes/customer.php`
- Test: `plugins/customer/tests/Feature/MeAndLogoutTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;
use Lumina\Customer\Models\CustomerGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('me requires authentication', function () {
    $this->getJson('/api/customer/me')->assertStatus(401);
});

test('me returns the authenticated user with groups', function () {
    $user = User::factory()->create();
    $group = CustomerGroup::create(['name' => 'VIP', 'slug' => 'vip', 'status' => 'active']);
    $user->taxonomies()->attach($group->id);
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/customer/me');

    $response->assertOk();
    $response->assertJsonPath('data.email', $user->email);
    $response->assertJsonCount(1, 'data.groups');
    $response->assertJsonPath('data.groups.0.name', 'VIP');
});

test('logout revokes only the presented token', function () {
    $user = User::factory()->create();
    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$tokenA}")->postJson('/api/customer/logout')->assertOk();

    $this->withHeader('Authorization', "Bearer {$tokenA}")->getJson('/api/customer/me')->assertStatus(401);
    $this->withHeader('Authorization', "Bearer {$tokenB}")->getJson('/api/customer/me')->assertOk();
});
```

This requires `App\Models\User` to already have `HasTaxonomies` (from
Task 6) for `->taxonomies()` to exist — if Task 6 hasn't landed yet when
this task runs, the second test will fail with a "method does not exist"
error; note that as a concern rather than trying to add the trait yourself
here (Task 6 owns that change, to keep this task's diff scoped to the
controller/routes).

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/customer/tests/Feature/MeAndLogoutTest.php`
Expected: FAIL — 404, routes not found (and possibly the groups test also
fails on `HasTaxonomies` missing, per the note above — that's expected
until Task 6 lands; re-run this file after Task 6 to confirm all 3 pass).

- [ ] **Step 3: Add `me` and `logout` methods**

Add to `CustomerAuthController`:
```php
public function me(Request $request): JsonResponse
{
    $user = $request->user()->load('taxonomies');

    $socialAccounts = method_exists($user, 'socialAccounts')
        ? $user->socialAccounts->map(fn ($link) => ['provider' => $link->provider, 'email' => $link->email])
        : [];

    return response()->json([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'groups' => $user->taxonomies->map(fn ($group) => ['id' => $group->id, 'name' => $group->name]),
            'social_accounts' => $socialAccounts,
        ],
    ]);
}

public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();

    return response()->json(['data' => true]);
}
```

- [ ] **Step 4: Wire the routes**

Add to `plugins/customer/routes/customer.php`, inside the existing group:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [CustomerAuthController::class, 'me']);
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
});
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/customer/tests/Feature/MeAndLogoutTest.php`
Expected: PASS (3 tests) — assuming Task 6 (`HasTaxonomies` on `User`) has
also landed; if executing tasks strictly in order, do Task 6 before this
step and come back.

---

### Task 6: Add `HasTaxonomies` to `App\Models\User`

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add the trait**

In `app/Models/User.php`, add the import:
```php
use Lumina\Taxonomies\Traits\HasTaxonomies;
```
and add `HasTaxonomies` to the trait list (alphabetical):
```php
use HasApiTokens, HasFactory, HasTaxonomies, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
```

- [ ] **Step 2: Verify**

Run: `php artisan tinker --execute="echo method_exists(App\Models\User::class, 'taxonomies') ? 'ok' : 'missing';"`
Expected: prints `ok`.

- [ ] **Step 3: Re-run Task 5's test to confirm it now fully passes**

Run: `php artisan test plugins/customer/tests/Feature/MeAndLogoutTest.php`
Expected: PASS (3 tests).

---

### Task 7: `plugins/social` — `SocialAccount` model + `HasSocialAccounts` trait

**Files:**
- Create: `plugins/social/database/migrations/2026_07_28_000000_create_social_accounts_table.php`
- Create: `plugins/social/src/Models/SocialAccount.php`
- Create: `plugins/social/src/Traits/HasSocialAccounts.php`
- Modify: `app/Models/User.php` (add the trait)
- Test: `plugins/social/tests/Feature/SocialAccountTest.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->morphs('socialable');
            $table->string('provider');
            $table->string('provider_id');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['socialable_id', 'socialable_type', 'provider'], 'social_accounts_socialable_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
```

This migration won't be picked up by Laravel until `SocialServiceProvider`
uses `RegistersPlugins` (Task 8) — that's expected; write the file now,
confirm it's syntactically valid (`php -l`), and it'll get exercised once
Task 8 lands.

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\User;
use Lumina\Social\Models\SocialAccount;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a user can have multiple linked social accounts', function () {
    $user = User::factory()->create();

    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-123', 'email' => $user->email]);
    $user->socialAccounts()->create(['provider' => 'facebook', 'provider_id' => 'fb-456', 'email' => $user->email]);

    expect($user->socialAccounts()->count())->toBe(2);
});

test('a social account resolves back to its owning user', function () {
    $user = User::factory()->create();
    $link = $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-999']);

    expect($link->socialable->is($user))->toBeTrue();
});

test('the same provider_id cannot be linked twice', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userA->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'dup']);

    expect(fn () => $userB->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'dup']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test plugins/social/tests/Feature/SocialAccountTest.php`
Expected: FAIL — `socialAccounts()` doesn't exist on `User`, `SocialAccount` model not found.

- [ ] **Step 4: Write the `SocialAccount` model**

```php
<?php

namespace Lumina\Social\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialAccount extends Model
{
    protected $fillable = ['socialable_id', 'socialable_type', 'provider', 'provider_id', 'email'];

    public function socialable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 5: Write `HasSocialAccounts`**

```php
<?php

namespace Lumina\Social\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lumina\Social\Models\SocialAccount;

trait HasSocialAccounts
{
    public function socialAccounts(): MorphMany
    {
        return $this->morphMany(SocialAccount::class, 'socialable');
    }
}
```

- [ ] **Step 6: Add the trait to `App\Models\User`**

In `app/Models/User.php`, add the import:
```php
use Lumina\Social\Traits\HasSocialAccounts;
```
and add it to the trait list (alphabetical):
```php
use HasApiTokens, HasFactory, HasSocialAccounts, HasTaxonomies, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
```

- [ ] **Step 7: Run the test — still expected to fail on migration**

Run: `php artisan test plugins/social/tests/Feature/SocialAccountTest.php`
Expected: FAIL — table `social_accounts` doesn't exist yet (migration not
loaded until Task 8's real `SocialServiceProvider`). This is expected;
proceed to Task 8, then return and re-run.

---

### Task 8: `SocialServiceProvider` + config + app-level morph map

**Files:**
- Modify: `plugins/social/src/Providers/SocialServiceProvider.php` (replace the Task-1-era stub)
- Create: `plugins/social/configs/social.php`
- Create: `plugins/social/routes/social.php` (placeholder, filled in Task 9+)
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write the config file**

```php
<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
    ],
];
```

- [ ] **Step 2: Write a placeholder routes file**

Same reasoning as `plugins/customer/routes/customer.php`: the file itself
loads under `RegistersPlugins`'s default `web` middleware, but the actual
route definitions inside it are nested in their own `api` middleware group
(stateless, no CSRF — required for these bearer-token endpoints):
```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/social')->group(function () {
    //
});
```

- [ ] **Step 3: Replace the stub `SocialServiceProvider`**

```php
<?php

namespace Lumina\Social\Providers;

use Illuminate\Support\ServiceProvider;
use Lumina\Core\Traits\RegistersPlugins;

class SocialServiceProvider extends ServiceProvider
{
    use RegistersPlugins {
        register as registerPlugins;
    }

    public function register(): void
    {
        $this->registerPlugins();
    }
}
```
`RegistersPlugins::register()` merges `configs/social.php` into
`config('social')`; `RegistersPlugins::boot()` (inherited) loads
`database/migrations` (picking up Task 7's `social_accounts` migration)
and `routes/social.php`.

- [ ] **Step 4: Register the `web`/`admin` morph map in `AppServiceProvider`**

In `app/Providers/AppServiceProvider.php`, add the import:
```php
use Illuminate\Database\Eloquent\Relations\Relation;
```
and inside `boot()` (alongside the existing `configureDefaults()` call), add:
```php
Relation::morphMap([
    'web' => \App\Models\User::class,
    'admin' => \Lumina\Cms\Models\Admin::class,
]);
```

- [ ] **Step 5: Run migrations**

Run: `php artisan migrate`
Expected: `social_accounts` table gets created now that the migration path
is loaded.

- [ ] **Step 6: Re-run Task 7's test**

Run: `php artisan test plugins/social/tests/Feature/SocialAccountTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Verify config + morph map**

Run: `php artisan tinker --execute="echo config('social.google.client_id') ?? 'null';"`
Expected: prints `null` (no `.env` value set yet), no error.

Run: `php artisan tinker --execute="print_r(\Illuminate\Database\Eloquent\Relations\Relation::morphMap());"`
Expected: array includes `web => App\Models\User` and `admin => Lumina\Cms\Models\Admin` (plus any other plugins' entries, e.g. `product`).

---

### Task 9: `SocialLoginService` + Google login

**Files:**
- Create: `plugins/social/src/Services/SocialLoginService.php`
- Create: `plugins/social/src/Services/GoogleTokenVerifier.php`
- Create: `plugins/social/src/Controllers/SocialAuthController.php`
- Modify: `plugins/social/routes/social.php`
- Test: `plugins/social/tests/Feature/GoogleLoginTest.php`

- [ ] **Step 1: Write `SocialLoginService`**

```php
<?php

namespace Lumina\Social\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lumina\Social\Models\SocialAccount;

class SocialLoginService
{
    public function resolve(string $provider, string $providerId, string $email, ?string $name, string $morphAlias): Model
    {
        $modelClass = Relation::getMorphedModel($morphAlias)
            ?? throw new \InvalidArgumentException("Unknown morph alias [{$morphAlias}].");

        $link = SocialAccount::where('provider', $provider)->where('provider_id', $providerId)->first();

        if ($link !== null) {
            return $link->socialable;
        }

        $model = $modelClass::firstOrCreate(['email' => $email], ['name' => $name ?? $email]);
        $model->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $email,
        ]);

        return $model;
    }
}
```

- [ ] **Step 2: Write the Google verifier service**

```php
<?php

namespace Lumina\Social\Services;

use Google\Client as GoogleClient;

class GoogleTokenVerifier
{
    /**
     * @return array{sub: string, email: string, name: ?string}|null
     */
    public function verify(string $idToken): ?array
    {
        $client = new GoogleClient(['client_id' => config('social.google.client_id')]);
        $payload = $client->verifyIdToken($idToken);

        if ($payload === false) {
            return null;
        }

        return [
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'name' => $payload['name'] ?? null,
        ];
    }
}
```

- [ ] **Step 3: Write the failing test**

```php
<?php

use App\Models\User;
use Lumina\Cms\Models\Admin;
use Lumina\Social\Models\SocialAccount;
use Lumina\Social\Services\GoogleTokenVerifier;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function fakeGoogleVerifier(?array $payload): void
{
    $fake = new class($payload) extends GoogleTokenVerifier {
        public function __construct(private ?array $payload) {}
        public function verify(string $idToken): ?array { return $this->payload; }
    };

    app()->instance(GoogleTokenVerifier::class, $fake);
}

test('google login with no as param defaults to the web/User model', function () {
    fakeGoogleVerifier(['sub' => 'g-123', 'email' => 'jane@example.com', 'name' => 'Jane Doe']);

    $response = $this->postJson('/api/social/login/google', ['id_token' => 'irrelevant-in-test']);

    $response->assertOk();
    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
    expect(SocialAccount::where('provider', 'google')->where('provider_id', 'g-123')->first()->socialable_type)
        ->toBe('web');
});

test('google login reuses the same user on a second login', function () {
    fakeGoogleVerifier(['sub' => 'g-123', 'email' => 'jane@example.com', 'name' => 'Jane Doe']);
    $this->postJson('/api/social/login/google', ['id_token' => 'first']);

    fakeGoogleVerifier(['sub' => 'g-123', 'email' => 'jane@example.com', 'name' => 'Jane Doe']);
    $this->postJson('/api/social/login/google', ['id_token' => 'second']);

    expect(User::where('email', 'jane@example.com')->count())->toBe(1);
    expect(SocialAccount::where('provider', 'google')->where('provider_id', 'g-123')->count())->toBe(1);
});

test('google login with as=admin resolves against the Admin model', function () {
    fakeGoogleVerifier(['sub' => 'g-admin-1', 'email' => 'admin@example.com', 'name' => 'Admin Jane']);

    $response = $this->postJson('/api/social/login/google?as=admin', ['id_token' => 'irrelevant']);

    $response->assertOk();
    expect(Admin::where('email', 'admin@example.com')->exists())->toBeTrue();
});

test('an invalid google token is rejected', function () {
    fakeGoogleVerifier(null);

    $response = $this->postJson('/api/social/login/google', ['id_token' => 'bad-token']);

    $response->assertStatus(401);
});

test('an explicitly unknown as value is rejected', function () {
    fakeGoogleVerifier(['sub' => 'g-1', 'email' => 'jane@example.com', 'name' => 'Jane']);

    $this->postJson('/api/social/login/google?as=bogus', ['id_token' => 'x'])->assertStatus(400);
});
```

The `as=admin` test requires `Lumina\Cms\Models\Admin` to support
`createToken()` (Sanctum) and `socialAccounts()` (the trait) the same way
`User` does — check `plugins/cms/src/Models/Admin.php` before writing this
test. **If `Admin` doesn't have `HasApiTokens`/`HasSocialAccounts` yet**
(per the spec, wiring `Admin` itself is out of scope for this plan), skip
this specific test with a comment explaining why, rather than adding those
traits to `Admin` here — that's an explicit out-of-scope item. Keep the
other 4 tests.

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test plugins/social/tests/Feature/GoogleLoginTest.php`
Expected: FAIL — 404, route not found.

- [ ] **Step 5: Write the controller**

```php
<?php

namespace Lumina\Social\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lumina\Social\Services\GoogleTokenVerifier;
use Lumina\Social\Services\SocialLoginService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SocialAuthController extends Controller
{
    public function __construct(
        private SocialLoginService $logins,
        private GoogleTokenVerifier $google,
    ) {
    }

    public function google(Request $request): JsonResponse
    {
        $request->validate(['id_token' => ['required', 'string']]);

        $as = $this->morphAlias($request);

        $payload = $this->google->verify($request->input('id_token'));

        if ($payload === null) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid Google token.');
        }

        $model = $this->logins->resolve('google', $payload['sub'], $payload['email'], $payload['name'], $as);

        return response()->json([
            'data' => ['id' => $model->id, 'name' => $model->name, 'email' => $model->email],
            'token' => $model->createToken('api')->plainTextToken,
        ]);
    }

    private function morphAlias(Request $request): string
    {
        $as = $request->query('as', 'web');

        if (! is_string($as) || ! array_key_exists($as, Relation::morphMap())) {
            throw new BadRequestHttpException('Unknown [as] query parameter.');
        }

        return $as;
    }
}
```

- [ ] **Step 6: Wire the route**

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Social\Controllers\SocialAuthController;

Route::middleware('api')->prefix('api/social')->group(function () {
    Route::post('/login/google', [SocialAuthController::class, 'google']);
});
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test plugins/social/tests/Feature/GoogleLoginTest.php`
Expected: PASS (4 or 5 tests, depending on whether the `as=admin` test was
kept or skipped per Step 3's note).

---

### Task 10: Facebook login

**Files:**
- Create: `plugins/social/src/Services/FacebookTokenVerifier.php`
- Modify: `plugins/social/src/Controllers/SocialAuthController.php`
- Modify: `plugins/social/routes/social.php`
- Test: `plugins/social/tests/Feature/FacebookLoginTest.php`

- [ ] **Step 1: Write the verifier service**

```php
<?php

namespace Lumina\Social\Services;

use Illuminate\Support\Facades\Http;

class FacebookTokenVerifier
{
    /**
     * @return array{id: string, email: string, name: ?string}|null
     */
    public function verify(string $accessToken): ?array
    {
        $response = Http::get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email',
            'access_token' => $accessToken,
        ]);

        if ($response->failed() || ! $response->json('id') || ! $response->json('email')) {
            return null;
        }

        return [
            'id' => $response->json('id'),
            'email' => $response->json('email'),
            'name' => $response->json('name'),
        ];
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Lumina\Social\Models\SocialAccount;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('facebook login creates a new user on first login', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'fb-1', 'email' => 'jane@example.com', 'name' => 'Jane Doe'])]);

    $response = $this->postJson('/api/social/login/facebook', ['access_token' => 'irrelevant']);

    $response->assertOk();
    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
    expect(SocialAccount::where('provider', 'facebook')->where('provider_id', 'fb-1')->exists())->toBeTrue();
});

test('facebook login reuses the same user on a second login', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'fb-1', 'email' => 'jane@example.com', 'name' => 'Jane Doe'])]);
    $this->postJson('/api/social/login/facebook', ['access_token' => 'first']);
    $this->postJson('/api/social/login/facebook', ['access_token' => 'second']);

    expect(User::where('email', 'jane@example.com')->count())->toBe(1);
});

test('an invalid facebook token is rejected', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'invalid']], 400)]);

    $response = $this->postJson('/api/social/login/facebook', ['access_token' => 'bad-token']);

    $response->assertStatus(401);
});

test('linking google then facebook to the same user results in two social accounts', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1', 'email' => 'jane@example.com']);

    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'fb-1', 'email' => 'jane@example.com', 'name' => 'Jane Doe'])]);
    $this->postJson('/api/social/login/facebook', ['access_token' => 'token']);

    expect($user->fresh()->socialAccounts()->count())->toBe(2);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test plugins/social/tests/Feature/FacebookLoginTest.php`
Expected: FAIL — 404, route not found.

- [ ] **Step 4: Add the `facebook` method to `SocialAuthController`**

Add `FacebookTokenVerifier` as a third constructor param, and reuse the
existing `morphAlias()` helper:

```php
public function __construct(
    private SocialLoginService $logins,
    private GoogleTokenVerifier $google,
    private FacebookTokenVerifier $facebook,
) {
}

public function facebook(Request $request): JsonResponse
{
    $request->validate(['access_token' => ['required', 'string']]);

    $as = $this->morphAlias($request);

    $payload = $this->facebook->verify($request->input('access_token'));

    if ($payload === null) {
        throw new UnauthorizedHttpException('Bearer', 'Invalid Facebook token.');
    }

    $model = $this->logins->resolve('facebook', $payload['id'], $payload['email'], $payload['name'], $as);

    return response()->json([
        'data' => ['id' => $model->id, 'name' => $model->name, 'email' => $model->email],
        'token' => $model->createToken('api')->plainTextToken,
    ]);
}
```

- [ ] **Step 5: Wire the route**

Add to `plugins/social/routes/social.php`:
```php
Route::post('/login/facebook', [SocialAuthController::class, 'facebook']);
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test plugins/social/tests/Feature/FacebookLoginTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Re-run Task 9's test to confirm no regression**

Run: `php artisan test plugins/social/tests/Feature/GoogleLoginTest.php`
Expected: PASS, same count as before.

---

### Task 11: Wire checkout/cart to Sanctum-authenticated `User`

**Files:**
- Modify: `plugins/e-commerce/src/Controllers/CheckoutController.php:44`
- Modify: `plugins/e-commerce/src/Services/CartService.php:29`
- Test: `plugins/e-commerce/tests/Feature/CustomerCheckoutTest.php`

- [x] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;
use Lumina\Ecommerce\Models\Cart;
use Lumina\Ecommerce\Models\Order;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('checkout attaches the authenticated user id to the order', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $product = \Lumina\Ecommerce\Models\Product::factory()->create(['price' => 10000, 'stock' => 5, 'status' => 'active']);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/cart/items', ['resource' => 'products', 'id' => $product->id, 'quantity' => 1]);

    $cart = Cart::where('customer_id', $user->id)->first();
    $cart->items()->update(['selected' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/checkout', [
        'name' => $user->name,
        'email' => $user->email,
        'shipping_address' => '123 Main St',
        'payment_method' => 'cod',
    ]);

    $response->assertCreated();
    expect(Order::first()->customer_id)->toBe($user->id);
});

test('guest checkout still works with a null customer_id', function () {
    $product = \Lumina\Ecommerce\Models\Product::factory()->create(['price' => 10000, 'stock' => 5, 'status' => 'active']);
    $this->postJson('/api/cart/items', ['resource' => 'products', 'id' => $product->id, 'quantity' => 1]);

    $cart = Cart::whereNull('customer_id')->first();
    $cart->items()->update(['selected' => true]);

    $response = $this->postJson('/api/checkout', [
        'name' => 'Guest',
        'email' => 'guest@example.com',
        'shipping_address' => '123 Main St',
        'payment_method' => 'cod',
    ]);

    $response->assertCreated();
    expect(Order::first()->customer_id)->toBeNull();
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/e-commerce/tests/Feature/CustomerCheckoutTest.php`
Expected: FAIL on the first test — `customer_id` is `null` even with a
bearer token, because the code still calls the default `web` guard's
session-based `$request->user()`, not the Sanctum-token-resolved one.

- [x] **Step 3: Update `CartService::resolveCart`**

In `plugins/e-commerce/src/Services/CartService.php:29`, change:
```php
$customerId = $request->user()?->id;
```
to:
```php
$customerId = $request->user('sanctum')?->id;
```

- [x] **Step 4: Update `CheckoutController::store`**

In `plugins/e-commerce/src/Controllers/CheckoutController.php:44`, change:
```php
'customer_id' => $request->user()?->id,
```
to:
```php
'customer_id' => $request->user('sanctum')?->id,
```

- [x] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/e-commerce/tests/Feature/CustomerCheckoutTest.php`
Expected: PASS (2 tests).

- [x] **Step 6: Run the full e-commerce suite to check for regressions**

Run: `php artisan test --testsuite=Ecommerce`
Expected: PASS, no regressions from the guard change.

---

### Task 12: E-commerce default address columns

**Files:**
- Create: `plugins/e-commerce/database/migrations/2026_07_28_000003_add_default_addresses_to_users_table.php`

- [x] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('default_shipping_address')->nullable();
            $table->text('default_billing_address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_shipping_address', 'default_billing_address']);
        });
    }
};
```

- [x] **Step 2: Run migrations**

Run: `php artisan migrate`
Expected: `users` table gains the two nullable columns, no errors.

- [x] **Step 3: Confirm `App\Models\User`'s fillable attribute is intentionally unchanged**

`app/Models/User.php`'s `#[Fillable(['name', 'email', 'password'])]`
attribute does **not** list these two columns — mass-assigning them from
generic code would be an e-commerce-specific concern living in a
base-app file. No change needed here; e-commerce code that writes these
columns does so directly (e.g. `->update([...])`), which is out of scope
for this plan (no e-commerce profile controller is being built here).

---

### Task 13: Regression check on the generic `customer-groups` CRUD

**Files:**
- Test: `plugins/customer/tests/Feature/CustomerGroupCrudTest.php`

- [x] **Step 1: Write the test**

```php
<?php

use Lumina\Customer\Models\CustomerGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('customer groups can be created, updated, and deleted through the generic items API', function () {
    $create = $this->postJson('/api/items/customer-groups', [
        'name' => 'Wholesale',
        'slug' => 'wholesale',
        'status' => 'active',
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->putJson("/api/items/customer-groups/{$id}", ['name' => 'Wholesale Updated'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Wholesale Updated');

    $this->deleteJson("/api/items/customer-groups/{$id}")->assertOk();

    expect(CustomerGroup::find($id))->toBeNull();
});
```

If the generic items API requires admin authentication, check
`tests/Feature/Core/ItemApiCrudTest.php` for how it authenticates its
requests and mirror that same setup here before making the requests.

- [x] **Step 2: Run the test**

Run: `php artisan test plugins/customer/tests/Feature/CustomerGroupCrudTest.php`
Expected: PASS. If it fails on auth, add the admin-auth setup found above
and re-run.

---

### Task 14: End-to-end smoke test + full suite

**Files:**
- Test: `plugins/customer/tests/Feature/CustomerFullFlowTest.php`
- Modify: `.env.example`

- [x] **Step 1: Write the end-to-end test**

```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('register, login, me, logout full flow', function () {
    $register = $this->postJson('/api/customer/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ])->assertCreated();

    $token = $register->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/customer/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com');

    $login = $this->postJson('/api/customer/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ])->assertOk();

    $loginToken = $login->json('token');
    expect($loginToken)->not->toBe($token);

    $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->postJson('/api/customer/logout')
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->getJson('/api/customer/me')
        ->assertStatus(401);

    expect(User::where('email', 'jane@example.com')->count())->toBe(1);
});
```

- [x] **Step 2: Run it**

Run: `php artisan test plugins/customer/tests/Feature/CustomerFullFlowTest.php`
Expected: PASS.

- [x] **Step 3: Run the entire project test suite**

Run: `php artisan test`
Expected: PASS, no regressions in `tests/Feature/Auth/*` (admin auth
untouched — different guard/model) or `--testsuite=Ecommerce`. Pay
particular attention to any existing test that asserted the `web` guard's
`users` provider was disabled/absent — Task 1 changes that, so a test
relying on the old commented-out state (unlikely, but check) would need
updating; if found, report it rather than deleting the test blindly.

- [x] **Step 4: Add `.env.example` entries**

Add to `.env.example`:
```
GOOGLE_CLIENT_ID=
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
```
