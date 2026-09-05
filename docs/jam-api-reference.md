# JAM API Reference (Generic Item API)

Tài liệu tham chiếu API cho `plugins/core`'s generic `/items/{resource}` endpoint — dùng để xây dựng thư viện FE (API client) gọi vào Lumina CMS. Mọi model có `use QueryBuilder` đều tự động expose qua các route dưới đây theo convention resource name (`post-categories` → `PostCategory`).

## Base URL & Auth

Hai nhóm route song song, cùng shape, khác middleware:

| Prefix | Middleware | Dùng cho |
|---|---|---|
| `/items/{resource}` | `web`, `auth` | Admin dashboard (session cookie) |
| `/api/items/{resource}` | `api` | Thư viện FE / client ngoài (nên dùng nhóm này) |

Response luôn là JSON (`shouldRenderJsonWhen` bật cho mọi request `api/*` hoặc `Accept: application/json`).

## Endpoints

| Method | Path | Trait/Method | Mô tả |
|---|---|---|---|
| GET | `/api/items/{resource}` | `index` | List + query (filter/sort/search/pagination) |
| POST | `/api/items/{resource}` | `store` | Tạo record |
| GET | `/api/items/{resource}/{idOrSlug}` | `show` | Lấy 1 record theo id hoặc slug |
| PUT | `/api/items/{resource}/{id}` | `update` | Cập nhật record |
| DELETE | `/api/items/{resource}/{id}` | `destroy` | Soft delete (204) |
| GET | `/api/items/{resource}/export` | `export` | Export JSON/CSV/XLSX |
| POST | `/api/items/{resource}/import` | `import` | Import JSON file |
| POST | `/api/items/{resource}/dashboards` | `dashboards` | Batch aggregate cho dashboard cards |
| GET | `/api/items/{resource}/options/{field}` | `options` | Data cho select/combobox |
| GET | `/api/items/{resource}/{id}/frontend-url` | `frontendUrl` | URL public của record |
| GET | `/api/items/{resource}/{id}/index-status` | `indexStatus` | Trạng thái index Google Search Console |
| POST | `/api/items/{resource}/{id}/restore` | `restore` | Khôi phục record đã xoá mềm |
| DELETE | `/api/items/{resource}/{id}/force` | `forceDestroy` | Xoá vĩnh viễn |

`GET /api/items/{resource}?trashed=1` → dùng chung `index`, trả về các record đã soft-delete thay vì record đang hoạt động (yêu cầu model dùng `SoftDeletes`, nếu không sẽ 404).

---

## Query Params (`GET /api/items/{resource}`, `options`, `export`)

Áp dụng qua `QueryBuilder::scopeApplyQuery()`. Tất cả param optional.

### `filter[field][operator]=value`

```
GET /api/items/products?filter[status][_eq]=active&filter[price][_gte]=100000
```

| Operator | SQL tương đương | Ghi chú |
|---|---|---|
| `_eq` | `=` | |
| `_neq` | `!=` | |
| `_gt` / `_gte` | `>` / `>=` | |
| `_lt` / `_lte` | `<` / `<=` | |
| `_in` | `IN (...)` | value là array hoặc CSV |
| `_nin` | `NOT IN (...)` | |
| `_like` | `LIKE %value%` | |
| `_nlike` | `NOT LIKE %value%` | |
| `_startswith` | `LIKE value%` | |
| `_endswith` | `LIKE %value` | |
| `_is_null` / `_is_not_null` | `IS NULL` / `IS NOT NULL` | |
| `_is_empty` / `_is_not_empty` | `= ''` / `!= ''` | |
| `checked` / `unchecked` | `= true` / `= false` | boolean field |
| `has` / `does_not_have` | `whereHas` / `whereDoesntHave` | field là tên relation |

### `search` + `searchFields[]`

```
GET /api/items/products?search=ao+thun&searchFields[]=name&searchFields[]=slug
```

- Không truyền `searchFields[]` → server tự dùng cột `name`/`title`/`slug` nào tồn tại trên bảng (`defaultSearchFields`).
- Field trong `$translatable` được match theo locale hiện tại (`name->vi`) thay vì so trực tiếp JSON blob.
- Field trong `$fulltextSearchable` dùng `MATCH ... AGAINST` (cần fulltext index) thay vì `LIKE`.

### `sorts[]=column:direction`

```
GET /api/items/products?sorts[]=created_at:desc&sorts[]=name:asc
```

Multi-column: áp dụng theo thứ tự khai báo. `direction` mặc định `asc` nếu bỏ trống.

### `fields[]` — chọn cột / include relation

