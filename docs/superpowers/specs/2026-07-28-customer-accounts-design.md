# Customer Accounts (`plugins/customer` + `plugins/social`)

Date: 2026-07-28 (revised 2)
Plugins: `plugins/customer`, `plugins/social`

## Purpose

`Cart`/`Order` already have a `customer_id` column, and
`CheckoutController`/`CartService` already read `$request->user()?->id` —
but the default `web` guard's `users` provider is commented out in
`config/auth.php`, so `customer_id` is always null today.

This spec:
- Enables the `web` guard properly (`App\Models\User` as the customer
  identity — no separate `Customer` model/table) with Sanctum token auth.
- `plugins/customer` adds customer-facing endpoints (register/login/me/
  logout) and customer segmentation via the existing taxonomy system
  (`plugins/taxonomies`), not a bespoke `customer_groups` table.
- `plugins/social` adds Google/Facebook login as a standalone, polymorphic
  capability usable by `User` (customers) or `Admin`, with zero composer
  dependency on `plugins/customer`.

## Why `App\Models\User` instead of a separate `Customer` model

Earlier revisions of this spec introduced a dedicated `Customer` model.
That's unnecessary complexity: this app already ships `App\Models\User`
specifically for the `web` guard (`config/auth.php`'s `'web' => ['driver' =>
'session', 'provider' => 'users']`, with the `users` provider currently
commented out) — it's simply never been wired up. Turning it on is less
code, avoids a duplicate identity table, and matches the existing
`Admin`/`admin` guard split (CMS staff vs. everyone else), just with
"everyone else" now meaning storefront customers instead of nothing.

## Auth wiring

**`config/auth.php`:** uncomment the `users` provider:
```php
'providers' => [
    'admins' => [...],
    'users' => [
        'driver' => 'eloquent',
        'model' => env('AUTH_MODEL', \App\Models\User::class),
    ],
],
```
The `web` guard already points at `provider: users` — no guard-block change
needed, just un-commenting the provider it was already configured to use.

**`App\Models\User`:** add `Laravel\Sanctum\HasApiTokens` alongside its
existing traits (`HasFactory`, `Notifiable`, `PasskeyAuthenticatable`,
`TwoFactorAuthenticatable`) so it can issue/verify Sanctum tokens. No new
guard is registered for this — Sanctum's own `sanctum` guard (from its
default config, already published) resolves the authenticated model
generically from the token's `tokenable` relation, so `auth:sanctum`
middleware works against `User` (or `Admin`, if `Admin` ever adopts
`HasApiTokens` too) without any per-model guard entries.

`laravel/sanctum` is added to root `composer.json` (still required — it's
the token mechanism, independent of which plugin uses it); its
`personal_access_tokens` migration is published to the root
`database/migrations`.

## `plugins/customer`

### Scope
Register/login/me/logout endpoints for `App\Models\User`, plus customer
grouping via taxonomies. No models of its own except `CustomerGroup`
(a `Taxonomy` subclass — see below); no migrations of its own (the
`taxonomies`/`taxonomables` tables already exist courtesy of
`plugins/taxonomies`, and `users` already exists courtesy of the base app).

`plugins/customer/composer.json` requires `lumina/taxonomies` (for
`Taxonomy`/`HasTaxonomies`).

### `CustomerGroup` model (`plugins/customer/src/Models/CustomerGroup.php`)
```php
namespace Lumina\Customer\Models;

use Lumina\Taxonomies\Models\Taxonomy;

