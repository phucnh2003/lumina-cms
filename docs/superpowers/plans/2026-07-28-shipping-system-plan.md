# Shipping System Implementation Plan

Date: 2026-07-28
Plugin: `plugins/shipping`

---

## Phases

### Phase 1: Plugin Scaffolding
1. Tạo file `plugins/shipping/composer.json`:
   - Name: `lumina/shipping`
   - Autoload: `Lumina\\Shipping\\` -> `src/`
   - Register Provider: `Lumina\\Shipping\\Providers\\ShippingServiceProvider`
2. Tạo ServiceProvider `plugins/shipping/src/Providers/ShippingServiceProvider.php`.
3. Đăng ký vào root `composer.json` -> `require`: `lumina/shipping: "@dev"`.
4. Chạy `composer update lumina/shipping`.

### Phase 2: Migrations & Models
1. Tạo migration tạo bảng `shipping_zones` và `shipping_methods`.
2. Tạo các Model tương ứng:
   - `Lumina\Shipping\Models\ShippingZone`
   - `Lumina\Shipping\Models\ShippingMethod` (Đăng ký morph alias `shipping_method` trong boot).

### Phase 3: Shipping Calculator Service
1. Tạo `Lumina\Shipping\Services\ShippingService.php`:
   - Method `getAvailableMethods(string $provinceCode, int $cartAmount)`: Tìm ShippingZone tương ứng với `provinceCode`, trả về danh sách các `ShippingMethod` có điều kiện thỏa mãn.
   - Method `applyShipping(Cart $cart, string $methodCode)`: Tìm method theo code, update trường `shipping_fee` trên Cart thông qua API của `CartController` hoặc database query trực tiếp, lưu thông tin method vào metadata của giỏ hàng.

### Phase 4: API & Controller
1. Tạo routes trong `plugins/shipping/routes/shipping.php`.
2. Tạo Controller `ShippingController.php` để gọi Service tính phí và trả kết quả.

### Phase 5: Verification & Tests
1. Tạo feature tests giả lập quá trình lấy phí và áp dụng phí vào giỏ hàng.
2. Kiểm tra code style (`composer lint`) và static analysis (`composer types:check`).
