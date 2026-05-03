# Module điểm rèn luyện

## Tổng quan

Phase 6 triển khai module điểm rèn luyện cho học sinh THPT Võ Văn Kiệt. Module dùng các bảng conduct hiện có và mở rộng thêm tiêu chí, sự kiện, minh chứng, duyệt sự kiện, tổng hợp học kỳ, điều chỉnh thủ công, nhận xét GVCN, khóa điểm và báo cáo.

Dữ liệu demo phải là dữ liệu giả với mã `DEMO`, email `.test` hoặc `.local`. Không đưa dữ liệu học sinh thật vào seed, test hoặc tài liệu.

## Schema chính

- `conduct_rules`: danh mục tiêu chí cộng/trừ điểm, gồm mã, tên, loại `bonus`/`deduction`, điểm, mức độ, cờ cần duyệt và trạng thái.
- `conduct_records`: sự kiện rèn luyện của học sinh, gồm học sinh, lớp, ngày xảy ra, tiêu chí, điểm, mô tả, người ghi nhận và trạng thái `pending`, `approved`, `rejected`, `cancelled`.
- `conduct_record_evidences`: minh chứng file/ảnh lưu trên disk private, chỉ tải qua endpoint có kiểm quyền.
- `conduct_score_summaries`: tổng hợp theo học sinh/học kỳ, gồm điểm nền, điểm cộng, điểm trừ, điểm điều chỉnh, điểm cuối, xếp loại, nhận xét và trạng thái khóa.
- `conduct_adjustments`: lịch sử điều chỉnh điểm thủ công, luôn có người sửa, điểm cũ/mới và lý do.
- `conduct_approval_logs`: lịch sử duyệt/từ chối sự kiện rèn luyện.

## Cấu hình công thức

Cấu hình nằm trong `config/school.php`, khóa `conduct`.

Mặc định:

- `base_score = 100`
- `min_score = 0`
- `max_score = 100`
- Xếp loại: `Tốt 90-100`, `Khá 75-89`, `Trung bình 50-74`, `Yếu 0-49`

Công thức:

```text
final_score = clamp(base_score + bonus_points - minus_points + adjustments, min_score, max_score)
```

Chỉ bản ghi `conduct_records.status = approved` được tính vào `bonus_points` và `minus_points`.

## Quyền và phạm vi dữ liệu

- Admin: toàn quyền.
- BGH: xem toàn trường, duyệt/từ chối, khóa/mở khóa và điều chỉnh.
- GVCN: quản lý lớp chủ nhiệm, duyệt sự kiện lớp, nhận xét cuối kỳ và khóa điểm; không mở khóa sau khi đã khóa.
- Giáo viên bộ môn: ghi nhận sự kiện cho học sinh thuộc lớp/học kỳ có phân công giảng dạy.
- Giám thị: ghi nhận vi phạm nề nếp.
- Đoàn trường: ghi nhận phong trào, hoạt động Đoàn và điểm cộng liên quan.
- Giáo vụ: giữ quyền xem tổng hợp rèn luyện hiện có.
- Phụ huynh: chỉ xem điểm/timeline học sinh đã liên kết.
- Học sinh: chỉ xem điểm/timeline cá nhân.

Server luôn kiểm permission và scope. UI chỉ hỗ trợ ẩn/hiện thao tác, không thay thế kiểm quyền.

## Luồng nghiệp vụ

Ghi nhận sự kiện:

1. Người dùng chọn năm học, học kỳ, lớp, học sinh, tiêu chí và ngày xảy ra.
2. Nếu tiêu chí cần duyệt, mức độ `major/serious`, hoặc điểm tuyệt đối từ ngưỡng cấu hình, bản ghi vào `pending`.
3. Các bản ghi nhỏ hợp lệ được auto-approved và tính điểm ngay.
4. Mọi thao tác tạo/sửa/hủy ghi `audit_logs`.

Duyệt sự kiện:

1. GVCN duyệt sự kiện lớp chủ nhiệm; BGH/Admin duyệt toàn trường.
2. Duyệt chuyển trạng thái sang `approved` và tính lại summary.
3. Từ chối lưu lý do và không tính điểm.

Điều chỉnh và khóa:

1. Điều chỉnh thủ công bắt buộc có lý do.
2. Điều chỉnh tạo `conduct_adjustments` và `audit_logs`.
3. Khi summary đã khóa, thao tác thường bị chặn.
4. Sau khi khóa, chỉ Admin/BGH được mở khóa.

## API chính

- `GET /api/conduct/rules`
- `POST /api/conduct/rules`
- `PUT /api/conduct/rules/{rule}`
- `DELETE /api/conduct/rules/{rule}`
- `GET /api/conduct/records`
- `POST /api/conduct/records`
- `PUT /api/conduct/records/{record}`
- `POST /api/conduct/records/{record}/approve`
- `POST /api/conduct/records/{record}/reject`
- `POST /api/conduct/records/{record}/cancel`
- `GET /api/conduct/summaries`
- `GET /api/conduct/classes/{class}/summaries`
- `POST /api/conduct/summaries/recompute`
- `PUT /api/conduct/summaries/{summary}/adjust`
- `PUT /api/conduct/summaries/{summary}/comment`
- `POST /api/conduct/summaries/{summary}/lock`
- `POST /api/conduct/summaries/{summary}/unlock`
- `GET /api/conduct/students/{student}/timeline`
- `GET /api/conduct/records/{record}/evidences/{evidence}`
- `GET /api/conduct/reports`

Ví dụ tạo sự kiện:

```json
{
  "school_year_id": 1,
  "semester_id": 2,
  "class_id": 1,
  "student_id": 1,
  "conduct_rule_id": 3,
  "points": -3,
  "recorded_date": "2026-03-15",
  "description": "Đi học trễ đầu buổi"
}
```

Ví dụ điều chỉnh:

```json
{
  "points_delta": 5,
  "reason": "Bổ sung điểm hỗ trợ bạn học tập đã được xác minh"
}
```

## UI

- `/conduct/rules`: cấu hình tiêu chí và xem thang xếp loại.
- `/conduct/records`: ghi nhận vi phạm/khen thưởng.
- `/conduct/approvals`: duyệt sự kiện chờ xử lý.
- `/conduct/classes`: bảng điểm rèn luyện theo lớp.
- `/conduct/students/{student}`: timeline rèn luyện học sinh.
- `/conduct/comments`: nhận xét cuối kỳ.
- `/conduct/locks`: khóa/mở khóa điểm rèn luyện.
- `/conduct/reports`: báo cáo rèn luyện.

## Kiểm thử

Nhóm test cần duy trì:

- Công thức tính điểm và clamp min/max.
- Chỉ record `approved` được tính.
- GVBM ghi nhận đúng phân công, ngoài phân công bị 403.
- GVCN duyệt đúng lớp chủ nhiệm.
- BGH/Admin khóa, mở khóa và điều chỉnh.
- Phụ huynh/học sinh chỉ xem đúng scope.
- Điều chỉnh tạo `conduct_adjustments` và `audit_logs`.

Lệnh kiểm tra:

```bash
php artisan test
npm run lint
npm run build
```
