# PHP / Laravel Rules

> Path filter: `plugins/**/*.php`, `app/**/*.php`, `database/**/*.php`, `tests/**/*.php`

## Bắt buộc mọi PHP file

```php
<?php

declare(strict_types=1);
```

## Namespace convention

- Root app: `App\...`
- Plugin code: `Lumina\<PluginName>\...`  
  - `Lumina\Cms\...` · `Lumina\Core\...` · `Lumina\Ecommerce\...`
  - `Lumina\Customer\...` · `Lumina\Social\...` · `Lumina\Taxonomies\...`
  - `Lumina\Posts\...` · `Lumina\Payment\...` · `Lumina\Seo\...`

## Controller — phải slim

```php
final class MyController extends Controller
{
    public function __construct(
        private readonly MyService $service
    ) {}

    public function store(MyRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->create($request->validated()),
            201
        );
    }
}
```

**Không** đặt logic nghiệp vụ trong Controller. Controller chỉ: nhận input → gọi Service → trả response.

## Service — chứa business logic

```php
final class MyService
{
    public function create(array $data): Model
    {
        // business logic ở đây
        return MyModel::create($data);
    }
}
```

## FormRequest — dùng cho mọi validation

```php
final class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

## Plugin isolation

```php
// ❌ SAI — hard dependency
use Lumina\Customer\Models\User;

// ✅ ĐÚNG — morph map
$modelClass = Relation::getMorphedModel($morphAlias);

// ✅ ĐÚNG — optional check
if (method_exists($model, 'socialAccounts')) {
    $model->socialAccounts()->get();
}
```

## Migrations

- Tên file: `YYYY_MM_DD_HHMMSS_create_<table>_table.php`
- Đặt trong: `plugins/<name>/database/migrations/`
- **Không** alter bảng của plugin khác (ngoại lệ: `e-commerce` được add columns vào `users`)

## Eloquent conventions

- Money/price: **integer** (smallest currency unit, VD: 10000 = 10.000 VNĐ) — không dùng float
- Polymorphic: dùng morph alias (`'product'`) không dùng FQCN
- Soft delete: `use SoftDeletes` nếu cần trash
- Tránh `$model->update([])` với empty array — Eloquent sẽ update `updated_at`

## Lệnh trước khi commit

```bash
composer lint          # Fix code style
composer types:check   # PHPStan level 8
```
