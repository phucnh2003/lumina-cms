# Setups Plugin Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the `lumina:setup` Artisan command and its template files out of `plugins/cms` (which is deployed to production) into a new, `.gitignore`d `plugins/setups` plugin (which is never deployed), with no behavior change to the command itself.

**Architecture:** Create a new plugin skeleton (`plugins/setups`) following the same composer.json + ServiceProvider shape as `plugins/posts`. Physically move `SetupCommand.php` and the `template/` directory into it, updating only the namespace and the one hardcoded path (`plugins/cms` → `plugins/setups`) inside `syncConfigFiles()`. Deregister the command from `plugins/cms/src/Providers/CmsServiceProvider.php`. Register the new plugin in `plugins/core/configs/plugins.php` behind a directory-existence + non-production guard, and add `/plugins/setups` to `.gitignore` so it's never committed.

**Tech Stack:** Laravel 11 (PHP), Composer PSR-4 autoloading, Artisan console commands.

**Spec:** `docs/superpowers/specs/2026-07-29-setups-plugin-extraction-design.md`

---

### Task 1: Scaffold the `plugins/setups` plugin skeleton

**Files:**
- Create: `plugins/setups/composer.json`
- Create: `plugins/setups/src/Providers/SetupsServiceProvider.php`

- [ ] **Step 1: Create the plugin directory and composer.json**

```bash
mkdir -p plugins/setups/src/Providers
mkdir -p plugins/setups/src/Console/Commands
```

Write `plugins/setups/composer.json`:

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

- [ ] **Step 2: Write the SetupsServiceProvider**

Write `plugins/setups/src/Providers/SetupsServiceProvider.php`:

```php
<?php

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

- [ ] **Step 3: Verify the files exist**

```bash
ls plugins/setups/composer.json plugins/setups/src/Providers/SetupsServiceProvider.php
```

Expected: both paths listed, no "No such file" errors.

Note: this project is not a git repository (no `.git` directory), so this plan does not use `git` commands anywhere — plain `mkdir`/`mv`/`rm` only. `plugins/setups/` will still end up listed in `.gitignore` per Task 5, for the day this project *is* put under version control.

---

### Task 2: Move `SetupCommand.php` into the new plugin

**Files:**
- Move: `plugins/cms/src/Console/Commands/SetupCommand.php` → `plugins/setups/src/Console/Commands/SetupCommand.php`

- [ ] **Step 1: Move the file**

```bash
mv plugins/cms/src/Console/Commands/SetupCommand.php plugins/setups/src/Console/Commands/SetupCommand.php
```

- [ ] **Step 2: Update the namespace**

In `plugins/setups/src/Console/Commands/SetupCommand.php`, change:

```php
namespace Lumina\Cms\Console\Commands;
```

to:

```php
namespace Lumina\Setups\Console\Commands;
```

- [ ] **Step 3: Update the hardcoded plugin path in `syncConfigFiles()`**

The method currently syncs `composer.json`, `tsconfig.json`, `vite.config.ts` from the app root back into the command's *own* plugin directory. Since the command now lives in `plugins/setups`, this must point there instead of the old `plugins/cms`.

Find this line inside `syncConfigFiles()`:

```php
        $pluginRoot = base_path('plugins/cms');
```

Replace with:

```php
        $pluginRoot = base_path('plugins/setups');
