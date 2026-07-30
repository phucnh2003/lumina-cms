# AGENT.md — Lumina CMS

> **Mọi AI agent** (Antigravity, Claude, Cursor, Copilot…) đọc file này đầu tiên khi làm việc
> trong repo này. Đây là nguồn sự thật duy nhất về project — không cần hỏi lại.

---

## 1. SELF-SETUP: Đọc trước khi làm bất cứ việc gì

### 1.1 Hiểu project trong 30 giây

|                 |                                                                      |
| --------------- | -------------------------------------------------------------------- |
| **Loại dự án**  | Plugin-based CMS — Laravel backend + React frontend                  |
| **Backend**     | Laravel 13 · PHP 8.3 · Inertia.js · Laravel Sanctum                  |
| **Frontend**    | React 19 · TypeScript strict · TailwindCSS v4 · shadcn/ui · Radix UI |
| **Database**    | MySQL (production) · SQLite (testing)                                |
| **Testing**     | PestPHP (backend) · ESLint + tsc (frontend)                          |
| **Package mgr** | Composer (PHP) · pnpm (JS)                                           |
| **Monorepo**    | 13 plugins trong `plugins/`, mỗi plugin = 1 Composer package         |

### 1.2 Files quan trọng cần đọc theo task

| Task liên quan đến                     | Đọc spec này trước                                                           |
| -------------------------------------- | ---------------------------------------------------------------------------- |
| Customer auth / login / register       | `docs/superpowers/specs/2026-07-28-customer-accounts-design.md`              |
| Google / Facebook OAuth                | `docs/superpowers/specs/2026-07-28-customer-accounts-design.md`              |
| Cart / Order / Checkout                | `docs/superpowers/specs/2026-07-23-cart-purchasable-design.md`               |
| Product / E-commerce                   | `docs/superpowers/specs/2026-07-23-ecommerce-product-cart-design.md`         |
| JAM API / CRUD / Trash / Import/Export | `docs/superpowers/specs/2026-07-23-crud-trash-importexport-traits-design.md` |
| Dashboard UI                           | `docs/superpowers/specs/2026-07-23-dashboard-page-design.md`                 |
| OTP / One-Time Password                | `docs/superpowers/specs/2026-07-28-otp-system-design.md`                     |
| Shipping / Phương thức vận chuyển      | `docs/superpowers/specs/2026-07-28-shipping-system-design.md`                |
| Coupon / Discount codes (Cupont)       | `docs/superpowers/specs/2026-07-28-coupon-system-design.md`                  |
| Implementation plan chi tiết           | `docs/superpowers/plans/`                                                    |

### 1.3 Lệnh cần biết ngay

```bash
composer dev          # Start Laravel + Vite cùng lúc (dùng cái này khi dev)
composer lint         # Fix PHP code style (Pint)
composer types:check  # PHPStan static analysis level 8
php artisan test      # Chạy toàn bộ test suite (PestPHP)
composer ci:check     # Full CI: lint + types + format + test
npm run lint:check    # ESLint check
npm run types:check   # TypeScript check
```

---

## 2. ARCHITECTURE

### 2.1 Plugin Monorepo

Mọi business logic nằm trong `plugins/`. Không đặt logic vào `app/` nếu nó thuộc về một plugin cụ thể.

```
lumina-cms/
├── app/
│   ├── Models/User.php          ← Customer identity (App\Models\User)
│   └── Providers/AppServiceProvider.php  ← Morph map đặt ở đây
├── plugins/
│   ├── core/                    ← JAM API, CRUD/Trash/Import/Export traits
│   ├── cms/                     ← Admin model, dashboard, media, auth
│   ├── taxonomies/              ← Polymorphic taxonomy
│   ├── posts/                   ← Blog posts
│   ├── e-commerce/              ← Product, Cart, Order, Checkout
│   ├── customer/                ← Customer auth (dùng App\Models\User)
│   ├── social/                  ← Google/Facebook OAuth (standalone)
│   ├── payment/                 ← Payment integrations
│   ├── notification/            ← Notifications
│   ├── seo/                     ← SEO traits
│   ├── sliders/                 ← Sliders
│   ├── ratings/                 ← Ratings
│   ├── redirects/               ← URL redirects
│   └── locations/               ← Locations & Address Management
├── resources/js/                ← React/TypeScript frontend
├── docs/superpowers/            ← Specs & plans
│   ├── specs/                   ← Technical design decisions
│   └── plans/                   ← Step-by-step implementation plans
└── .gemini/GEMINI.md            ← Antigravity auto-load (trỏ về file này)
    .claude/CLAUDE.md            ← Claude auto-load (trỏ về file này)
```

