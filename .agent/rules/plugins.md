# Plugin Development Rules

> Path filter: `plugins/**`

## ServiceProvider — entry point của mọi plugin

```php
<?php

declare(strict_types=1);

namespace Lumina\<Name>\Providers;

use Illuminate\Support\ServiceProvider;
use Lumina\Core\Traits\RegistersPlugins;

final class <Name>ServiceProvider extends ServiceProvider
{
    use RegistersPlugins {
        register as registerPlugins;
        boot as bootPlugins;
    }

    public function register(): void
    {
        // registerPlugins() merges configs/<name>.php into config('<name>')
        // AND checks plugins.php's enable flag first — bind extra services after it.
        $this->registerPlugins();
    }

    public function boot(): void
    {
        // bootPlugins() loads migrations/views/routes/(name).php, gated by the
        // enable flag. Anything written directly here (like the morph map below)
        // still runs unconditionally — guard it yourself if it must respect
        // plugins.php too (e.g. `if ($this->pluginEnabled('<name>'))`).
        $this->bootPlugins();

        // Morph map (nếu plugin có polymorphic models)
        // Relation::morphMap(['<alias>' => ModelClass::class]);
    }
}
```

## composer.json của plugin

```json
{
    "name": "lumina/<name>",
    "type": "library",
    "autoload": {
        "psr-4": {
            "Lumina\\<Name>\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Lumina\\<Name>\\Providers\\<Name>ServiceProvider"
            ]
        }
    }
}
```

## Plugin isolation — quy tắc tuyệt đối

```php
// ❌ SAI — trực tiếp import từ plugin khác
use Lumina\Customer\Models\Customer;
use Lumina\Social\Models\SocialAccount;

// ✅ ĐÚNG — morph map (không biết class cụ thể)
$modelClass = Relation::getMorphedModel($alias);

// ✅ ĐÚNG — optional dependency (plugin có thể chạy standalone)
if (method_exists($model, 'socialAccounts')) {
    $accounts = $model->socialAccounts;
}

// ✅ ĐÚNG — interface (define interface trong plugin mình, implement ở nơi dùng)
public function resolve(Purchasable $item): CartItem {}
```

## Model muốn expose qua JAM API

```php
use Lumina\Core\Traits\QueryBuilder;

class Product extends Model
{
    use QueryBuilder; // ← BẮT BUỘC để /api/items/products hoạt động

    // Model sẽ tự động được resolve bởi ItemController
    // resource segment: 'products' → Product (kebab-plural → StudlySingular)
}
```

## Morph map — đặt đúng chỗ

```php
// ✅ ĐÚNG — Morph alias của root app models đặt trong AppServiceProvider
// app/Providers/AppServiceProvider.php
Relation::morphMap([
    'web'   => \App\Models\User::class,
    'admin' => \Lumina\Cms\Models\Admin::class,
]);

// ✅ ĐÚNG — Morph alias của plugin's own models đặt trong plugin ServiceProvider
// plugins/e-commerce/src/Providers/EcommerceServiceProvider.php
Relation::morphMap([
    'product' => \Lumina\Ecommerce\Models\Product::class,
]);
```

## Plugin enable/disable — bắt buộc khi tạo plugin mới

Mọi plugin (trừ `core`, `cms`) **phải** có 1 entry trong `plugins/core/configs/plugins.php`:

```php
// plugins/core/configs/plugins.php
return [
    // ...
    '<name>' => ['enable' => true],
];
```

- Đọc qua `config("plugins.{name}.enable")` — `RegistersPlugins::pluginEnabled()` gọi giá trị này ở đầu `register()`/`boot()` để quyết định có load config/migrations/views/routes của plugin đó hay không.
- Thiếu entry → mặc định `true` (an toàn), nhưng vẫn nên khai báo tường minh để `Lumina\Core\Support\PluginRegistry::all()` (share qua Inertia prop `plugins`) liệt kê đầy đủ.
- **Không** thêm entry cho `core`/`cms` — 2 plugin này luôn hard-coded enabled trong `RegistersPlugins::pluginEnabled()`, không đọc config.
- Không tự ý gọi `$this->loadMigrationsFrom()`/`Route::...` ngoài `RegistersPlugins::boot()` nếu muốn plugin tôn trọng cờ enable — tự viết loading logic riêng sẽ bỏ qua check này.

## Route naming convention & Middleware

- **Storefront Client APIs**: Các API dành cho storefront bên ngoài (không qua dashboard admin) sử dụng middleware `api`.
- **Admin Dashboard & Plugin Core Routes**: Tất cả các route quản lý admin của plugin (load qua file `routes/<name>.php` nhờ trait `RegistersPlugins`) luôn được tự động đăng ký dưới nhóm middleware `web` (bao gồm session, csrf, cookie) và middleware `plugin.view:<name>`. **Tuyệt đối không dùng nhóm middleware `api` cho các tác vụ quản trị admin.**

```php
// ✅ ĐÚNG - Storefront API
Route::prefix('api/<name>')
     ->middleware(['api'])
     ->name('<name>.')
     ->group(function () {
         Route::post('/register', [AuthController::class, 'register'])->name('register');
     });

// ✅ ĐÚNG - Admin / Core Plugin routes nạp qua RegistersPlugins sẽ tự động ăn nhóm middleware ['web', 'plugin.view:<name>']
// Đăng ký route trong routes/<name>.php không bọc middleware 'api'
Route::prefix('api/items/<name>')->group(function () {
    // ...
});
```
