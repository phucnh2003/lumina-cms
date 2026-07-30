# OTP System Implementation Plan

Date: 2026-07-28
Plugin: `plugins/opt`

---

## Phases

### Phase 1: Plugin Scaffolding
1. Tạo file `plugins/opt/composer.json`:
   - Name: `lumina/opt`
   - Autoload: `Lumina\\Otp\\` -> `src/`
   - Register Provider: `Lumina\\Otp\\Providers\\OtpServiceProvider`
2. Tạo ServiceProvider `plugins/opt/src/Providers/OtpServiceProvider.php`:
   - Load routes
   - Load migrations
3. Đăng ký vào root `composer.json` -> `require`: `lumina/opt: "@dev"`.
4. Chạy `composer update lumina/opt`.

### Phase 2: Database Migration
1. Tạo migration `plugins/opt/database/migrations/xxxx_xx_xx_xxxxxx_create_otps_table.php`:
   - Cột: `identifier`, `code` (hashed), `action`, `token`, `expires_at`, `verified_at`.

### Phase 3: Models & Services
1. Tạo Model `plugins/opt/src/Models/Otp.php`:
   - `$fillable` các cột cần thiết.
   - Thêm casts cho `expires_at` và `verified_at` sang datetime.
2. Tạo Service `plugins/opt/src/Services/OtpService.php`:
   - `generate(string $identifier, string $action): Otp`: Tạo OTP code random 6 số, hash bằng `Hash::make()` hoặc `bcrypt()`, lưu vào DB, set `expires_at` (mặc định 10 phút), tạo `token` random.
   - `verify(string $identifier, string $action, string $code): ?string`: Xác thực. Tìm bản ghi gần nhất có `identifier` và `action` chưa verified, check expiry, check hash. Nếu khớp -> update `verified_at`, trả về `token`.

### Phase 4: Routing & Controller
1. Tạo file route `plugins/opt/routes/opt.php`:
   - `POST /api/otp/send`
   - `POST /api/otp/verify`
2. Tạo Controller `plugins/opt/src/Controllers/OtpController.php`:
   - Gọi `OtpService` để xử lý.
   - Trả về JSON chuẩn.
3. Tạo requests validation.

### Phase 5: Testing
1. Tạo Feature test tại `tests/Feature/Otp/OtpTest.php` hoặc `plugins/opt/tests/Feature/OtpTest.php`.
2. Chạy `composer lint` và `composer types:check`.