### 2.2 Plugin Structure (chuẩn cho mọi plugin)

```
plugins/<name>/
├── composer.json                ← "name": "lumina/<name>", PSR-4 namespace
├── configs/                     ← Published to root config/
│   └── <name>.php
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── <name>.php               ← Loaded by ServiceProvider
└── src/
    ├── Controllers/
    ├── Models/
    ├── Providers/
    │   └── <Name>ServiceProvider.php   ← Entry point
    ├── Requests/                ← FormRequest classes
    ├── Services/                ← Business logic
    └── Traits/
```

### 2.3 Model Inventory (thực tế trong codebase)

| Model                                                 | Plugin     | Namespace                              |
| ----------------------------------------------------- | ---------- | -------------------------------------- |
| `User`                                                | root app   | `App\Models\User`                      |
| `Admin`                                               | cms        | `Lumina\Cms\Models\Admin`              |
| `File`, `Setting`                                     | cms        | `Lumina\Cms\Models\`                   |
| `Post`                                                | posts      | `Lumina\Posts\Models\Post`             |
| `Product`, `ProductVariant`, `Option`, `OptionValue`  | e-commerce | `Lumina\Ecommerce\Models\`             |
| `Cart`, `CartItem`, `Order`, `OrderItem`, `Category`  | e-commerce | `Lumina\Ecommerce\Models\`             |
| `Taxonomy`, `PostCategory`, `ProductCategory`, `Menu` | taxonomies | `Lumina\Taxonomies\Models\`            |
| `CustomerGroup`                                       | customer   | `Lumina\Customer\Models\CustomerGroup` |
| `SocialAccount`                                       | social     | `Lumina\Social\Models\SocialAccount`   |
| `Transaction`                                         | payment    | `Lumina\Payment\Models\Transaction`    |
| `Rating`                                              | ratings    | `Lumina\Ratings\Models\Rating`         |
| `Redirect`                                            | redirects  | `Lumina\Redirects\Models\Redirect`     |
| `Location`, `Address`                                 | locations  | `Lumina\Locations\Models\`             |
| `Slider`                                              | sliders    | `Lumina\Sliders\Models\Slider`         |
| `Seo`                                                 | seo        | `Lumina\Seo\Models\Seo`                |
| `Passkey`                                             | core       | `Lumina\Core\Models\Passkey`           |

### 2.4 Traits Inventory (từ `lumina/core`)

| Trait              | Loại             | Dùng ở                    |
| ------------------ | ---------------- | ------------------------- |
| `QueryBuilder`     | Model trait      | Models cần JAM API expose |
| `HasCrud`          | Controller trait | `ItemController`          |
| `HasTrash`         | Controller trait | `ItemController`          |
| `HasImportExport`  | Controller trait | `ItemController`          |
| `HasQueries`       | Controller trait | `ItemController`          |
| `ClonesModelData`  | Shared helper    | `HasCrud`, `HasTrash`     |
| `HasRelations`     | Controller trait | `ItemController`          |
| `RegistersPlugins` | Provider trait   | ServiceProviders          |

| Trait               | Plugin     | Dùng ở                      |
| ------------------- | ---------- | --------------------------- |
| `HasTaxonomies`     | taxonomies | Các models cần category/tag |
| `HasSeo`            | seo        | Models cần SEO meta         |
| `HasSocialAccounts` | social     | `User`, `Admin`             |

### 2.5 Auth Architecture

```
Admin staff  →  admin guard  →  Lumina\Cms\Models\Admin   (session-based)
Customers    →  web guard + Sanctum  →  App\Models\User    (token-based)
```

**Morph map** (bắt buộc đặt trong `AppServiceProvider::boot()`):

```php
Relation::morphMap([
    'web'   => \App\Models\User::class,
    'admin' => \Lumina\Cms\Models\Admin::class,
]);
```

**Sanctum**: `App\Models\User` dùng `HasApiTokens`. Middleware `auth:sanctum` — không có guard `customer` riêng.

### 2.6 JAM API — Generic CRUD layer

Endpoint: `GET|POST|PUT|DELETE /api/items/{resource}`

- `posts` → `Lumina\Posts\Models\Post`
- `products` → `Lumina\Ecommerce\Models\Product`
- `customer-groups` → `Lumina\Customer\Models\CustomerGroup`
- v.v — convention: `kebab-plural` → `StudlySingular`

Model phải `use QueryBuilder` để được expose. Safety guard: model không có trait này → 404.

**Filter operators**: `_eq _neq _gt _gte _lt _lte _in _nin _like _nlike _startswith _endswith _is_null _is_not_null _is_empty _is_not_empty checked unchecked has does_not_have`

**Response shape**:

```json
{ "data": [...], "meta": { "total": 100, "page": 1, "limit": 20 } }
```

---

## 3. CODING STANDARDS

### 3.1 PHP — Non-negotiable

```php
<?php

