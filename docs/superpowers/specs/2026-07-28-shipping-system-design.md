# Shipping System Design Spec

Date: 2026-07-28
Plugin: `plugins/shipping`

## Purpose

Cung cấp tính năng quản lý khu vực giao hàng (Shipping Zones), các phương thức vận chuyển (Shipping Methods) và tự động tính toán phí vận chuyển (Shipping Fee) dựa trên giỏ hàng và địa chỉ giao hàng.

---

## In Scope

1. **Model `ShippingZone`**: Quản lý các khu vực địa lý (quốc gia, tỉnh thành).
2. **Model `ShippingMethod`**: Phương thức vận chuyển (VD: Standard, Express) thuộc ShippingZone với cách tính phí cố định (Flat rate) hoặc theo điều kiện giỏ hàng.
3. **ShippingCalculator**: Service tính toán phí vận chuyển cho Cart.
4. **API Endpoints**:
   - `GET /api/shipping/methods`: Lấy các phương thức vận chuyển khả dụng cho một địa chỉ nhất định.
   - `POST /api/cart/shipping`: Áp dụng phương thức vận chuyển vào giỏ hàng hiện tại.

## Out of Scope this phase

- Kết nối API thời gian thực với các bên thứ ba (GHTK, GHN, Viettel Post).
- Tính phí vận chuyển theo cân nặng/kích thước phức tạp (sẽ phát triển ở phase sau).

---

## Design

### 1. Database Schema

#### `shipping_zones`
```php
Schema::create('shipping_zones', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('regions')->nullable(); // Danh sách tỉnh thành/quận huyện được áp dụng (ví dụ: ["VN-SG", "VN-HN"])
    $table->timestamps();
});
```

#### `shipping_methods`
```php
Schema::create('shipping_methods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('code')->unique(); // 'standard', 'express'
    $table->unsignedInteger('cost')->default(0); // Phí vận chuyển (smallest currency unit)
    $table->unsignedInteger('min_order_amount')->nullable(); // Điều kiện giá trị đơn hàng tối thiểu
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

---

## Key Decisions

- **Polymorphic Morph Map**: Đăng ký `shipping_method` trong `Relation::morphMap` để sẵn sàng cho các plugin khác liên kết thông tin.
- **Vận chuyển dựa trên Tỉnh/Thành**: Shipping zone phân loại chủ yếu dựa trên code tỉnh/thành để matching nhanh chóng thay vì lưu toạ độ.
- **Giá lưu dạng integer**: Phí ship luôn lưu dưới dạng tiền tệ nhỏ nhất (đồng/cents).

---

## API Endpoints

### 1. Get Available Methods
- **Route**: `GET /api/shipping/methods`
- **Params**: `?province_code=VN-SG&cart_amount=500000`
- **Response**: `200 OK`
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Standard Delivery",
        "code": "standard",
        "cost": 30000
      }
    ]
  }
  ```

### 2. Apply Shipping Method to Cart
- **Route**: `POST /api/cart/shipping`
- **Body**:
  ```json
  {
    "shipping_method_code": "standard"
  }
  ```
- **Response**: Trả về cấu trúc Cart đầy đủ có chứa `shipping_fee` đã được tính lại.
