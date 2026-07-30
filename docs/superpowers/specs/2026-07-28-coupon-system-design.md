# Coupon System Design Spec (Cupont)

Date: 2026-07-28
Plugin: `plugins/cupont`

> Note: Tên thư mục và package được đặt thống nhất là `cupont` theo cấu trúc monorepo hiện có.

## Purpose

Cung cấp tính năng quản lý mã giảm giá (Coupon/Cupont). Cho phép người dùng áp dụng mã giảm giá trực tiếp vào giỏ hàng trước khi thanh toán. Hỗ trợ nhiều loại discount (giảm giá cố định hoặc phần trăm) cùng với các điều kiện ràng buộc.

---

## In Scope

1. **Model `Cupont`**: Quản lý mã giảm giá, giới hạn sử dụng, điều kiện giá trị đơn hàng.
2. **Model `CupontUsage`**: Lưu vết lịch sử khách hàng nào đã sử dụng mã nào, cho đơn hàng nào để kiểm soát giới hạn.
3. **CupontService**: Logic kiểm tra tính hợp lệ và tính toán số tiền giảm.
4. **API Endpoints**:
   - `POST /api/cart/coupon`: Áp dụng mã giảm giá vào giỏ hàng.
   - `DELETE /api/cart/coupon`: Gỡ bỏ mã giảm giá khỏi giỏ hàng.

## Out of Scope this phase

- Tự động áp dụng coupon tối ưu nhất (Auto-apply best coupon).
- Coupon phát hành riêng cho từng khách hàng cụ thể (sẽ làm ở phase sau).

---

## Design

### 1. Database Schema

#### `cuponts`
```php
Schema::create('cuponts', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique(); // VD: SUMMERSALE
    $table->string('type');           // 'fixed', 'percentage'
    $table->unsignedInteger('value'); // Số tiền giảm hoặc phần trăm (ví dụ: 50000 hoặc 15)
    
    // Constraints
    $table->unsignedInteger('min_spend')->default(0); // Đơn hàng tối thiểu
    $table->unsignedInteger('max_discount')->nullable(); // Số tiền giảm tối đa (nếu là dạng percentage)
    $table->unsignedInteger('usage_limit')->nullable(); // Tổng số lần được dùng
    $table->unsignedInteger('usage_limit_per_user')->default(1); // Số lần dùng tối đa của mỗi user
    $table->unsignedInteger('usage_count')->default(0); // Đã sử dụng bao nhiêu lần

    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### `cupont_usages`
```php
Schema::create('cupont_usages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cupont_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // App\Models\User
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
});
```

---

## Key Decisions

- **Expose qua JAM API**: `Cupont` sẽ sử dụng `QueryBuilder` trait để admin có thể quản lý CRUD nhanh qua generic endpoints `/api/items/cuponts`.
- **Tránh lưu số âm**: Cột `value` lưu dạng integer dương. Logic tính giảm giá trên giỏ hàng sẽ trừ tiền và đảm bảo giá trị giỏ hàng không âm (`max(0, $total - $discount)`).

---

## API Endpoints

### 1. Apply Coupon to Cart
- **Route**: `POST /api/cart/coupon`
- **Body**:
  ```json
  {
    "code": "SUMMERSALE"
  }
  ```
- **Response**: `200 OK` với giỏ hàng đã áp dụng mã thành công. Trả về thông tin discount trong response metadata.

### 2. Remove Coupon from Cart
- **Route**: `DELETE /api/cart/coupon`
- **Response**: `200 OK` với giỏ hàng đã được reset giá gốc.
