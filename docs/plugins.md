# Plugin Docs — Lumina CMS

> Tài liệu kỹ thuật đầy đủ cho tất cả plugins trong `plugins/`.
> Cập nhật khi thêm model, route, hoặc trait mới.

---

## lumina/core

**Namespace**: `Lumina\Core\`
**Vai trò**: Nền tảng — API, generic CRUD/Trash/Import/Export, base traits

### API — `ItemController`

Endpoint duy nhất xử lý mọi resource:

```
GET    /api/items/{resource}              → index (filter/sort/paginate)
POST   /api/items/{resource}              → store
GET    /api/items/{resource}/{idOrSlug}   → show
PUT    /api/items/{resource}/{id}         → update
DELETE /api/items/{resource}/{id}         → destroy (soft delete nếu có SoftDeletes)
GET    /api/items/{resource}/export       → export JSON
POST   /api/items/{resource}/import       → import JSON
POST   /api/items/{resource}/{id}/restore → restore từ trash
DELETE /api/items/{resource}/{id}/force   → force delete
GET    /api/items/{resource}/{id}/frontend-url
GET    /api/items/{resource}/{id}/index-status
GET    /api/items/{resource}/options/{field}
POST   /api/items/{resource}/dashboards
```

**Resource resolution**: `posts` → `Post`, `post-categories` → `PostCategory`, `customer-groups` → `CustomerGroup`
(kebab-plural → StudlySingular, tìm trong `config/core.php:model_namespaces`)

**Safety guard**: Model phải `use QueryBuilder` — không có trait này → 404

**Middleware**: `web + auth` (Admin session)

### Traits

| Trait              | Loại       | Chức năng                                            |
| ------------------ | ---------- | ---------------------------------------------------- |
| `QueryBuilder`     | Model      | `scopeApplyQuery()` — filter/sort/fields/pagination  |
| `HasCrud`          | Controller | index, store, show, update, destroy, duplicate       |
| `HasTrash`         | Controller | trashIndex, trashRestore, trashForceDelete           |
| `HasImportExport`  | Controller | export JSON, import JSON                             |
| `HasQueries`       | Controller | Shared query builder cho index + export              |
| `HasRelations`     | Controller | Relation handling                                    |
| `ClonesModelData`  | Shared     | Clone model data (dùng bởi duplicate + trashRestore) |
| `RegistersPlugins` | Provider   | Helper cho ServiceProviders                          |

### QueryBuilder filter operators

`_eq _neq _gt _gte _lt _lte _in _nin _like _nlike _startswith _endswith _is_null _is_not_null _is_empty _is_not_empty checked unchecked has does_not_have`

### Models

| Model     | Table      | Notes                                                           |
| --------- | ---------- | --------------------------------------------------------------- |
| `Passkey` | `passkeys` | Extends `Laravel\Passkeys\Passkey`, polymorphic `passkeyable()` |

### Plugin enable/disable

Mỗi plugin có thể được bật/tắt qua `plugins/core/configs/plugins.php` (merge vào `config('plugins')` bởi `CoreServiceProvider::register()`):

```php
// plugins/core/configs/plugins.php
return [
    'customer' => ['enable' => true],
    'seo'      => ['enable' => false], // tắt plugin này
    // ...
];
```

- `RegistersPlugins::pluginEnabled($name)` đọc `config("plugins.{$name}.enable", true)` (mặc định `true` nếu không khai báo) — nếu `false`, plugin đó **không** load config/migrations/views/routes trong `register()`/`boot()`.
- `core` và `cms` không nằm trong config này và **luôn được coi là enabled** — 2 plugin nền tảng (auth, view resolution, base migrations), tắt sẽ phá vỡ toàn hệ thống.
- `Lumina\Core\Support\PluginRegistry`:
  - `all(): array` — map đầy đủ mọi plugin dưới `plugins/`, dạng `{ "customer": {"enable": true}, "seo": {"enable": false} }`. Đây là shape share qua Inertia prop `plugins` (`CmsServiceProvider`) — đọc ở frontend qua `usePage().props.plugins`.
  - `active(): array<string>` — chỉ tên các plugin đang bật, dạng mảng phẳng.

---

## lumina/cms

**Namespace**: `Lumina\Cms\`
**Vai trò**: Admin auth, dashboard, media manager, settings, roles/permissions

### Models

| Model     | Table      | Fields quan trọng                                                                    | Traits                                                                                                                |
| --------- | ---------- | ------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------- |
| `Admin`   | `admins`   | `name`, `email`, `password`, `system_admin`                                          | `HasApiTokens`, `HasFactory`, `Notifiable`, `PasskeyAuthenticatable`, `TwoFactorAuthenticatable`, `HasRoles` (spatie) |
| `File`    | `files`    | `name`, `type` (folder/file), `mime_type`, `size`, `path`, `parent_id`, `created_by` | Nested tree, permission system (folder-level per role)                                                                |
| `Setting` | `settings` | `key`, `value` (JSON)                                                                | Static helpers: `get()`, `set()`, `many()`                                                                            |

### Routes

```
# Auth (plugins/cms/routes/auth.php) — middleware: web
POST   /login
POST   /logout
GET    /two-factor-challenge
POST   /two-factor-challenge

