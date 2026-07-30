# JAM API Core: QueryBuilder + Generic ItemController + Crud/Trash/ImportExport

Date: 2026-07-23
Plugin: `plugins/core`

## Purpose

A generic, model-agnostic API layer under `/api/items/{resource}` (per the
JAM API Query Guide), plus reusable controller-level concerns for
create/read/update/delete, soft-delete trash handling, and JSON
import/export. Built incrementally: this pass covers QueryBuilder, the
generic ItemController, Crud, and Trash (with filter/sort/fields/pagination
support). Multilingual fields, attachments, revisions, and caching are
explicitly deferred to a later pass.

## In scope this pass

1. `QueryBuilder` trait (Model-level) — `applyQuery(array $params)`
2. Generic `ItemController` + `HasQueries` trait (Controller-level) driving
   `/api/items/{resource}`
3. `HasCrud` trait — index/create/edit/store/update/destroy/duplicate
4. `HasTrash` trait — trashIndex (with filter/sort/fields/pagination),
   trashRestore (duplicate-from-trash), trashForceDelete
5. `HasImportExport` trait — JSON export/import
6. `ClonesModelData` shared helper trait

## Out of scope this pass (deferred)

- Multilingual field access (`title->vi`, `->toRaw`, `?locale=`)
- Attachment field types (`SingleAttachment`/`MultipleAttachments`)
- `HasRevisions`
- `Cacheable` / `getCachedData()`
- `HasRelations`, `HasValidation` (relation IDs are accepted as plain
  foreign-key arrays for now, validation stays in per-resource FormRequests
  if any are added later — no generic validation trait yet)

## Resource → Model resolution

Convention-based: `posts` → `Post`, `post-categories` → `PostCategory`,
`consultation-requests` → `ConsultationRequest`. The resource segment is
kebab/plural, converted via `Str::studly(Str::singular($resource))`.

Resolution searches configured model namespaces (each plugin registers its
model namespace, e.g. `Lumina\Cms\Models`, in
`config/core.php:model_namespaces`) for a class of that name.

**Safety guard:** a resolved class is only usable as a resource if it `use`s
the `QueryBuilder` trait (checked via
`in_array(QueryBuilder::class, class_uses_recursive($class))`). Any other
resolved class, or a resource with no matching class, returns 404. This
keeps convention-based resolution but prevents accidentally exposing models
that weren't meant to be API resources (e.g. `Admin`, `Passkey`).

## 1. QueryBuilder (Model trait)

```php
trait QueryBuilder
{
    public function scopeApplyQuery(Builder $query, array $params): Builder
    {
        // fields[] selection (including nested relation.field / relation.*)
        // filter[field][_operator]=value (operators listed below)
        // sort=field / sort=-field (multi-column: comma-separated)
        // pagination: page/limit, limit=-1 = all, paginate=false = no wrapper
    }
}
```

Supported filter operators: `_eq _neq _gt _gte _lt _lte _in _nin _like
_nlike _startswith _endswith _is_null _is_not_null _is_empty
_is_not_empty checked unchecked has does_not_have`.

`fields[]` supports dot-notation for eager-loaded relations
(`category.name`, `category.*`); the trait resolves these into `select()` +
`with()` calls.

Response shape: `{ "data": [...], "meta": { "total": N, "page": N, "limit": N } }`
when paginated; `{ "data": [...], "meta": { "total": N } }` when
`paginate=false` or `limit=-1`.

## 2. Generic ItemController + HasQueries

```php
class ItemController extends Controller
{
    use HasQueries, HasCrud, HasTrash, HasImportExport;
}
```

Route: `Route::apiResource('items/{resource}', ItemController::class)` plus
trash/export/import routes, all going through the same controller. The
controller resolves `$this->model` per-request from the `{resource}` route
segment (via the resolver above) rather than being hardcoded per-plugin —
this is the one controller that differs from the original per-plugin
`AdminController`-style design.

`HasQueries::query()` builds `$model::query()->applyQuery($request->all())`
and is used by `index`, `export`, and `trashIndex`.

## 3. HasCrud

Unchanged from prior design, but `index` now delegates to
`HasQueries::query()` instead of manual `when()` chains, so filter/sort/
fields/pagination all work automatically:

- `index` → JSON (this controller is API-only; Inertia pages, if any,
  stay in per-plugin controllers that don't use `ItemController`)
- `store`/`update` → validated via request body fields as-is (no generic
  validation trait this pass)
- `destroy($id)` → soft delete if model has `SoftDeletes`, else hard delete
- `duplicate($id)` → clone active record via `ClonesModelData`

## 4. HasTrash

- `trashIndex(Request $request)` → `$model::onlyTrashed()->applyQuery($request->all())`,
  same filter/sort/fields/pagination support as `index`
- `trashRestore($id)` → clone trashed record into a new active record via
  `ClonesModelData`; original trashed record is left untouched
- `trashForceDelete($id)` → permanently deletes the trashed record
- `trashForceDeleteBulk(Request $request)` → optional bulk permanent delete

Routes:
```
GET    /api/items/{resource}/trash
POST   /api/items/{resource}/trash/{id}/restore
DELETE /api/items/{resource}/trash/{id}
```

## 5. HasImportExport

- `export(Request $request)` → same `applyQuery` filters as `index`,
  streamed as a JSON file download
- `import(Request $request)` → uploaded `.json` file, array of objects,
  `$model::create()` per row, returns per-row success/failure counts

## ClonesModelData (shared helper trait)

```php
protected function cloneModelData(Model $model): Model
{
    $data = $model->getAttributes();
    unset($data[$model->getKeyName()], $data['created_at'], $data['updated_at'], $data['deleted_at']);
    return new ($model::class)($data);
}
```

## Testing

- Feature tests against a throwaway test model (or an existing simple
  model) covering: filter operators, sort, fields selection, pagination
  modes, resource resolution + 404 guard, crud + duplicate, trash index/
  restore/forceDelete, import/export round-trip.
