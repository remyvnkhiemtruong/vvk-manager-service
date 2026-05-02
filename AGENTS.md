# AGENTS.md - Hướng dẫn làm việc cho Codex

## 1. Mục tiêu dự án

Dự án là hệ thống quản lý nội bộ cho Trường THPT Võ Văn Kiệt, phục vụ quản lý học sinh, giáo viên, lớp học, điểm số, điểm rèn luyện, phong trào, khen thưởng, kỷ luật, học phí, thông báo và cổng phụ huynh/học sinh.

Hệ thống không chỉ là website tin tức. Mọi thay đổi cần tôn trọng mục tiêu vận hành nội bộ, phân quyền chi tiết, audit log và dữ liệu demo an toàn.

## 2. Kiến trúc frontend/backend/database

- Backend dùng Laravel 13 theo mô hình modular monolith. Ưu tiên controller, service/action và model có trách nhiệm rõ ràng.
- Frontend dùng React, TypeScript và Inertia. UI quản trị nằm trong `resources/js`, CSS chung nằm trong `resources/css`.
- Database chính là PostgreSQL, quản lý schema bằng Laravel migration. SQLite chỉ dùng cho test nếu cần.
- `config/school.php` là nơi khai báo module, resource, field, validation, permission và role matrix cho CRUD dùng chung.
- RBAC, audit log và portal phụ huynh/học sinh là phần lõi. Khi thêm nghiệp vụ mới, luôn xác định vai trò nào được xem, tạo, sửa, xóa và audit thao tác nào.
- Không đưa logic nghiệp vụ nhạy cảm vào UI-only. Server phải là nơi validate, kiểm tra quyền và ghi audit.

## 3. Quy ước đặt tên

- Controller: đặt tên dạng `*Controller`, nằm trong `app/Http/Controllers`. Ví dụ: `ResourceController`, `AuditLogController`.
- Model/entity: danh từ số ít, PascalCase, nằm trong `app/Models`. Ví dụ: `Student`, `ScoreEntry`, `FeeInvoice`.
- Service/action: PascalCase, tên mô tả hành động nghiệp vụ rõ ràng. Ví dụ: `RecordScoreRevision`, `CollectFeePayment`.
- Middleware: PascalCase, mô tả điều kiện xử lý. Ví dụ: `EnsurePermission`.
- React page/component: PascalCase `.tsx`, đặt theo module trong `resources/js/Pages`, `resources/js/Components` hoặc `resources/js/Layouts`.
- Route: RESTful theo resource, tên route rõ nghiệp vụ. Route nội bộ cần nằm sau middleware `auth` và permission phù hợp.
- DTO/request validation: ưu tiên FormRequest khi nghiệp vụ phức tạp; với CRUD cấu hình dùng validation tập trung trong `config/school.php`.
- Database table: snake_case, số nhiều cho bảng nghiệp vụ. Pivot table đặt theo hai entity, ví dụ `student_guardians`.
- Column: snake_case, khóa ngoại dạng `*_id`, trạng thái dùng các giá trị string ổn định và có validation.

## 4. Quy ước bảo mật

- Không hard-code secret, token, mật khẩu, API key hoặc thông tin đăng nhập thật trong code, seed, test, README hay comment.
- Không lưu mật khẩu plain text. Luôn dùng hashing của Laravel và không trả password/hash ra UI/API.
- Không dùng dữ liệu học sinh thật trong seed, test, screenshot, tài liệu hoặc ví dụ. Chỉ dùng dữ liệu giả với mã demo và email `.test` hoặc `.local`.
- Mọi thao tác nhạy cảm phải có audit log: sửa điểm, sửa điểm rèn luyện, thu/hoàn học phí, kỷ luật, phân quyền, liên kết tài khoản phụ huynh/học sinh.
- Audit log cần có actor, action, subject, before/after values, IP/user agent nếu có và thời điểm thao tác.
- Luôn kiểm tra permission trên server trước khi đọc, tạo, sửa hoặc xóa dữ liệu nội bộ.
- Phụ huynh chỉ được xem dữ liệu học sinh đã liên kết. Học sinh chỉ được xem dữ liệu của chính mình.
- Không tin dữ liệu từ UI. Mọi input phải validate phía server.

## 5. Quy ước database