declare(strict_types=1);  // ← LUÔN có ở đầu mỗi file PHP

namespace Lumina\<PluginName>\Controllers;
```

| Rule                          | Chi tiết                                             |
| ----------------------------- | ---------------------------------------------------- |
| **`declare(strict_types=1)`** | Đầu mọi PHP file, không ngoại lệ                     |
| **Namespace**                 | `Lumina\<PluginName>\...` cho plugin code            |
| **Code style**                | Laravel Pint (PSR-12). `composer lint` trước commit  |
| **Static analysis**           | PHPStan level 8. `composer types:check` trước commit |
| **Controllers**               | Slim — chỉ nhận request, gọi Service, trả response   |
| **Services**                  | Chứa toàn bộ business logic                          |
| **FormRequest**               | Dùng cho mọi validation phức tạp                     |
| **`final class`**             | Dùng cho Controllers và Services                     |
| **Constructor promotion**     | `private readonly MyService $service`                |

### 3.2 Plugin Isolation — Quy tắc quan trọng nhất

Plugin **KHÔNG** được import class từ plugin khác trực tiếp:

```php
// ❌ SAI — tạo hard dependency
use Lumina\Customer\Models\Customer;

// ✅ ĐÚNG — dùng morph map
use Illuminate\Database\Eloquent\Relations\Relation;
$modelClass = Relation::getMorphedModel($morphAlias);

// ✅ ĐÚNG — optional dependency check
if (method_exists($user, 'socialAccounts')) {
    $accounts = $user->socialAccounts()->get();
}

// ✅ ĐÚNG — dùng interface
public function __construct(private readonly PurchasableInterface $item) {}
```

**Plugin** `lumina/social` **không phụ thuộc vào** `lumina/customer`. Cả hai standalone.

### 3.3 Controller Pattern

```php
<?php

declare(strict_types=1);

namespace Lumina\Customer\Controllers;

use Illuminate\Http\JsonResponse;
use Lumina\Customer\Requests\RegisterRequest;
use Lumina\Customer\Services\CustomerAuthService;

final class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $service
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->service->register($request->validated());
        return response()->json($result, 201);
    }
}
```

### 3.4 Service Pattern

```php
<?php

declare(strict_types=1);

namespace Lumina\Customer\Services;

use App\Models\User;

final class CustomerAuthService
{
    public function register(array $data): array
    {
        $user = User::create($data);
        $token = $user->createToken('api')->plainTextToken;

        return ['token' => $token, 'user' => $user];
    }
}
```

### 3.5 TypeScript / React

| Rule              |                                                         |
| ----------------- | ------------------------------------------------------- |
| **No `any`**      | Dùng proper types hoặc `unknown`                        |
| **Strict mode**   | `"strict": true` trong tsconfig                         |
| **Pages**         | `resources/js/Pages/` — Inertia page components         |
| **Components**    | `resources/js/Components/`                              |
| **UI primitives** | shadcn/ui + Radix UI — không tự build                   |
| **Forms**         | React Hook Form + Zod                                   |
| **Tables**        | TanStack Table                                          |
| **i18n**          | i18next — không hardcode text                           |
| **URLs**          | `route()` từ Ziggy — không hardcode URL string          |
| **Styling**       | TailwindCSS classes — không dùng `style={{}}`           |
| **Naming**        | Components: `PascalCase.tsx` · Hooks: `useCamelCase.ts` |

```tsx
// resources/js/Pages/MyPage.tsx
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface Props {
    items: Item[];
}

export default function MyPage({ items }: Props) {
    const { t } = useTranslation();
    return (
        <>
            <Head title={t('my_page.title')} />
            {/* ... */}
        </>
    );
}
```

### 3.6 Testing (PestPHP)

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('registers a customer and returns a token', function (): void {
    $response = $this->postJson('/api/customer/register', [
        'name'     => 'Test User',
        'email'    => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertCreated()
             ->assertJsonStructure(['token']);

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});
```

| Rule               |                                                |
| ------------------ | ---------------------------------------------- |
| **Pest DSL**       | `it()`, `test()`, `describe()`                 |
| **Feature > Unit** | Test qua HTTP requests ưu tiên                 |
| **Factories**      | Không hardcode fixture data                    |
| **Isolation**      | `RefreshDatabase` hoặc `LazilyRefreshDatabase` |
| **Coverage**       | ≥1 feature test cho mỗi API endpoint mới       |

