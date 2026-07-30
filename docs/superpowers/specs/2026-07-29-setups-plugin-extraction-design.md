# Setups Plugin Extraction — Design

## Purpose

`plugins/cms` currently ships a project-scaffolding command, `lumina:setup`, that is only ever needed once per project (right after cloning, to stamp out frontend config files and clean the starter `.tsx` files). It has no business existing in a production deploy — it mutates project files and is a developer-only bootstrapping tool. Today it lives inside `plugins/cms`, which *is* pushed to prod, so it rides along unnecessarily and could theoretically be invoked in the wrong environment.

This design extracts that command into its own plugin, `plugins/setups`, which is `.gitignore`d and therefore never committed or pushed. On any environment where the plugin's directory doesn't exist (i.e. every deploy), it silently and safely disappears — no dead routes, no leftover command, no risk.

## Scope

This is an **extraction**, not new functionality. `lumina:setup`'s behavior (flags, steps, output) does not change. Only its home changes: `plugins/cms` → `plugins/setups`.

Out of scope: the web-wizard UI, install-wizard (admin creation), and demo-data seeding discussed earlier in this design conversation are deferred — not part of this change. If wanted later, they become new steps/commands inside the already-extracted `plugins/setups` plugin.

## Current state (what's being moved)

- `plugins/cms/src/Console/Commands/SetupCommand.php` — the `lumina:setup` Artisan command. Three independently-skippable steps:
  1. `publishTemplates()` — copies each file in `plugins/cms/template/` into the app root per a hardcoded map (e.g. `components.json.template` → `components.json`, `app.tsx.template` → `resources/js/app.tsx`). Skips existing destinations unless `--force`. Supports `--dry-run`, `--skip-publish`.
  2. `syncConfigFiles()` — copies `composer.json`, `tsconfig.json`, `vite.config.ts` from the app root back into `plugins/cms/`. Supports `--skip-config`.
  3. Removes `.tsx` files from `resources/js` in the main app. Supports `--skip-tsx`.
- `plugins/cms/template/` — the 5 source template files the command publishes from.
- Registered in `plugins/cms/src/Providers/CmsServiceProvider.php` via the `public $commands = [...]` array (line 37, includes `SetupCommand::class`), applied through `$this->commands($this->commands)` (line 108).
- `plugins/core/configs/plugins.php` — the master per-plugin enable-flag registry; `cms` and `core` are hardcoded as always-on and don't appear in this file.

## Target state

### Directory move

```
plugins/setups/
├── composer.json
├── src/
│   ├── Providers/SetupsServiceProvider.php
│   └── Console/Commands/SetupCommand.php   (namespace Lumina\Cms\... → Lumina\Setups\...)
└── template/                                 (moved as-is from plugins/cms/template/)
    ├── components.json.template
    ├── tsconfig.json.template
    ├── app.tsx.template
    ├── vite.config.ts.template
    └── resources/css/app.css.template
```

`SetupCommand.php`'s internal `$templateRoot` resolution (`dirname(__DIR__, 3) . '/template'`) is unaffected by the move since it's relative to the command's own file location — it will still resolve to the new `plugins/setups/template` after the directory shift. No path-construction logic changes, only the namespace.

### composer.json (new)

```json
{
    "name": "lumina/setups",
    "description": "Lumina Setups Plugin — developer-only project scaffolding (lumina:setup), never deployed to production",
    "type": "library",
    "autoload": {
        "psr-4": {
            "Lumina\\Setups\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Lumina\\Setups\\Providers\\SetupsServiceProvider"
            ]
        }
    }
}
```

### SetupsServiceProvider

Follows the same shape as `PostsServiceProvider` (the established plugin pattern) but registers the command directly, since `RegistersPlugins` handles config/migrations/views/routes, not commands:

```php
namespace Lumina\Setups\Providers;

use Illuminate\Support\ServiceProvider;
use Lumina\Core\Traits\RegistersPlugins;
use Lumina\Setups\Console\Commands\SetupCommand;

class SetupsServiceProvider extends ServiceProvider
{
    use RegistersPlugins {
        register as registerPlugins;
    }

    public function register(): void
    {
        $this->registerPlugins();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SetupCommand::class]);
        }
    }
}
```

### CmsServiceProvider cleanup

Remove the `SetupCommand` import (line 18) and its entry from `public $commands` (part of the array starting line 37) in `plugins/cms/src/Providers/CmsServiceProvider.php`. `plugins/cms` no longer knows this command exists.

### plugins.php registration

Add to `plugins/core/configs/plugins.php`:

```php
'setups' => ['enable' => is_dir(base_path('plugins/setups')) && !app()->isProduction()],
```

This double guard means: (a) if the gitignored directory isn't present — true on every prod deploy — the plugin is off regardless of environment misconfiguration, and (b) even if the directory somehow exists (e.g. an accidental local `APP_ENV=production`), it still won't register. Belt and suspenders, no single point of failure.

### .gitignore

Add `/plugins/setups` to the root `.gitignore`. Since Composer autoloads `plugins/*` already (root `composer.json`), no autoload changes are needed beyond the new plugin's own `composer.json` being picked up locally by `composer dump-autoload`.

## Consequence: a genuinely local-only plugin

Because `plugins/setups/` is gitignored, it will not exist for a fresh clone, on any teammate's machine, or in CI/CD. This is intentional per the stated goal (never pushed to prod), but it means:

- After this change lands, whoever runs the extraction locally keeps a working `plugins/setups/` on their machine; nobody else automatically gets it via `git pull`.
- Distributing the plugin to other developers (if ever needed) is an out-of-band step (e.g. copying the directory manually) — not handled by this design, and not required for the stated goal of "never on prod."

## Testing

- Run `composer dump-autoload` after the move, confirm `Lumina\Setups\Providers\SetupsServiceProvider` is discovered.
- Run `php artisan lumina:setup --dry-run` and confirm identical output/behavior to before the move (aside from any plugin-loading log lines).
- Confirm `php artisan list` no longer shows `lumina:setup` if `plugins/setups/` is removed/renamed, proving the enable-flag guard works.
- Confirm `plugins/cms` no longer references `SetupCommand` anywhere (`grep -rn SetupCommand plugins/cms`).
