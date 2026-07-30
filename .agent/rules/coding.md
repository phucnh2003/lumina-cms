# Coding & Naming Rules

> Mọi dòng code viết ra phải tuân thủ nghiêm ngặt các nguyên tắc đặt tên và cấu trúc để đảm bảo dễ đọc, dễ hiểu cho người khác.

---

## 1. Đặt Tên Biến & Thuộc Tính (Variables & Properties)

- **Không viết tắt**: Đặt tên đầy đủ, phản ánh chính xác mục đích sử dụng.
  - ❌ *Sai*: `$usr`, `$auth`, `$qty`, `$amt`, `$msg`, `$cust`
  -  *Đúng*: `$user`, `$authenticated`, `$quantity`, `$amount`, `$message`, `$customer`
- **Ngoại lệ viết tắt được chấp nhận**: Chỉ chấp nhận các thuật ngữ chuẩn hóa trong ngành CNTT:
  - `id` (Identity/Identifier)
  - `url` (Uniform Resource Locator)
  - `db` (Database)
  - `ip` (Internet Protocol)
  - `otp` (One-Time Password)
  - `api` (Application Programming Interface)
  - `json` (JavaScript Object Notation)
- **Tên biến phải dễ đọc**: Viết rõ nghĩa, không ghép từ cụt lủn làm người đọc phải đoán nghĩa.
- **Biến Boolean**: Phải có tiền tố khẳng định/trạng thái như `is_`, `has_`, `can_`, `should_` (Snake case đối với PHP/DB, Camel case đối với TypeScript/JS):
  - ❌ *Sai*: `$active`, `$verified`, `$permissions`
  -  *Đúng (PHP)*: `$isActive`, `$isVerified`, `$hasPermission` (hoặc `$is_active` đối với database attributes)
  -  *Đúng (TS/JS)*: `isActive`, `isVerified`, `hasPermission`

---

## 2. Đặt Tên Hàm & Phương Thức (Functions & Methods)

- **Động từ đứng đầu**: Mọi hàm/phương thức phải bắt đầu bằng một động từ thể hiện đúng hành động nó thực hiện.
  - ❌ *Sai*: `userUpdate()`, `dataProcess()`, `cartAdd()`
  -  *Đúng*: `updateUser()`, `processData()`, `addItemToCart()`
- **Tên hàm phải phản ánh đúng kết quả**:
  - Hàm kiểm tra trạng thái trả về boolean nên bắt đầu bằng `is`, `has`, `can`.
  - Hàm lấy dữ liệu nên bắt đầu bằng `get`, `find`, `resolve`.

---

## 3. Tên Cột/Trường Trong Cơ Sở Dữ Liệu (Database Columns)

- Mọi cột/trường trong database migration bắt buộc phải dùng **Snake Case** đầy đủ và tường minh:
  - ❌ *Sai*: `cust_id`, `qty`, `ship_fee`, `disc_val`
  -  *Đúng*: `customer_id`, `quantity`, `shipping_fee`, `discount_value`
- Giữ tên trường dễ đọc và nhất quán trên toàn bộ các plugin để không gây nhầm lẫn khi mapping.