### 3.7 Clean Code & Readability

Mọi đoạn code viết ra phải tuân thủ nguyên tắc **Gọn gàng, dễ đọc, dễ hiểu**:

- **Tên biến, hàm, class phải rõ ràng**: Phản ánh đúng chức năng, không viết tắt khó hiểu.
- **Single Responsibility Principle (SRP)**: Mỗi hàm/class chỉ nên làm một việc duy nhất.
- **Tránh nested code (deep nesting)**: Dùng early return (guard clauses) để giảm bớt các tầng `if/else`.
- **Comment khi cần thiết**: Comment để giải thích _tại sao_ (why), không phải _làm gì_ (what). Code tự nó phải giải thích được logic "what".
- **Hàm/Phương thức ngắn gọn**: Nếu hàm quá dài, hãy refactor tách thành các private helper methods với tên mô tả rõ hành động.

---

## 4. KEY DECISIONS (đã confirm — không thảo luận lại)

| Decision                                                         | Lý do                                                        |
| ---------------------------------------------------------------- | ------------------------------------------------------------ |
| `App\Models\User` cho customers, không có model `Customer` riêng | Tránh duplicate identity table, `web` guard đã có sẵn        |
| Sanctum token auth cho customers, không session                  | Stateless API-friendly                                       |
| Không có guard `customer` riêng                                  | Sanctum's generic guard resolve được từ token                |
| Morph aliases `'web'`, `'admin'` thay vì FQCN                    | Decoupled, portable                                          |
| `lumina/social` standalone (không depend `lumina/customer`)      | Tái sử dụng cho `Admin` social login sau này                 |
| `CustomerGroup` là subclass của `Taxonomy`                       | Tận dụng sẵn JAM API + taxonomy infrastructure               |
| `Cart.total` computed, không stored                              | Tránh drift giữa items và stored total                       |
| Money lưu dạng integer (smallest unit: xu/đồng)                  | Không dùng float cho tiền                                    |
| `CartItem.metadata` JSON, không typed                            | Variant selection là product-specific, out of scope validate |
| Guest checkout: `customer_id` nullable                           | Guest flow phải luôn hoạt động                               |

---

## 5. CONSTRAINTS — KHÔNG ĐƯỢC VI PHẠM

### ❌ Cấm tuyệt đối

- Hardcode `User::class` hoặc `Admin::class` trong plugin code
- Plugin import trực tiếp model/class từ plugin khác
- Dùng `any` trong TypeScript
- Hardcode text UI (dùng i18next)
- Hardcode URL string (dùng `route()`)
- Dùng `style={{}}` inline trong React
- Model `Customer` riêng biệt (dùng `App\Models\User`)
- Guard `customer` riêng (dùng Sanctum's `auth:sanctum`)
- Float/Decimal cho giá tiền (dùng integer — smallest currency unit)
- Commit mà không chạy `composer lint` và `composer types:check`

### ✅ Bắt buộc

- `declare(strict_types=1)` ở đầu mọi PHP file
- Guest checkout phải luôn hoạt động (`customer_id` nullable)
- Model muốn expose qua JAM API phải `use QueryBuilder`
- Mỗi API endpoint mới phải có ≥1 feature test
- PHP ≥ 8.3, Laravel ≥ 13.x
- Plugin mới phải có `ServiceProvider` đăng ký routes/migrations/configs
- Morph map được đặt trong `AppServiceProvider`, không trong plugin

---

## 6. GIT CONVENTIONS

```
feat: add customer registration endpoint
fix: resolve cart token cookie expiry
chore: update composer dependencies
docs: add customer auth spec
```

- Branch: `feature/<slug>` · `fix/<slug>` · `chore/<slug>`
- Mỗi PR phải pass: `composer ci:check`

---

## 7. THÊM FEATURE MỚI — CHECKLIST

```
□ Đọc spec liên quan trong docs/superpowers/specs/
□ Đọc plan trong docs/superpowers/plans/ (nếu có)
□ Xác định plugin đúng để đặt code
□ Tạo ServiceProvider nếu là plugin mới
□ Đặt business logic trong Service, không trong Controller
□ Dùng FormRequest cho validation
□ Viết ≥1 feature test (PestPHP)
□ composer lint && composer types:check
□ npm run lint:check && npm run types:check (nếu có frontend)
□ Cập nhật spec/plan nếu design thay đổi
□ Luôn luôn cập nhật docs

```