# Settings (middleware: auth)
GET|PATCH settings/profile
GET|PATCH settings/general
GET|PATCH settings/smtp
GET|PATCH settings/search-console
GET       settings/security
PUT       settings/password
DELETE    settings/profile
GET       account/status
GET       .well-known/passkey-endpoints

# Files (middleware: auth)
GET|POST  api/files
GET|PUT|DELETE api/files/{id}
POST      api/files/folder
...

# Roles (middleware: auth)
GET|POST  roles
GET|PUT|DELETE roles/{role}
PUT       roles/{role}/permissions

# CMS (middleware: auth)
GET       /  (dashboard)
GET       {collection}/{slug?} (view pages)
```

### Key Classes

- `CmsServiceProvider` — registers admin guard, Fortify, listens Login/Logout events
- `FortifyServiceProvider` — configures 2FA, passkeys, email verification

---

## lumina/taxonomies

**Namespace**: `Lumina\Taxonomies\`
**Vai trò**: Polymorphic taxonomy system — categories, tags, menus, customer groups

### Models

| Model             | Type value         | Notes                                                          |
| ----------------- | ------------------ | -------------------------------------------------------------- |
| `Taxonomy`        | (base)             | `name`, `slug`, `type`, `parent_id`, `position`, `metadata`    |
| `PostCategory`    | `post_category`    | Subclass của `Taxonomy`, global scope `type = 'post_category'` |
| `ProductCategory` | `product_category` | Subclass của `Taxonomy`                                        |
| `Menu`            | `menu`             | Subclass của `Taxonomy`                                        |

Subclass pattern:

```php
class PostCategory extends Taxonomy {} // auto type = snake_case(class_basename)
```

### Traits

| Trait           | Dùng ở                                 | Chức năng                                              |
| --------------- | -------------------------------------- | ------------------------------------------------------ |
| `HasTaxonomies` | Consuming models (Post, Product, User) | `taxonomies()` → `morphToMany` qua bảng `taxonomables` |

### Tables

- `taxonomies` — tất cả taxonomy items
- `taxonomables` — pivot (`taxonomable_id`, `taxonomable_type`, `taxonomy_id`)

### API exposure

`PostCategory`, `ProductCategory`, `Menu`, `CustomerGroup` đều được expose qua `/api/items/{resource}` vì kế thừa `Taxonomy` có `QueryBuilder`.

---

## lumina/posts

**Namespace**: `Lumina\Posts\`
**Vai trò**: Blog posts, static pages (policies)

### Models

| Model    | Type     | Fields                                                                     | Traits                   |
| -------- | -------- | -------------------------------------------------------------------------- | ------------------------ |
| `Post`   | `blog`   | `title`, `slug`, `content`, `type`, `metadata`, `status` (published/draft) | `QueryBuilder`, `HasSeo` |
| `Policy` | `policy` | (extends Post)                                                             | -                        |

**Translatable fields**: `title`, `content`

**Relationships**:

- `categories()` → `morphToMany(PostCategory)`
- `relatedPosts()` → `belongsToMany(Post, 'related_post')`

** API**: `/api/items/posts`, `/api/items/policies`

---

## lumina/e-commerce

**Namespace**: `Lumina\Ecommerce\`
**Vai trò**: Product catalog, Cart, Order, Checkout

### Models

| Model            | Table              | Fields quan trọng                                                                                                       | Notes                                                            |
| ---------------- | ------------------ | ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `Product`        | `products`         | `name`, `slug`, `description`, `content`, `price`(int), `stock`(int), `status`(active/draft), `is_featured`, `position` | `QueryBuilder`, `HasSeo`; translatable: name/description/content |
| `ProductVariant` | `product_variants` | `name`, `image`(array), `price`(int), `sale_price`(int), `stock`, `is_default`, `has_options`                           | `belongsTo(Product)`, `belongsToMany(Option)`                     |
| `Option`         | `options`          | `name`, `slug`, `parent_id`                                                                                             | Self-referencing: root row = attribute (Size, Color...), child row (`parent_id` set) = its value (S/M/L, Red/Blue...) |
| `Category`       | `categories`       | `name`, `slug`, `parent_id`                                                                                             | `QueryBuilder`                                                   |
| `Cart`           | `carts`            | `customer_id`(nullable), `session_token`, `type`, `status`, `currency`, `shipping_fee`(int)                             | Types: cart/buy_now/wishlist                                     |
| `CartItem`       | `cart_items`       | `cart_id`, `cartable_id`, `cartable_type`, `quantity`, `unit_price`(int), `selected`, `metadata`(array)                 | Polymorphic `cartable()`                                         |
| `Order`          | `orders`           | `customer_id`, `status`, `metadata`(array), `shipping_fee`(int), `currency`                                             | `total` computed (không stored)                                  |
| `OrderItem`      | `order_items`      | `order_id`, `orderable_id`, `orderable_type`, `quantity`, `unit_price`, `metadata`                                      | Polymorphic `orderable()`                                        |

**Money convention**: tất cả giá tiền lưu dạng **integer** (đơn vị nhỏ nhất — xu/đồng). Không dùng float.

**Cart types**:

```php
Cart::TYPE_CART      = 'cart'
Cart::TYPE_BUY_NOW   = 'buy_now'
Cart::TYPE_WISHLIST  = 'wishlist'
```

**Morph map** (trong `EcommerceServiceProvider`):

```php
Relation::morphMap(['product' => Product::class]);
```

### Routes

```
# middleware: api + cookie encryption
GET    /api/cart          → CartController::get
DELETE /api/cart          → CartController::clear
POST   /api/cart/items    → CartController::add
PATCH  /api/cart/items    → CartController::update
POST   /api/checkout      → CheckoutController::store
```

**Cart resolution**: guest → `session_token` cookie; authenticated → `customer_id` (Sanctum)

### Relationships

- `Product` → `variants()` (HasMany ProductVariant)
- `Product` → `categories()` (morphToMany ProductCategory)
- `Product` → `relatedProducts()` (BelongsToMany self)
- `Product` → `posts()` (BelongsToMany Post)
- `Cart` → `items()` (HasMany CartItem)
- `CartItem` → `cartable()` (MorphTo — Product hoặc purchasable khác)
- `Transaction` → `order()` (BelongsTo Order)

---

## lumina/customer

**Namespace**: `Lumina\Customer\`
**Vai trò**: Customer authentication — register/login/me/logout

### Models

| Model                        | Notes                                                                                                                                                                                                                       |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Lumina\Customer\Models\User` | Model **gốc** thật sự — `Authenticatable`, full traits: `HasApiTokens`, `HasFactory`, `HasSocialAccounts`, `HasTaxonomies`, `Notifiable`, `PasskeyAuthenticatable`, `QueryBuilder`, `TwoFactorAuthenticatable`. Fields: `name`, `email`, `phone`, `password`, `status` (`active`/`locked`), `email_verified_at`, `phone_verified_at`. Quan hệ: `customer_groups()` (taxonomies lọc `type=customer_group`), `passkeys()`, `addresses()` (morphMany → `Lumina\Locations\Models\Address`) |
| `App\Models\User`             | Chỉ `class User extends \Lumina\Customer\Models\User {}` — giữ để code hiện có (Fortify, factories, `App\Models\User::class` reference) không đổi                                                                       |
| `CustomerGroup`                | Extends `Taxonomy` — type = `customer_group`. API: `/api/items/customer-groups`                                                                                                                                          |