- Mọi thay đổi schema phải đi qua migration. Không sửa trực tiếp database/schema nếu chưa có migration tương ứng.
- Migration phải có `up()` và `down()` hợp lý, đặt tên rõ mục đích và giữ thứ tự phụ thuộc khóa ngoại.
- Không sửa migration đã chạy trong môi trường chia sẻ nếu thay đổi có tính migrate tiếp; tạo migration mới để điều chỉnh.
- Seed data chỉ dùng dữ liệu giả, mã `DEMO`, email `.test` hoặc `.local`, và không mô phỏng thông tin nhạy cảm thật.
- Khi thêm bảng nghiệp vụ nhạy cảm, cần cân nhắc revision table hoặc audit metadata để truy vết thay đổi.
- Thêm index cho các trường lọc/tìm kiếm phổ biến và khóa ngoại quan trọng.
- Dùng transaction cho các nghiệp vụ cập nhật nhiều bảng, đặc biệt là điểm, học phí, kỷ luật và phân quyền.

## 6. Quy ước API

- Thiết kế RESTful theo resource và hành động nghiệp vụ rõ ràng.
- Response format phải thống nhất, có thông báo thành công/lỗi và lỗi validation để UI hiển thị được.
- Danh sách dữ liệu phải có pagination. Không trả về tập dữ liệu lớn không giới hạn.
- Hỗ trợ filter/search cho các danh sách có khả năng lớn như học sinh, giáo viên, lớp, điểm, học phí và audit log.
- Validate mọi input phía server. Lỗi validation cần rõ trường nào sai và lý do sai.
- API/route nội bộ phải kiểm tra auth, permission và scope dữ liệu theo vai trò.
- Không để UI tự quyết định các trường hệ thống như actor audit, người thu tiền, người nhập điểm nếu server có thể suy ra từ user đang đăng nhập.

## 7. Quy ước UI

- Toàn bộ giao diện người dùng dùng tiếng Việt, ưu tiên từ ngữ rõ ràng với môi trường THPT.
- Phong cách cần nghiêm túc, dễ đọc, dễ thao tác, phù hợp cán bộ nhà trường, giáo viên, phụ huynh và học sinh.
- Module quản trị ưu tiên bảng, bộ lọc, tìm kiếm, phân trang, trạng thái rõ ràng và hành động ngắn gọn.
- Báo cáo có thể thêm export Excel/PDF khi nghiệp vụ yêu cầu. Không thêm export nếu chưa có nhu cầu rõ.
- Không hiển thị dữ liệu ngoài phạm vi vai trò hiện tại.
- Các thao tác nguy hiểm như xóa, sửa điểm, thu/hoàn phí, kỷ luật cần có xác nhận và thông báo kết quả rõ ràng.
- Form phải có validation feedback bằng tiếng Việt và giữ lại dữ liệu người dùng đã nhập khi có lỗi.

## 8. Lệnh chạy project, test, lint, build

- Cài frontend: `npm install`
- Dev frontend: `npm run dev`
- Build frontend: `npm run build`
- Lint/typecheck frontend: `npm run lint`
- Chạy Docker: `docker compose up --build`
- Cài PHP dependencies trong container/local: `composer install`
- Migration + seed: `php artisan migrate --seed`
- Reset database demo: `php artisan migrate:fresh --seed`
- Test backend: `php artisan test`

Nếu máy local không có PHP/Composer, ưu tiên chạy qua Docker. Không commit `vendor`, `node_modules`, `.env`, build artifact hoặc log.

## 9. Definition of Done

Một thay đổi chỉ hoàn tất khi:

- Code chạy được trong môi trường dự án.
- Có test cơ bản cho nghiệp vụ, permission hoặc security path liên quan.
- Có migration nếu thay đổi schema.
- Có seed giả nếu thêm dữ liệu demo cần thiết.
- Có kiểm tra phân quyền cho vai trò bị ảnh hưởng.
- Có audit log cho thao tác nhạy cảm.
- Input được validate phía server.
- UI hiển thị tiếng Việt và không lộ dữ liệu ngoài scope.
- Không phá module cũ, route cũ, permission cũ hoặc seed/test hiện có.
- README hoặc tài liệu liên quan được cập nhật khi thay đổi cách chạy, cấu hình hoặc hành vi quan trọng.

