# Lumina CMS

A Laravel + Inertia (React/TypeScript) admin CMS built on the `lumina/*` plugin ecosystem (CMS core, e-commerce, posts, customers, shipping, coupons, and more). Each plugin lives under `plugins/` and is loaded as a local Composer path package.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- SQLite (default) or another database supported by Laravel

## Setup

Clone the repository together with its submodules (the `plugins/` directory is a separate git repo):

```bash
git clone --recurse-submodules <repo-url>
cd lumina-cms
```

If you already cloned without `--recurse-submodules`:

```bash
git submodule update --init --recursive
```

Install dependencies and bootstrap the app in one step:

```bash
composer setup
```

This runs, in order:

1. `composer install`
2. Copies `.env.example` to `.env` (if missing)
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `npm install`
6. `npm run build`

## Development

Start the full dev stack (server, queue listener, logs, and Vite) with one command:

```bash
composer dev
```

Or run pieces individually:

```bash
php artisan serve   # backend
npm run dev          # Vite dev server (hot reload)
php artisan horizon  # queue worker (if using Horizon)
```

## Useful commands

| Command | Description |
| --- | --- |
| `composer lint` | Format PHP with Pint |
| `composer lint:check` | Check PHP formatting without writing |
| `composer test` | Run the PHP test suite |
| `npm run lint` | Lint & auto-fix TypeScript/React |
| `npm run types:check` | TypeScript type-check |
| `npm run format` | Format frontend code with Prettier |
| `npm run build` | Production frontend build |

## Project structure

- `app/`, `routes/`, `config/`, `database/` — the host Laravel application
- `plugins/` — the `lumina/*` plugin packages (git submodule, own repo), each with its own `src/`, `routes/`, and `resources/js/` (Inertia pages/components)
- `resources/` — host app's own Inertia entry, layouts, and shared frontend assets
- `docs/` — specs, plans, and reference docs for ongoing feature work

## Working with the `plugins/` submodule

Changes inside `plugins/` are committed to its own repository, not this one — this repo only tracks which commit of `plugins/` it points to.

```bash
cd plugins
git status
git add -A && git commit -m "..."
git push origin main
cd ..
git add plugins && git commit -m "chore: bump plugins submodule"
```
