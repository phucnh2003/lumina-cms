# Dashboard Page Design

## Goal
Replace the placeholder default page (`plugins/cms/resources/js/pages/dashboard.tsx`) with a real
dashboard showing overview stat cards, backed by real data from the backend.

## Backend

- Add `Lumina\Cms\Controllers\DashboardController` with an `index()` method.
  - Returns `Inertia::render('dashboard', ['stats' => [...]])`.
  - Stats computed:
    - `admins`: `Admin::count()`
    - `roles`: `Spatie\Permission\Models\Role::count()`
    - `files`: `File::count()`
- Register route in `plugins/cms/routes/cms.php`, inside the existing `auth`+`web` middleware group,
  **before** the catch-all `{collection}/{slug?}` route:
  ```php
  Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
  ```
- No "notifications" stat: the notifications page currently renders static mock data with no
  backing DB table, so there is nothing real to count.

## Frontend

`plugins/cms/resources/js/pages/dashboard.tsx`:

- Props: `{ stats: { admins: number; roles: number; files: number } }`.
- Layout: heading ("Dashboard" + Vietnamese welcome subtext, kept from current copy) followed by a
  responsive grid (`grid-cols-1 sm:grid-cols-3 gap-4`) of 3 `Card` components from
  `@cms/components/ui/card`.
- Each stat card:
  - Icon (lucide-react): `Users` for Admin, `ShieldCheck` for Vai trò, `Files` for File.
  - Big number (`stats.X`), label underneath (e.g. "Admin", "Vai trò", "File").
  - Whole card wrapped in an Inertia `<Link>` to the relevant index page (`/admins`, `/roles`,
    `/file-manager`), with hover style consistent with existing clickable cards/rows in the app
    (subtle border/shadow change on hover).
- No client-side data fetching; stats arrive as Inertia page props.

## Out of scope
- No charts, no recent-activity list, no notification counts (no real data source yet).
