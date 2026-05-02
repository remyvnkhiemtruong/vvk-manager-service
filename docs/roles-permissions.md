# Sơ đồ quyền theo vai trò

## 1. Mức quyền

- `Full`: toàn quyền xem, tạo, sửa, xóa, cấu hình và phân quyền.
- `Manage`: được quản lý dữ liệu nghiệp vụ trong phạm vi vai trò.
- `View`: được xem dữ liệu toàn module hoặc báo cáo liên quan.
- `Scoped View`: chỉ xem dữ liệu theo lớp, môn, bộ phận hoặc phạm vi được phân công.
- `Own/Linked Only`: chỉ xem dữ liệu cá nhân hoặc học sinh đã liên kết.
- `None`: không có quyền truy cập.

## 2. Ma trận quyền module

| Module | Admin | BGH | Giáo vụ | GVCN | GV bộ môn | Đoàn/BTC | Giám thị | Kế toán | Phụ huynh | Học sinh |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Người dùng & phân quyền | Full | Manage | None | None | None | None | None | None | None | None |
| Học sinh | Full | Manage | Manage | Scoped View | None | None | Scoped View | None | Own/Linked Only | Own/Linked Only |
| Giáo viên/nhân sự | Full | Manage | Manage | View | View | View | View | None | None | None |
| Năm học/học kỳ/lớp/khối | Full | Manage | Manage | Scoped View | Scoped View | View | View | None | Own/Linked Only | Own/Linked Only |
| Môn học/phân công | Full | Manage | Manage | Scoped View | Scoped View | None | None | None | None | Scoped View |
| Điểm số | Full | Manage | View | Scoped View | Manage | None | None | None | Own/Linked Only | Own/Linked Only |
| Điểm rèn luyện | Full | Manage | View | Manage | View | None | Manage | None | Own/Linked Only | Own/Linked Only |
| Điểm danh/chuyên cần | Full | Manage | Manage | Manage | Manage | None | Manage | None | Own/Linked Only | Own/Linked Only |
| Phong trào | Full | Manage | View | Scoped View | View | Manage | View | None | View | View |
| Khen thưởng | Full | Manage | View | Manage | View | Manage | View | None | Own/Linked Only | Own/Linked Only |
| Kỷ luật | Full | Manage | View | Manage | None | None | Manage | None | Own/Linked Only | Own/Linked Only |
| Hội thi/hội thao | Full | Manage | View | Scoped View | View | Manage | View | None | View | View |
| Học phí/khoản thu | Full | View | None | View | None | None | None | Manage | Own/Linked Only | Own/Linked Only |
| Cổng phụ huynh | Full | View | View | Scoped View | None | None | None | View | Own/Linked Only | None |
| Cổng học sinh | Full | View | View | Scoped View | Scoped View | View | View | None | None | Own/Linked Only |
| Thông báo | Full | Manage | Manage | View | View | Manage | View | View | Own/Linked Only | Own/Linked Only |
| Báo cáo thống kê | Full | View | View | Scoped View | Scoped View | View | Scoped View | Scoped View | None | None |
| Audit log | Full | View | None | None | None | None | None | None | None | None |

## 3. Quyền theo vai trò

### Admin

- Toàn quyền cấu hình hệ thống, user, role, permission và mọi module.
- Được xem audit log và báo cáo toàn trường.
- Chịu trách nhiệm cấp tài khoản, khóa tài khoản, phân quyền và xử lý cấu hình hệ thống.

### BGH

- Quản lý/xem hầu hết dữ liệu học vụ, điểm, rèn luyện, kỷ luật, phong trào, học phí và báo cáo.
- Được xem audit log để giám sát thao tác nhạy cảm.
- Không nên trực tiếp giữ secret hệ thống hoặc can thiệp database ngoài ứng dụng.

### Giáo vụ

- Quản lý hồ sơ học vụ: học sinh, giáo viên, lớp, năm học, học kỳ, phân công.
- Xem điểm và điểm rèn luyện phục vụ tổng hợp.
- Quản lý thông báo học vụ.
- Không quản lý phân quyền hệ thống trừ khi được Admin ủy quyền.

### GVCN

- Xem học sinh/lớp mình chủ nhiệm.
- Xem điểm lớp chủ nhiệm.
- Quản lý điểm rèn luyện, khen thưởng, kỷ luật trong phạm vi lớp.
- Xem thông báo và báo cáo lớp.

### Giáo viên bộ môn

- Xem lớp, môn và phân công của mình.
- Nhập/sửa điểm trong phạm vi lớp-môn-học kỳ được phân công.
- Xem dữ liệu học sinh cần thiết cho giảng dạy.
- Không xem/sửa điểm ngoài phân công.

### Đoàn trường/BTC phong trào

- Quản lý phong trào, hoạt động Đoàn, STEM, báo tường, hướng nghiệp, hội thi/hội thao.
- Quản lý hoặc đề xuất khen thưởng liên quan hoạt động.
- Gửi thông báo cho nhóm đối tượng phù hợp.

### Giám thị

- Quản lý điểm rèn luyện, điểm danh/chuyên cần và kỷ luật theo phạm vi được phân công.
- Xem thông tin học sinh cần thiết cho nề nếp.
- Không quản lý điểm học tập hoặc học phí.

### Kế toán

- Quản lý khoản thu, phiếu thu, giao dịch thanh toán và công nợ.
- Xem báo cáo tài chính/học phí theo phạm vi.
- Không xem/sửa điểm học tập, điểm rèn luyện hoặc kỷ luật nếu không được cấp quyền riêng.

### Phụ huynh

- Chỉ xem dữ liệu học sinh đã liên kết: điểm, rèn luyện, chuyên cần, học phí, thông báo.
- Không có quyền vào màn quản trị nội bộ.
- Không xem dữ liệu học sinh khác.

### Học sinh

- Chỉ xem dữ liệu cá nhân: điểm, rèn luyện, chuyên cần, thông báo và hoạt động liên quan.
- Không có quyền quản trị hoặc xem dữ liệu học sinh khác.

## 4. Thao tác bắt buộc audit

Các thao tác sau phải ghi audit log với actor, action, subject, before/after, IP/user agent nếu có:

- Tạo/sửa/xóa user, role, permission, gán vai trò.
- Liên kết hoặc hủy liên kết tài khoản phụ huynh/học sinh.
- Sửa điểm số và thay đổi trạng thái điểm.
- Sửa điểm rèn luyện và trạng thái duyệt.
- Sửa điểm danh/chuyên cần sau khi đã chốt.
- Tạo/sửa/xóa hồ sơ kỷ luật hoặc biện pháp kỷ luật.
- Thu, sửa, hoàn hoặc xóa giao dịch học phí.
- Sửa kết quả hội thi/hội thao đã công bố.

## 5. Scope dữ liệu bắt buộc

- Giáo viên bộ môn: theo `teaching_assignments`.
- GVCN: theo lớp chủ nhiệm và năm học hiện hành.
- Giám thị: theo lớp/khối/khu vực được phân công khi có cấu hình.
- Kế toán: theo khoản thu/học kỳ/năm học được giao; mặc định toàn trường nếu chưa có phân vùng.
- Phụ huynh: theo `student_guardians`.
- Học sinh: theo `students.user_id`.

