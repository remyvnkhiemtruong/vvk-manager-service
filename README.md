# Hệ thống quản lý Trường THPT Võ Văn Kiệt

Ứng dụng quản lý nội bộ cho Trường THPT Võ Văn Kiệt: học sinh, giáo viên, lớp học, điểm số, điểm rèn luyện, phong trào, khen thưởng, kỷ luật, học phí, thông báo và cổng phụ huynh/học sinh.

## Stack

- Laravel 13, PHP 8.3+
- React + TypeScript + Inertia
- PostgreSQL, Redis, queue worker
- Docker Compose cho môi trường chạy chuẩn

## Chạy bằng Docker

```bash
cp .env.example .env
docker compose up --build
```

Ứng dụng: `http://localhost:8000`

Vite dev server: `http://localhost:5173`

## Tài khoản demo

Tất cả tài khoản demo dùng mật khẩu `password`.

- `admin@vvk.local` - Admin
- `bgh@vvk.local` - Ban giám hiệu
- `giaovu@vvk.local` - Giáo vụ
- `gvcn@vvk.local` - Giáo viên chủ nhiệm
- `giaovien@vvk.local` - Giáo viên bộ môn
- `doantruong@vvk.local` - Đoàn trường/BTC phong trào
- `giamthi@vvk.local` - Giám thị
- `ketoan@vvk.local` - Kế toán
- `phuhuynh@vvk.local` - Phụ huynh
- `hocsinh@vvk.local` - Học sinh

Dữ liệu seed chỉ là dữ liệu giả, dùng mã `DEMO` và email `.test/.local`, không chứa dữ liệu học sinh thật.

## Kiểm thử

```bash
npm run build
php artisan test
```

Trong phiên hiện tại, `npm run build` đã chạy thành công. PHP/Composer cục bộ chưa có và Docker Desktop Linux engine đang trả lỗi 500, nên `php artisan test` cần chạy sau khi bật lại Docker engine hoặc cài PHP/Composer.

## Kiến trúc

- `config/school.php`: khai báo module, resource, field, validation, permission và role matrix.
- `app/Http/Controllers/ResourceController.php`: CRUD dùng chung, kiểm tra permission, audit và revision cho nghiệp vụ nhạy cảm.
- `app/Support/Audit/Auditor.php`: audit logger bất biến ở tầng ứng dụng.
- `database/migrations`: schema cho RBAC, audit, học vụ, điểm, rèn luyện, phong trào, học phí và thông báo.
- `resources/js/Pages`: dashboard, resource CRUD, audit, reports, portal.

## Ghi chú vận hành

- Audit log áp dụng cho phân quyền, sửa điểm, sửa điểm rèn luyện, học phí và kỷ luật.
- Giáo viên bộ môn bị giới hạn nhập/xem điểm theo phân công lớp-môn.
- Phụ huynh/học sinh dùng cổng riêng, chỉ thấy dữ liệu hồ sơ được liên kết.