> **Quan trọng**: Customer identity **thực chất sống trong plugin `customer`**, không phải `app/`. `App\Models\User` chỉ là 1 alias mỏng extend từ `Lumina\Customer\Models\User` — không có bảng `customers` riêng. Đây là hướng ngược với pattern "app sở hữu model, plugin chỉ import" thường thấy — quyết định có chủ đích để tách hoàn toàn customer identity khỏi `app/`.
>
> **Admin pages**: `/users` (danh sách/form khách hàng) và `/customer-groups` (cây nhóm khách hàng) — collection **phải** là `users` (không phải `customers`) vì `ResourceResolver` map kebab-plural → StudlySingular (`users` → `User`), tìm class `User` trong `core.model_namespaces`, không tồn tại class `Customer`.

### Routes

```
# middleware: api
POST /api/customer/register  → CustomerAuthController::register
POST /api/customer/login     → CustomerAuthController::login

# middleware: api + auth:sanctum
GET  /api/customer/me        → CustomerAuthController::me
POST /api/customer/logout    → CustomerAuthController::logout
```

### Auth flow

- `register`: validate name/email/password → create User → `createToken('api')` → return token
- `login`: verify password → return token (401 nếu sai, không leak email existence)
- `me`: trả về user + `taxonomies` (groups) + `socialAccounts` (nếu `method_exists`)
- `logout`: revoke chỉ current token