```

Also update the doc comment directly above `syncConfigFiles()` (currently reads "into the cms plugin directory") to say "into the setups plugin directory", and the log line `"Syncing config files to plugins/cms ..."` to `"Syncing config files to plugins/setups ..."`, and the two-column detail label `"plugins/cms/{$destRelative}"` to `"plugins/setups/{$destRelative}"`. These are cosmetic but must match the new destination so `--dry-run` output isn't misleading.

- [ ] **Step 4: Verify `$templateRoot` resolution still works**

No code change needed here — `publishTemplates()` computes `$templateRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'template'`, which is relative to the command file's own location (3 levels up from `src/Console/Commands/` = the plugin root). Since the file moved with the rest of its plugin, this still resolves correctly to `plugins/setups/template` once Task 3 moves the template directory. Just confirm by reading the line and reasoning through the path — no test needed at this step since the template directory doesn't exist yet.

- [ ] **Step 5: Verify**

```bash
grep -n "namespace\|pluginRoot = base_path" plugins/setups/src/Console/Commands/SetupCommand.php
```

Expected: shows `namespace Lumina\Setups\Console\Commands;` and `$pluginRoot = base_path('plugins/setups');`.

---

### Task 3: Move the template directory

**Files:**
- Move: `plugins/cms/template/` → `plugins/setups/template/`

- [ ] **Step 1: Move all five template files preserving structure**

```bash
mkdir -p plugins/setups/template/resources/css
mv plugins/cms/template/components.json.template plugins/setups/template/components.json.template
mv plugins/cms/template/tsconfig.json.template plugins/setups/template/tsconfig.json.template
mv plugins/cms/template/app.tsx.template plugins/setups/template/app.tsx.template
mv plugins/cms/template/vite.config.ts.template plugins/setups/template/vite.config.ts.template
mv plugins/cms/template/resources/css/app.css.template plugins/setups/template/resources/css/app.css.template
```

- [ ] **Step 2: Confirm the old template directory is empty and remove it if so**

```bash
find plugins/cms/template -type f
```

Expected: no output (empty). If empty, remove the now-empty directory tree:

```bash
find plugins/cms/template -type d -empty -delete
```

- [ ] **Step 3: Verify all five template files landed correctly**

```bash
find plugins/setups/template -type f
```

Expected: 5 files listed (`components.json.template`, `tsconfig.json.template`, `app.tsx.template`, `vite.config.ts.template`, `resources/css/app.css.template`).

---

### Task 4: Deregister `SetupCommand` from `plugins/cms`

**Files:**
- Modify: `plugins/cms/src/Providers/CmsServiceProvider.php:18,40`

- [ ] **Step 1: Remove the import**

In `plugins/cms/src/Providers/CmsServiceProvider.php`, delete this line (currently line 18):

```php
use Lumina\Cms\Console\Commands\SetupCommand;
```

- [ ] **Step 2: Remove the command registration**

Change:

```php
    public $commands = [
        PullFilesCommand::class,
        GeneratePermissionsCommand::class,
        SetupCommand::class,
    ];
```

to:

```php
    public $commands = [
        PullFilesCommand::class,
        GeneratePermissionsCommand::class,
    ];
```

- [ ] **Step 3: Verify no other references remain**

```bash
grep -rn "SetupCommand" plugins/cms
```

Expected: no output.

---

### Task 5: Register the plugin, guard against production, and gitignore it

**Files:**
- Modify: `plugins/core/configs/plugins.php`
- Modify: `.gitignore`

- [ ] **Step 1: Add the `setups` entry to the plugin registry**

In `plugins/core/configs/plugins.php`, the array is alphabetically ordered: `coupon, customer, e-commerce, locations, notification, opt, payment, posts, ratings, redirects, seo, shipping, sliders, social, taxonomies`. `setups` sorts between `seo` and `shipping` — insert it there:

```php
    'seo' => ['enable' => true],
    'setups' => ['enable' => is_dir(base_path('plugins/setups')) && !app()->isProduction()],
    'shipping' => ['enable' => true],
