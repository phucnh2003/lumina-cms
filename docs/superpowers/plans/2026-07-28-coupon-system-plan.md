# Coupon System Implementation Plan (Cupont)

Date: 2026-07-28
Plugin: `plugins/cupont`

---

## Phases

### Phase 1: Plugin Scaffolding
1. Tạo file `plugins/cupont/composer.json`:
   - Name: `lumina/cupont`
   - Autoload: `Lumina\\Cupont\\` -> `src/`
   - Register Provider: `Lumina\\Cupont\\Providers\\CupontServiceProvider`
2. Tạo ServiceProvider `plugins/cupont/src/Providers/CupontServiceProvider.php`.
3. Đăng ký vào root `composer.json` -> `require`: `lumina/cupont: "@dev"`.
4. Chạy `composer update lumina/cupont`.

### Phase 2: Database Migration
1. Tạo migration cho bảng `cuponts` và bảng `cupont_usages`.
2. Chạy `php artisan migrate`.

### Phase 3: Models & Services
1. Tạo Model `Lumina\Cupont\Models\Cupont` và `Lumina\Cupont\Models\CupontUsage`.
2. Implement `QueryBuilder` trait trong `Cupont` model để expose quản lý ở CMS.
3. Tạo Service `Lumina\Cupont\Services\CupontService.php`:
   - Method `validateCoupon(string $code, User $user, int $cartAmount)`: Kiểm tra tính khả dụng của Coupon (hết hạn, vượt quá giới hạn tổng cộng, giới hạn của user này, hoặc chưa đạt giá trị mua tối thiểu).
   - Method `calculateDiscount(Cupont $coupon, int $cartAmount)`: Trả về số tiền được giảm giá (đảm bảo không vượt quá `max_discount` đối với coupon percentage).

### Phase 4: Integration with Checkout & Cart
1. Tạo routes trong `plugins/cupont/routes/cupont.php`.
2. Tạo `CupontController.php` xử lý các API `POST /api/cart/coupon` và `DELETE /api/cart/coupon`.
3. Khi coupon hợp lệ, lưu trữ mã giảm giá và số tiền được trừ vào metadata của giỏ hàng để `CartController::present()` hiển thị chính xác.

### Phase 5: PestPHP Tests
1. Viết tests kiểm tra các kịch bản: coupon giảm giá cố định, phần trăm, điều kiện min_spend, giới hạn lượt dùng, và kịch bản coupon hết hạn.
2. Đảm bảo chạy `composer lint` và `composer types:check` thành công.