---

## lumina/social

**Namespace**: `Lumina\Social\`
**Vai trò**: OAuth social login — Google, Facebook (standalone, không depend lumina/customer)

### Models

| Model           | Table             | Fields                                                                 |
| --------------- | ----------------- | ---------------------------------------------------------------------- |
| `SocialAccount` | `social_accounts` | `socialable_id`, `socialable_type`, `provider`, `provider_id`, `email` |

Polymorphic — liên kết với bất kỳ model nào (`User`, `Admin`).

### Traits

| Trait               | Dùng ở          | Chức năng                                              |
| ------------------- | --------------- | ------------------------------------------------------ |
| `HasSocialAccounts` | `User`, `Admin` | `socialAccounts()` → HasMany SocialAccount (morphMany) |

### Routes

```
# middleware: api
GET /api/social/login/{provider}/redirect  → SocialAuthController::redirect
GET /api/social/login/{provider}/callback  → SocialAuthController::callback
```

**Providers**: `google`, `facebook` (config trong `configs/social.php`)

**`?as=` param**: `web` (User, default) hoặc `admin` (Admin) — dùng morph map để resolve

### Key pattern

```php
// SocialLoginService — không biết User hay Admin, dùng morph map
$modelClass = Relation::getMorphedModel($morphAlias); // 'web' → User::class
```

---

## lumina/payment

**Namespace**: `Lumina\Payment\`
**Vai trò**: Payment gateway integrations

### Models

| Model         | Table          | Fields                                                                                                                                                                                                          |
| ------------- | -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Transaction` | `transactions` | `order_id`, `gateway`, `kind`, `status`, `amount`(int), `currency`, `message`, `authorization`, `transaction_number`(auto: TXNddmmyy####), `is_test`, `is_cod_gateway`, `payment_details`(array), `verified_at` |

`Transaction::QueryBuilder` → exposed qua `/api/items/transactions`

### Routes

```
POST /api/payments/{gateway}          → PaymentController::process
ANY  /api/payments/{gateway}/callback → PaymentController::callback
ANY  /api/payments/{gateway}/ipn      → PaymentController::ipn
```

---

## lumina/notification

**Namespace**: `Lumina\Notification\`
**Vai trò**: In-app notifications cho Admin

### Routes

```
# middleware: auth (Admin session)
GET  notifications         → NotificationController::index
GET  notifications/recent  → NotificationController::recent
POST notifications/{id}/read     → NotificationController::markAsRead
POST notifications/read-all      → NotificationController::markAllAsRead
```

---

## lumina/ratings

**Namespace**: `Lumina\Ratings\`
**Vai trò**: Polymorphic rating/review system

### Models

| Model    | Table     | Fields                                                |
| -------- | --------- | ----------------------------------------------------- |
| `Rating` | `ratings` | `ratable_id`, `ratable_type`, `score`, `comment`, ... |

### Routes

```
# middleware: throttle 20/min
POST /api/ratings           → RatingController::store
GET  /api/ratings/statistics → RatingController::statistics

# Inertia page
GET  /ratings/{type}        → ratings/index page
```

---

## lumina/seo

**Namespace**: `Lumina\Seo\`
**Vai trò**: SEO meta management

### Models

| Model | Table  | Fields                                                                              |
| ----- | ------ | ----------------------------------------------------------------------------------- |
| `Seo` | `seos` | `seoable_id`, `seoable_type`, `title`, `description`, `keywords`, `image`, `schema` |

Polymorphic — attach vào Post, Product, etc.

### Traits

| Trait    | Dùng ở        | Chức năng                                        |
| -------- | ------------- | ------------------------------------------------ |
| `HasSeo` | Post, Product | `seo()` → morphOne(Seo), `saveSeo(array)` helper |

---

## lumina/sliders

**Namespace**: `Lumina\Sliders\`
**Vai trò**: Image slider/banner management

### Models

| Model    | Table     | Notes                                             |
| -------- | --------- | ------------------------------------------------- |
| `Slider` | `sliders` | `QueryBuilder` → exposed qua `/api/items/sliders` |

---

## lumina/redirects

**Namespace**: `Lumina\Redirects\`
**Vai trò**: URL redirect management (301/302)

### Models

| Model      | Table       | Notes                                               |
| ---------- | ----------- | --------------------------------------------------- |
| `Redirect` | `redirects` | `QueryBuilder` → exposed qua `/api/items/redirects` |

---

## lumina/otp (OTP System)

**Namespace**: `Lumina\Otp\`
**Vai trò**: Cung cấp dịch vụ tạo, gửi và xác thực OTP qua Email/SMS

### Models

| Model | Table | Notes |
|-------|-------|-------|
| `Otp` | `otps` | Lưu trữ mã OTP (hashed), action, verify token, và expires_at |

### Routes

```
POST /api/otp/send   → OtpController::send
POST /api/otp/verify → OtpController::verify
```

---

## lumina/shipping

**Namespace**: `Lumina\Shipping\`
**Vai trò**: Quản lý khu vực (Shipping Zones), phương thức giao hàng (Shipping Methods) và tính phí ship

### Models

| Model | Table | Notes |
|-------|-------|-------|
| `ShippingZone` | `shipping_zones` | Quản lý khu vực tỉnh thành |
| `ShippingMethod` | `shipping_methods` | Phương thức giao hàng & đơn giá cố định hoặc động |

### Routes

```
GET  /api/shipping/methods → ShippingController::index
POST /api/cart/shipping    → ShippingController::apply
```

---

## lumina/coupon (Coupon System)

**Namespace**: `Lumina\Coupon\`
**Vai trò**: Quản lý mã giảm giá (Coupon) và vết lượt sử dụng (Coupon Usage)

### Models

| Model | Table | Notes |
|-------|-------|-------|
| `Coupon` | `coupons` | Khai báo mã giảm giá, hình thức (fixed/percentage), giá trị, và hạn mức |
| `CouponUsage` | `coupon_usages` | Ghi log lượt sử dụng của từng User trên từng Order |

### Routes

```
POST   /api/cart/coupon → CouponController::apply
DELETE /api/cart/coupon → CouponController::remove
```

---

## lumina/locations (Locations & Address System)

**Namespace**: `Lumina\Locations\`
**Vai trò**: Quản lý thông tin địa lý và sổ địa chỉ của khách hàng (trong nước và quốc tế)

### Models

| Model | Table | Notes |
|-------|-------|-------|
| `Location` | `locations` | Danh mục địa lý phân cấp (Country -> Province/State -> District/City -> Ward) |
| `Address` | `addresses` | Sổ địa chỉ khách hàng hỗ trợ 2 dạng: Domestic (Việt Nam) và International (Nước ngoài) |

### Key Interface

- `Lumina\Locations\Contracts\LocationProviderInterface`: Cho phép hoán đổi nguồn lấy dữ liệu địa lý từ DB (`DatabaseLocationProvider`) hoặc API bên ngoài (GHN, GHTK, v.v.).

### Routes

```
GET /api/locations/countries       → LocationController::countries
GET /api/locations/{id}/children   → LocationController::children
```

**Quan hệ với `Lumina\Customer\Models\User`**: `Address.addressable` (morphMany, khai trên `User::addresses()`) — không có morph alias riêng, `Address` là bảng dùng chung, chưa cần đăng ký trong `Relation::morphMap()`.

---

## Cross-plugin Map

```
Lumina\Customer\Models\User ──uses──► HasApiTokens (Sanctum)
                                       HasSocialAccounts (social)
                                       HasTaxonomies (taxonomies)
                                       QueryBuilder (core)
                                       PasskeyAuthenticatable
                                       TwoFactorAuthenticatable
                           ──has──►    addresses() → Lumina\Locations\Models\Address (locations)
                                       customer_groups() → Taxonomy where type=customer_group

App\Models\User ──extends──► Lumina\Customer\Models\User

Post ──uses──► QueryBuilder + HasSeo
Product ──uses──► QueryBuilder + HasSeo
Transaction ──uses──► QueryBuilder

Taxonomy ◄──extends── PostCategory
                       ProductCategory
                       Menu
                       CustomerGroup

CartItem.cartable ──morphTo──► Product (hiện tại)
                                [purchasable khác sau này]

SocialAccount.socialable ──morphTo──► User ('web')
                                       Admin ('admin')
```

## Morph Map Toàn Dự Án

```php
// AppServiceProvider::boot()
Relation::morphMap([
    'web'   => App\Models\User::class,
    'admin' => Lumina\Cms\Models\Admin::class,
]);

// EcommerceServiceProvider::boot()
Relation::morphMap([
    'product' => Lumina\Ecommerce\Models\Product::class,
]);
```
