# Thiết kế module hệ thống

## Tổng quan

Hệ thống chia module theo nghiệp vụ trường THPT. Một số module đã có CRUD nền trong hiện trạng, một số module là mục tiêu triển khai tiếp theo. Mỗi module phải xác định rõ actor, dữ liệu lõi, workflow, audit và scope quyền.

## 1. Quản lý người dùng và phân quyền

- Mục tiêu: quản lý tài khoản, vai trò, permission và trạng thái truy cập.
- Actor chính: Admin, BGH.
- Dữ liệu lõi: `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `audit_logs`.
- Workflow chính: tạo tài khoản, gán vai trò, khóa/mở tài khoản, kiểm tra quyền.
- Audit/security: bắt buộc audit khi tạo/sửa/xóa user, role, permission, gán quyền.
- Hiện trạng: đã có model, migration, seed, resource config và permission matrix.

## 2. Quản lý học sinh

- Mục tiêu: quản lý hồ sơ học sinh, trạng thái học tập và liên kết tài khoản.
- Actor chính: Admin, BGH, Giáo vụ, GVCN.
- Dữ liệu lõi: `students`, `class_enrollments`, `student_guardians`, `users`.
- Workflow chính: tạo hồ sơ, cập nhật thông tin, xếp lớp, chuyển lớp, liên kết phụ huynh.
- Audit/security: audit khi liên kết tài khoản hoặc thay đổi thông tin nhạy cảm; không dùng dữ liệu thật trong seed/test.
- Hiện trạng: đã có schema/resource cơ bản; chuyển lớp dùng `class_enrollments`.

## 3. Quản lý giáo viên

- Mục tiêu: quản lý hồ sơ giáo viên/nhân sự, chuyên môn, tài khoản và trạng thái công tác.
- Actor chính: Admin, BGH, Giáo vụ.
- Dữ liệu lõi: `staff`, `teacher_profiles`, `users`, `roles`.
- Workflow chính: tạo hồ sơ nhân sự, cập nhật tổ chuyên môn, liên kết user, phân công chủ nhiệm hoặc giảng dạy.
- Audit/security: audit khi liên kết tài khoản hoặc thay đổi vai trò.
- Hiện trạng: đã có schema/resource cơ bản.

## 4. Quản lý năm học, học kỳ, lớp, khối

- Mục tiêu: quản lý cấu trúc học vụ theo năm học.
- Actor chính: Admin, BGH, Giáo vụ.
- Dữ liệu lõi: `school_years`, `semesters`, `grades`, `classes`, `class_enrollments`.
- Workflow chính: mở năm học, tạo học kỳ, tạo khối/lớp, gán GVCN, xếp học sinh vào lớp.
- Audit/security: audit khi chuyển lớp hoặc thay đổi cấu trúc đang dùng nếu ảnh hưởng dữ liệu điểm/danh sách.
- Hiện trạng: đã có schema/resource cho năm học, học kỳ, lớp và xếp lớp.

## 5. Quản lý môn học và phân công giảng dạy

- Mục tiêu: quản lý môn học và giáo viên dạy từng lớp/học kỳ.
- Actor chính: Admin, BGH, Giáo vụ.
- Dữ liệu lõi: `subjects`, `teaching_assignments`, `staff`, `classes`, `semesters`.
- Workflow chính: tạo môn, cập nhật tổ chuyên môn, phân công giáo viên bộ môn theo lớp và học kỳ.
- Audit/security: phân công quyết định scope nhập điểm, nên cần audit khi thay đổi phân công.
- Hiện trạng: đã có schema/resource; `ResourceController` giới hạn giáo viên bộ môn theo phân công khi nhập/xem điểm.

## 6. Quản lý điểm số

- Mục tiêu: nhập, cập nhật, theo dõi điểm theo học sinh, môn, học kỳ và loại điểm.
- Actor chính: Giáo viên bộ môn, GVCN, Giáo vụ, BGH, Admin.
- Dữ liệu lõi: `score_categories`, `score_entries`, `score_revisions`.
- Workflow chính: cấu hình loại điểm, nhập điểm, sửa điểm, xem bảng điểm, tổng hợp điểm.
- Audit/security: sửa điểm bắt buộc có audit log và revision với before/after/reason; giáo viên bộ môn chỉ thao tác lớp-môn được phân công.
- Hiện trạng: đã có schema/resource, audit và revision cơ bản.

## 7. Quản lý điểm rèn luyện

- Mục tiêu: ghi nhận điểm/xếp loại rèn luyện theo học kỳ.
- Actor chính: GVCN, Giám thị, BGH, Admin.
- Dữ liệu lõi: `conduct_scores`, `conduct_revisions`.
- Workflow chính: nhập điểm rèn luyện, duyệt, sửa điểm, xem lịch sử.
- Audit/security: mọi sửa điểm rèn luyện phải có audit và revision.
- Hiện trạng: đã có schema/resource, audit và revision cơ bản.

## 8. Quản lý điểm danh/chuyên cần

- Mục tiêu: theo dõi hiện diện, đi trễ, vắng có phép/không phép theo buổi/ngày/môn.
- Actor chính: GVCN, Giáo viên bộ môn, Giám thị, Giáo vụ, BGH.
- Dữ liệu lõi đề xuất: `attendance_sessions`, `attendance_records`, `attendance_excuses`.
- Workflow chính: tạo buổi điểm danh, ghi nhận trạng thái, cập nhật lý do vắng, GVCN/giám thị xác nhận, báo cáo chuyên cần.
- Audit/security: audit khi sửa trạng thái điểm danh sau khi đã chốt hoặc xác nhận lý do vắng.
- Hiện trạng: chưa có schema/code; cần triển khai ở Phase 3.

## 9. Quản lý phong trào

- Mục tiêu: quản lý hoạt động Đoàn, STEM, báo tường, hướng nghiệp và phong trào nội bộ.
- Actor chính: Đoàn trường/BTC phong trào, BGH, GVCN.
- Dữ liệu lõi: `school_events`, `event_registrations`, `event_results`.
- Workflow chính: tạo hoạt động, mở đăng ký, duyệt đăng ký, ghi nhận kết quả.
- Audit/security: audit khi thay đổi kết quả/giải thưởng nếu dùng cho khen thưởng.
- Hiện trạng: đã có schema/resource chung qua `school_events`.

## 10. Quản lý khen thưởng

- Mục tiêu: ghi nhận khen thưởng học sinh, giáo viên hoặc đội/nhóm.
- Actor chính: BGH, GVCN, Đoàn trường/BTC phong trào, Admin.
- Dữ liệu lõi: `commendations`, `commendation_recipients`.
- Workflow chính: tạo quyết định/ghi nhận khen thưởng, gán người nhận, liên kết hoạt động nếu có.
- Audit/security: audit khi khen thưởng ảnh hưởng hồ sơ học sinh hoặc báo cáo chính thức.
- Hiện trạng: đã có schema/resource cơ bản.

## 11. Quản lý kỷ luật

- Mục tiêu: quản lý hồ sơ vi phạm, mức độ, biện pháp xử lý và trạng thái đóng hồ sơ.
- Actor chính: Giám thị, GVCN, BGH, Admin.
- Dữ liệu lõi: `disciplinary_cases`, `disciplinary_actions`.
- Workflow chính: lập hồ sơ vi phạm, ghi biện pháp, cập nhật trạng thái, xem lịch sử học sinh.
- Audit/security: bắt buộc audit khi tạo/sửa/xóa hồ sơ hoặc biện pháp kỷ luật.
- Hiện trạng: đã có schema/resource và audit cơ bản.

## 12. Quản lý hội thi/hội thao cấp trường

- Mục tiêu: quản lý các hội thi/hội thao, đội tham gia, thứ hạng và giải thưởng.
- Actor chính: Đoàn trường/BTC phong trào, BGH, GVCN.
- Dữ liệu lõi: `school_events`, `event_registrations`, `event_results`.
- Workflow chính: tạo sự kiện loại `contest`/`sports`, đăng ký cá nhân/đội, ghi kết quả, chuyển kết quả sang khen thưởng nếu cần.
- Audit/security: audit khi sửa kết quả chính thức.
- Hiện trạng: đã dùng chung module hoạt động/phong trào.

## 13. Quản lý học phí/khoản thu

- Mục tiêu: quản lý khoản thu, kế hoạch thu, phiếu thu, giao dịch thanh toán và công nợ.
- Actor chính: Kế toán, BGH, Admin, Phụ huynh.
- Dữ liệu lõi: `fee_categories`, `fee_plans`, `fee_invoices`, `fee_invoice_items`, `payments`.
- Workflow chính: tạo khoản thu, lập phiếu, ghi nhận thanh toán, cập nhật trạng thái, phụ huynh xem công nợ.
- Audit/security: thu/hoàn/sửa giao dịch học phí bắt buộc audit; phụ huynh chỉ xem phiếu của con mình.
- Hiện trạng: đã có schema/resource và audit cơ bản.

## 14. Cổng phụ huynh

- Mục tiêu: phụ huynh xem dữ liệu liên quan học sinh đã liên kết.
- Actor chính: Phụ huynh.
- Dữ liệu lõi: `guardians`, `student_guardians`, `students`, `score_entries`, `conduct_scores`, `fee_invoices`, `announcements`.
- Workflow chính: xem điểm, rèn luyện, học phí/công nợ, thông báo.
- Audit/security: chỉ linked-student scope; không cho truy cập quản trị nội bộ.
- Hiện trạng: đã có `PortalController` và `Portal.tsx` cơ bản.

## 15. Cổng học sinh

- Mục tiêu: học sinh xem dữ liệu cá nhân và thông báo phù hợp.
- Actor chính: Học sinh.
- Dữ liệu lõi: `students`, `users`, `score_entries`, `conduct_scores`, `announcements`.
- Workflow chính: xem điểm, rèn luyện, thông báo, sự kiện.
- Audit/security: own-student scope; không xem dữ liệu bạn khác.
- Hiện trạng: dùng chung portal cơ bản.

## 16. Thông báo

- Mục tiêu: gửi thông báo theo đối tượng toàn trường, giáo viên, học sinh hoặc phụ huynh.
- Actor chính: Giáo vụ, Đoàn trường/BTC, BGH, Admin.
- Dữ liệu lõi: `announcements`, `announcement_targets`, `announcement_reads`, `attachments`.
- Workflow chính: soạn nháp, đăng thông báo, nhắm đối tượng, theo dõi đã đọc.
- Audit/security: audit khi thông báo có tính chính thức hoặc đính kèm nhạy cảm.
- Hiện trạng: đã có schema/resource cơ bản.

## 17. Báo cáo thống kê

- Mục tiêu: tổng hợp số liệu học vụ, điểm, rèn luyện, phong trào, học phí, chuyên cần.
- Actor chính: BGH, Giáo vụ, Kế toán theo phạm vi, GVCN theo lớp.
- Dữ liệu lõi: tổng hợp từ các bảng nghiệp vụ.
- Workflow chính: xem dashboard, lọc theo năm/học kỳ/lớp, xuất Excel/PDF khi cần.
- Audit/security: báo cáo phải tôn trọng scope quyền; export dữ liệu nhạy cảm cần kiểm soát.
- Hiện trạng: đã có báo cáo cơ bản, cần mở rộng Phase 5.