class CustomerGroup extends Taxonomy
{
    // Auto sets type = "customer_group" and filters accordingly (same
    // pattern as Lumina\Taxonomies\Models\PostCategory)
}
```
Reachable through the existing generic `/api/items/customer-groups`
endpoint with zero extra code — same as every other `Taxonomy` subclass.

### `User` ↔ `CustomerGroup`
`App\Models\User` gets `use Lumina\Taxonomies\Traits\HasTaxonomies;`,
giving it `->taxonomies()` (a `morphToMany`). A user can belong to more than
one group (e.g. "VIP" + "Wholesale" simultaneously) — this is the existing
taxonomy relation shape, not a bespoke 1-1 `belongsTo` like an earlier
revision of this spec proposed.

### API (`/api/customer`, `routes/customer.php`)
```
POST /api/customer/register   — name, email, password → create User, issue Sanctum token
POST /api/customer/login      — email, password → verify, issue token
POST /api/customer/logout     — auth:sanctum — revoke current token
GET  /api/customer/me         — auth:sanctum — current user + groups + linked social accounts
```

- `register`: validates `name` (required), `email` (required, unique on
  `users`), `password` (required, `Password::min(8)`). Creates `User`,
  issues a token via `$user->createToken('api')->plainTextToken`.
- `login`: validates `email`/`password`, manual `Hash::check` + token issue
  (401 on failure, generic message, no user enumeration).
- `me`: returns the authenticated user with `taxonomies` (customer groups)
  and `socialAccounts` (from `plugins/social`, if that plugin is present —
  see below) eager loaded.
- `logout`: revokes only the presented token
  (`$request->user()->currentAccessToken()->delete()`).

`plugins/customer` does not import anything from `plugins/social` — `me`
calls `$user->socialAccounts()` only if the method exists
(`method_exists($user, 'socialAccounts')`), so `plugins/customer` works
standalone even if `plugins/social` isn't installed.

## `plugins/social`

### Scope
Fully standalone — no composer dependency on `plugins/customer`. Provides
`SocialAccount` (polymorphic), `HasSocialAccounts` trait, and Google/
Facebook login endpoints that resolve to whichever model a morph alias
points at.

### Morph map registration (app-level, not plugin-level)
Since the target models (`User`, `Admin`) are core app concepts, not
plugin concepts, their morph aliases are registered in
`app/Providers/AppServiceProvider.php::boot()`, not inside
`plugins/customer` or `plugins/social`:
```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'web' => \App\Models\User::class,
    'admin' => \Lumina\Cms\Models\Admin::class,
]);
```
This is the one place both plugins can rely on without depending on each
other — `plugins/social` reads `Relation::morphMap()` to validate the `as`
query param; it never hardcodes `User::class` or `Admin::class`.

### `SocialAccount` model + migration — unchanged from the prior revision
(`plugins/social/src/Models/SocialAccount.php`, table `social_accounts`,
polymorphic `socialable_id`/`socialable_type`, unique on
`(provider, provider_id)` and on `(socialable_id, socialable_type,
provider)`).

### `HasSocialAccounts` trait — unchanged
`App\Models\User` (and `Admin`, later) adopt it directly:
```php
use Lumina\Social\Traits\HasSocialAccounts;
```
added to `App\Models\User`'s trait list in this same spec (see "User model
changes" below), so a fresh install of `plugins/social` alone (without
`plugins/customer`) already makes `User` social-linkable.

### API (`/api/social`, `routes/social.php`)
```
POST /api/social/login/google      — id_token → verify, resolve, issue token
POST /api/social/login/facebook    — access_token → verify, resolve, issue token
```
Both accept an optional `?as=` query param (`web` or `admin`, matching the
morph map above). **Default when `as` is omitted: `web`** (i.e. `User`) —
that's the common case (customer-facing social login); admin social login
is the opt-in case via `?as=admin`. An explicitly-provided but unknown
alias still 400s; an omitted one defaults rather than erroring.

### `SocialLoginService` — same shape as before, just defaults `as`:
```php
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
        $model->socialAccounts()->create(['provider' => $provider, 'provider_id' => $providerId, 'email' => $email]);

        return $model;
    }
}
```

### Config (`plugins/social/configs/social.php`) — unchanged
```php
return [
    'google' => ['client_id' => env('GOOGLE_CLIENT_ID')],
    'facebook' => ['app_id' => env('FACEBOOK_APP_ID'), 'app_secret' => env('FACEBOOK_APP_SECRET')],
];
```

## `User` model changes (`app/Models/User.php`)

```php
use Laravel\Sanctum\HasApiTokens;
use Lumina\Social\Traits\HasSocialAccounts;
use Lumina\Taxonomies\Traits\HasTaxonomies;

class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens, HasFactory, HasSocialAccounts, HasTaxonomies, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
    // ...unchanged otherwise
}
```
This is a base-app file edit, not something either plugin can do on its
own (PHP has no way for a plugin to inject traits into a class it doesn't
own) — same reasoning the `HasSeo`/`HasTaxonomies` traits already require
of `Post`/`Product` (each consuming model opts in explicitly).

## Checkout / cart wiring

Unchanged in shape from the prior revision: `CheckoutController::store` and
`CartService::resolveCart` switch `$request->user()?->id` →
`$request->user('sanctum')?->id` (Sanctum's guard name, since there's no
longer a bespoke `customer` guard — `sanctum` resolves `User` from the
bearer token). Guest checkout stays supported (`customer_id` nullable).

## E-commerce address fields

Default shipping/billing address columns still don't belong in the base
`users` table (e-commerce-specific). `plugins/e-commerce` adds its own
migration:
```php
Schema::table('users', function (Blueprint $table) {
    $table->text('default_shipping_address')->nullable();
    $table->text('default_billing_address')->nullable();
});
```

## Validation / edge cases

- `register`/`login` never leak whether an email exists vs. wrong password.
- A `SocialAccount` unique on `(provider, provider_id)` means a Google
  account already linked to one `User` can't silently get attached to
  another by a second login attempt — the existing row always wins.
- Invalid/expired Google/Facebook tokens respond 401.
- An explicitly unknown `as` value (present but not in the morph map)
  responds 400; an omitted `as` defaults to `web`.
- `logout` only revokes the presented token, not all of the user's tokens.
- A `CustomerGroup`/taxonomy being deleted while users are still tagged
  with it: `taxonomables` rows are cleaned up by whatever cascade behavior
  `plugins/taxonomies` already implements — out of scope to add anything
  new here.

## Testing

**Auth wiring:** registering/logging in via `/api/customer/*` results in
`auth('sanctum')`/`$request->user()` resolving an `App\Models\User`, not a
separate table.

**`plugins/customer`:** register creates a user + returns a usable token;
login with correct/incorrect password; `GET /me` requires a valid token and
includes `taxonomies`/`social_accounts`; `customer-groups` are reachable
through `/api/items/customer-groups` (list/create/update/delete) with zero
bespoke code; a user can be tagged with more than one `CustomerGroup`
simultaneously; checkout's `customer_id` is populated for an authenticated
bearer-token request and stays null for a guest request.

**`plugins/social`:** Google login with no `as` param creates/logs into a
`User`; Google login with `as=admin` creates/logs into an `Admin` (mock
`Google_Client::verifyIdToken`, assert against `Lumina\Cms\Models\Admin`);
Facebook login same shape (`Http::fake`); an explicit unknown `as` value
gets 400; linking Google then Facebook to the same `User` results in two
`SocialAccount` rows.

## Out of scope this phase

Password reset flow, email verification enforcement, admin-side customer
management UI, rate limiting on login attempts, refresh tokens/token expiry
policy, Apple/other providers, merging two accounts that turn out to be the
same person, `Admin` actually adopting `HasApiTokens`/`HasSocialAccounts`
in this app's `Admin` model (the morph map entry and generic resolution
support it, but no `Admin`-model edit is done here — only `?as=admin`
plumbing exists, unexercised until `Admin` opts in), customer group
discount/pricing logic actually applied at checkout.