```

- [ ] **Step 2: Add `/plugins/setups` to `.gitignore`**

Append to the root `.gitignore` (any location in the file is fine; group it near the top with other path-based ignores):

```
/plugins/setups
```

- [ ] **Step 3: Verify all `plugins/setups` files still exist on disk**

```bash
ls plugins/setups/composer.json plugins/setups/src/Providers/SetupsServiceProvider.php plugins/setups/src/Console/Commands/SetupCommand.php plugins/setups/template/components.json.template
```

Expected: all four paths listed, no "No such file" errors.

---

### Task 6: Verify end-to-end behavior

**Files:** none (verification only)

- [ ] **Step 1: Regenerate the autoloader and confirm the new provider is discovered**

```bash
composer dump-autoload
```

Expected: no errors. Then confirm Laravel's package manifest includes the new provider:

```bash
php artisan package:discover --ansi
```

Expected output includes a line for `lumina/setups` (or no errors — Laravel merges local `plugins/*` composer packages the same way as any other package per the existing plugin pattern).

- [ ] **Step 2: Confirm `lumina:setup` is registered and runs identically**

```bash
php artisan list | grep lumina:setup
```

Expected: `lumina:setup  Publish Lumina CMS templates, sync configs, and clean .tsx files from resources/js`

```bash
php artisan lumina:setup --dry-run
```

Expected: same three-step dry-run output as before the move (publish templates / sync config / remove .tsx), no errors, ending with "Dry run completed. No files were modified."

- [ ] **Step 3: Confirm the production/absent-directory guard actually disables the plugin**

Simulate the "directory absent" case without actually deleting anything by temporarily renaming it:

```bash
mv plugins/setups plugins/setups.bak
php artisan list | grep lumina:setup
```

Expected: no output (command not registered).

```bash
mv plugins/setups.bak plugins/setups
php artisan list | grep lumina:setup
```

Expected: `lumina:setup` line reappears, confirming the guard is directory-existence-driven and reversible.

- [ ] **Step 4: Confirm no stray references remain in `plugins/cms`**

```bash
grep -rn "SetupCommand\|plugins/cms/template" plugins/cms
```

Expected: no output.

---

## Deviation found during Task 6 verification

The original spec/plan assumed Composer would auto-discover `plugins/setups` the same way it does other plugins, since root `composer.json` lists `plugins/*` as a path repository. That assumption was wrong: each plugin is also individually listed in root `composer.json`'s `require` block (e.g. `"lumina/posts": "@dev"`) — path repositories still need an explicit `require` entry to be installed, and Laravel's package auto-discovery (`extra.laravel.providers`) only fires for installed packages. Adding `"lumina/setups": "@dev"` to `require` would have broken `composer install` on every prod deploy, since the required path package wouldn't exist there.

Fix applied instead (not in the original plan, done live during Task 6):
- Added `"Lumina\\Setups\\": "plugins/setups/src/"` directly to root `composer.json`'s `autoload.psr-4` (safe even when the directory is absent — Composer just finds no classes there, no error, unlike a missing `require`d path package).
- Registered `SetupsServiceProvider` manually in `bootstrap/providers.php`, gated by `is_dir(__DIR__.'/../plugins/setups') && class_exists(...)`, instead of relying on package auto-discovery.
- `plugins/setups/composer.json` and its `extra.laravel.providers` entry are now inert/unused metadata (kept for consistency with other plugins, but not read by anything) — the plugin is not, and must never become, a `require`d Composer package.
- `plugins/core/configs/plugins.php`'s `setups` guard (Task 5) is still in place and still correct — it gates `RegistersPlugins::register()`'s config-merge behavior — but it's no longer the *only* thing preventing the provider from loading; `bootstrap/providers.php`'s guard is what actually keeps `SetupsServiceProvider` off the provider list in the first place.

## Post-plan note

This project is not currently a git repository, so this plan performed no commits — only file moves and edits via plain shell commands. `.gitignore` was still updated to list `/plugins/setups`, so whenever this project is put under version control (`git init`) or already-existing history is used elsewhere, the plugin's files won't be picked up. Until then, `plugins/setups/` is simply a local, uncommitted directory that only exists on whichever machine ran this plan; sharing it with teammates, if ever needed, is a manual out-of-band step not covered by this plan.