```
GET /api/items/products?fields[]=id&fields[]=name&fields[]=category.name&fields[]=category.*
```

- Cột thường: chọn đúng cột đó (`addSelect`).
- `relation.field`: eager-load relation, chỉ select field đó.
- `relation.*`: eager-load relation với đầy đủ field.
- `BelongsTo`/`MorphTo` tự động include thêm foreign key / morph type cần thiết dù không khai báo.
- Field không phải cột thật (vd accessor như `Order::total`) tự bị bỏ khỏi SQL select, Eloquent vẫn trả về qua `$appends`.

### `field->locale` — resolve translatable field theo locale cụ thể

```
GET /api/items/products?fields[]=name->en&locale=vi
```

- `?locale=` áp cho toàn bộ response; `field->locale` override riêng cho 1 field.
- `field->toRaw` trả nguyên object `{"vi": "...", "en": "..."}` thay vì resolve.
- Dot-path field (`metadata.title`) cũng theo cú pháp này nếu field đó nằm trong `$translatable`.

### `limit`, `page`, `paginate`

```
GET /api/items/products?limit=20&page=2
GET /api/items/products?limit=-1          # lấy tất cả, trả về Collection (không paginate)
GET /api/items/products?paginate=false    # tương tự, bỏ qua pagination
```

- Mặc định `limit=15`, `page=1`, có pagination.

---

## Response Shapes

### List (`index`) — có pagination

```json
{
  "data": [ { "id": 1, "name": "..." } ],
  "meta": { "total": 42, "page": 1, "limit": 15 }
}
```

### List — không pagination (`limit=-1` hoặc `paginate=false`)

```json
{
  "data": [ { "id": 1, "name": "..." } ],
  "meta": { "total": 3 }
}
```

### Single record (`show`, `store`, `update`)

```json
{ "data": { "id": 1, "name": "..." }, "message": "Đã tạo mới." }
```

`message` chỉ có ở `store`/`update`.

### `destroy` / `forceDestroy`

`204 No Content`, body rỗng.

### `options`

Flat list:
```json
{ "data": [ { "value": "1", "label": "Áo thun" } ] }
```

Tree (khi related model có cột `parent_id`, vd categories):
```json
{
  "data": [
    { "value": "1", "label": "Thời trang", "children": [
      { "value": "2", "label": "Áo" }
    ]}
  ]
}
```

Danh sách tĩnh (từ `{FIELD}_LIST` constant hoặc backed enum): `{ "value": ..., "label": ... }` hoặc kèm thêm field hiển thị (`text`, `background` — badge màu).

### `dashboards`

Request:
```json
POST /api/items/orders/dashboards
{
  "cards": [
    { "field": "id", "aggregate": "count", "filters": { "status": { "_eq": "pending" } } },
    { "field": "total", "aggregate": "sum", "filters": {} }
  ]
}
```

Response: `{ "data": [12, 4500000] }` — theo đúng thứ tự `cards` gửi lên.

### `frontendUrl`

```json
{ "data": { "url": "https://frontend.example.com/san-pham/ao-thun" } }
```

404 nếu model không khai báo hằng `FRONTEND_URL`.

### Lỗi

- `404` — resource không resolve được, hoặc model không hỗ trợ trash/frontend-url.
- `422` — validation fail (khi model có `rules()` static method), Laravel default shape: `{ "message": "...", "errors": { "field": ["..."] } }`.
- `501` — `index-status` khi Google Search Console chưa cấu hình.

---

## Ghi chú cho thư viện FE

1. **Luôn dùng prefix `/api/items/...`** thay vì `/items/...` (route đó dành cho session-based admin UI).
2. **`?locale=`** vừa quyết định field nào được đọc (translatable resolve theo locale này), vừa quyết định field nào được ghi khi `store`/`update` gửi plain string cho field translatable.
3. **Pagination meta** khác nhau tuỳ có paginate hay không — client nên check `meta.page`/`meta.limit` tồn tại hay không thay vì giả định luôn có.
4. **`options()`** là nguồn dữ liệu chuẩn cho mọi select/combobox — không nên tự query resource khác, vì nó tự resolve theo 3 nguồn ưu tiên (`{FIELD}_LIST` constant → backed enum → relation) và tự xử lý tree cho resource có `parent_id`.
5. **`export`** hỗ trợ `?format=json|csv|xlsx`, `?ids[]=` (export theo id đã chọn ở FE, ưu tiên hơn filter) hoặc `?fields[]=` để giới hạn cột xuất.
6. **`import`** chỉ nhận JSON array of objects qua multipart field `file`; không validate qua model `rules()` — dùng cho bulk restore/seed nội bộ, không phải public-facing form.
