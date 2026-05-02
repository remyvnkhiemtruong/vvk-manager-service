# Lộ trình phát triển

## Nguyên tắc chung

- Không dùng dữ liệu học sinh thật trong seed, test hoặc tài liệu.
- Mọi thay đổi schema phải có migration.
- Mọi thao tác nhạy cảm phải có audit log.
- Mỗi phase cần có test quyền cơ bản cho vai trò bị ảnh hưởng.
- Không phá module cũ, route cũ, permission cũ hoặc seed hiện có.

## Phase 1 - Tài liệu kiến trúc và thống nhất hướng đi

Mục tiêu:

- Hoàn thiện tài liệu tổng thể trong `docs/`.
- Ghi nhận stack hiện tại và kiến trúc mục tiêu.
- Xác định module, role matrix và roadmap.

Deliverables:

- `docs/architecture.md`
- `docs/modules.md`
- `docs/roles-permissions.md`
- `docs/development-roadmap.md`

Acceptance criteria:

- Tài liệu mô tả đúng hiện trạng Laravel 13 + React/Inertia.
- Có đủ module được yêu cầu, bao gồm module điểm danh/chuyên cần chưa triển khai.
- Có ma trận quyền cho 10 vai trò.
- Không thay đổi runtime code.

## Phase 2 - Harden nền tảng hiện tại

Mục tiêu:

- Kiểm tra và làm chắc nền tảng auth, RBAC, audit, seed và Docker.
- Xử lý vấn đề encoding tiếng Việt nếu còn trong file hiện hữu.
- Xác nhận đường chạy test backend bằng Docker hoặc PHP/Composer local.

Deliverables:

- Test backend chạy được trong môi trường chuẩn.
- Kiểm tra Docker Compose chạy app, Vite, queue, PostgreSQL, Redis.
- Bổ sung hoặc điều chỉnh test permission/audit cho resource hiện có.
- Chuẩn hóa thông báo tiếng Việt trong UI/backend.

Acceptance criteria:

- `npm run build` pass.
- `php artisan test` pass trong môi trường chuẩn.
- Admin, BGH, giáo vụ, GVCN, giáo viên bộ môn, kế toán, phụ huynh, học sinh được kiểm tra quyền cơ bản.
- Audit log sinh ra cho sửa điểm, sửa điểm rèn luyện, học phí, kỷ luật và phân quyền.

## Phase 3 - Hoàn thiện học vụ và chuyên cần

Mục tiêu:

- Hoàn thiện quản lý hồ sơ học vụ.
- Thêm module điểm danh/chuyên cần.
- Tăng scope dữ liệu cho GVCN, giáo viên bộ môn, giám thị.

Deliverables:

- Migration/model/resource cho `attendance_sessions`, `attendance_records`, `attendance_excuses`.
- UI điểm danh theo lớp/ngày/buổi/môn.
- Workflow xác nhận lý do vắng và chốt chuyên cần.
- Báo cáo chuyên cần theo học sinh, lớp, học kỳ.

Acceptance criteria:

- GVCN/Giáo viên bộ môn chỉ điểm danh trong phạm vi được phân công.
- Sửa điểm danh sau khi chốt có audit log.
- Phụ huynh/học sinh chỉ xem chuyên cần của học sinh liên kết/chính mình.
- Seed chuyên cần chỉ dùng dữ liệu giả.

## Phase 4 - Làm sâu điểm, rèn luyện, kỷ luật và học phí

Mục tiêu:

- Bổ sung workflow duyệt/chốt dữ liệu nhạy cảm.
- Tăng khả năng truy vết và giảm rủi ro sửa nhầm.

Deliverables:

- Trạng thái chốt điểm/chốt rèn luyện/chốt học phí theo học kỳ.
- Lý do chỉnh sửa bắt buộc cho điểm và rèn luyện.
- Workflow hoàn/điều chỉnh thanh toán học phí.
- Lịch sử kỷ luật/khen thưởng theo học sinh.

Acceptance criteria:

- Không thể sửa dữ liệu đã chốt nếu không có quyền phù hợp.
- Mọi chỉnh sửa dữ liệu nhạy cảm có audit và revision/history.
- Kế toán chỉ thao tác module tài chính; phụ huynh chỉ xem công nợ của con mình.

## Phase 5 - Portal, thông báo, báo cáo và export

Mục tiêu:

- Hoàn thiện trải nghiệm phụ huynh/học sinh.
- Mở rộng thông báo và báo cáo thống kê.
- Thêm export Excel/PDF khi nghiệp vụ cần.

Deliverables:

- Portal phụ huynh/học sinh có điểm, rèn luyện, chuyên cần, học phí, thông báo.
- Thông báo theo đối tượng và trạng thái đã đọc.
- Dashboard báo cáo theo năm học/học kỳ/lớp.
- Export Excel/PDF cho báo cáo điểm, chuyên cần, học phí, phong trào.

Acceptance criteria:

- Export tôn trọng permission và scope dữ liệu.
- Không lộ dữ liệu học sinh khác trong portal.
- Báo cáo có filter/search/pagination phù hợp.
- UI tiếng Việt, dễ dùng cho môi trường THPT.

## Phase 6 - Production readiness

Mục tiêu:

- Sẵn sàng vận hành thật với Docker và quy trình bảo mật.
- Chuẩn bị backup, monitoring, deployment và tài liệu vận hành.

Deliverables:

- Docker profile production.
- Hướng dẫn backup/restore PostgreSQL và storage.
- Health check, logging, queue monitoring.
- Security review cho auth, authorization, upload, audit và dữ liệu cá nhân.
- Checklist triển khai môi trường staging/production.

Acceptance criteria:

- Có hướng dẫn deploy từ môi trường sạch.
- Có backup/restore test được.
- Không có secret hard-code.
- Tài khoản demo không tồn tại trong production seed.
- Audit log không thể bị sửa/xóa từ UI thông thường.

