---
name: new-plugin
description: >
  Scaffold a new Lumina CMS plugin from scratch. Use this skill when asked to
  create a new plugin in the plugins/ directory. Generates the full plugin
  structure: composer.json, ServiceProvider, routes, migrations, and first model/controller.
---

# Skill: Tạo Plugin Mới

## Bước 1 — Xác định thông tin plugin

Hỏi (hoặc suy ra từ context):
- Tên plugin: `<name>` (lowercase, e.g. `shipping`)
- Namespace: `Lumina\<Name>` (e.g. `Lumina\Shipping`)
- Models cần tạo

## Bước 2 — Tạo cấu trúc thư mục

```
plugins/<name>/
├── composer.json
├── configs/
│   └── <name>.php
├── database/
│   └── migrations/
├── routes/
│   └── <name>.php
└── src/
    ├── Controllers/
    ├── Models/
    ├── Providers/
    │   └── <Name>ServiceProvider.php
    ├── Requests/
    └── Services/
```

## Bước 3 — composer.json

```json
{
    "name": "lumina/<name>",
    "description": "<Description>",
    "type": "library",
    "require": {},
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
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

## Bước 4 — ServiceProvider

```php
<?php

declare(strict_types=1);

namespace Lumina\<Name>\Providers;

use Illuminate\Support\ServiceProvider;

final class <Name>ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../configs/<name>.php', '<name>');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/<name>.php');
    }
}
```

## Bước 5 — Đăng ký vào root composer.json

Thêm vào `require`:
```json
"lumina/<name>": "@dev"
```

Repositories `plugins/*` đã có sẵn — không cần thêm.

## Bước 6 — Chạy

```bash
composer update lumina/<name>
php artisan migrate
```

## Checklist hoàn thành

- [ ] `plugins/<name>/composer.json` — đúng name và namespace
- [ ] `<Name>ServiceProvider.php` — load routes, migrations, configs
- [ ] Đã add vào root `composer.json` require
- [ ] Migration tạo bảng đầu tiên
- [ ] Model đầu tiên có `use QueryBuilder` (nếu cần JAM API)
- [ ] Route file với prefix `/api/<name>`
- [ ] `composer update lumina/<name>` thành công
