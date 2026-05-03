# Module phong trào, thi đua và hoạt động Đoàn

## Mục tiêu

Phase 7 bổ sung module quản lý phong trào/hoạt động cho Trường THPT Võ Văn Kiệt: STEM, báo tường, văn nghệ 20/11, tư vấn hướng nghiệp, an toàn giao thông, trường học không điện thoại, hoạt động tình nguyện, thi đua tuần/tháng và hoạt động Đoàn.

Module dùng workflow riêng thay vì CRUD dùng chung vì có đăng ký, duyệt, chấm điểm, minh chứng, xếp hạng, tổng kết và tự động cộng điểm.

## Dữ liệu chính

- `campaigns`: thông tin hoạt động, loại, đơn vị tổ chức, thời gian, đối tượng, trạng thái, điểm rèn luyện mặc định, điểm thi đua lớp mặc định và báo cáo tổng kết.
- `campaign_criteria`: tiêu chí chấm điểm gồm tham gia đầy đủ, chất lượng, sáng tạo, kỷ luật, đúng hạn hoặc tiêu chí tùy chỉnh.
- `campaign_participants`: đăng ký cá nhân, đội/nhóm hoặc tập thể lớp.
- `campaign_participant_members`: thành viên của đội/nhóm.
- `campaign_results`: tổng điểm, xếp hạng, giải thưởng và điểm cộng dự kiến.
- `campaign_result_scores`: điểm chi tiết theo từng tiêu chí.
- `campaign_files`: file kế hoạch và minh chứng, lưu private disk và chỉ tải qua endpoint có kiểm quyền.
- `campaign_point_applications`: log chống cộng trùng khi tổng kết.
- `campaign_class_scores`: điểm thi đua lớp do phong trào tạo ra.

Trạng thái hoạt động chuẩn:

- `draft`: nháp.
- `registration_open`: mở đăng ký.
- `in_progress`: đang diễn ra.
- `ended`: kết thúc.
- `summarized`: đã tổng kết.

## Workflow

1. Admin/BGH hoặc Đoàn trường/BTC tạo hoạt động, chọn loại, đối tượng, hình thức đăng ký và upload file kế hoạch.
2. Học sinh đăng ký cá nhân/đội nhóm khi hoạt động mở đăng ký; GVCN có thể đăng ký học sinh hoặc tập thể lớp chủ nhiệm.
3. GVCN duyệt đăng ký thuộc lớp chủ nhiệm; BTC/Admin/BGH duyệt toàn bộ.
4. BTC nhập kết quả theo tiêu chí, upload minh chứng, công bố kết quả và hệ thống tính tổng điểm/xếp hạng.
5. Khi BTC/Admin/BGH chốt tổng kết, hệ thống:
   - tạo `conduct_records` đã duyệt để cộng điểm rèn luyện cho học sinh;
   - cập nhật `campaign_class_scores` để cộng điểm thi đua lớp;
   - ghi `campaign_point_applications` để thao tác tổng kết chạy lại không cộng trùng;
   - ghi audit log cho tổng kết và các thao tác nhạy cảm.

## API

- `GET /api/campaigns`
- `POST /api/campaigns`
- `GET /api/campaigns/{campaign}`
- `PUT /api/campaigns/{campaign}`
- `DELETE /api/campaigns/{campaign}`
- `POST /api/campaigns/{campaign}/files`
- `GET /api/campaigns/{campaign}/files/{file}`
- `GET /api/campaigns/{campaign}/registrations`
- `POST /api/campaigns/{campaign}/registrations`
- `POST /api/campaign-registrations/{participant}/approve`
- `POST /api/campaign-registrations/{participant}/reject`
- `POST /api/campaign-registrations/{participant}/cancel`
- `GET /api/campaigns/{campaign}/criteria`
- `PUT /api/campaigns/{campaign}/criteria`
- `POST /api/campaigns/{campaign}/results`
- `PUT /api/campaigns/{campaign}/results`
- `POST /api/campaign-results/{result}/evidences`
- `GET /api/campaigns/{campaign}/rankings`
- `POST /api/campaigns/{campaign}/summarize`
- `GET /api/campaigns/{campaign}/exports/{participants|results|rankings}?format=xlsx|pdf`

## UI

- `/campaigns/dashboard`: dashboard Đoàn trường/BTC.
- `/campaigns`: danh sách phong trào.
- `/campaigns/create`, `/campaigns/{campaign}/edit`: tạo/sửa phong trào.
- `/campaigns/{campaign}/register`: đăng ký tham gia.
- `/campaigns/registrations`: duyệt đăng ký.
- `/campaigns/{campaign}/results`: nhập kết quả.
- `/campaigns/{campaign}/rankings`: xếp hạng.
- `/campaigns/{campaign}/summary`: tổng kết phong trào.

Portal phụ huynh/học sinh hiển thị hoạt động học sinh đã tham gia, trạng thái đăng ký, trạng thái hoạt động và kết quả nếu có.

## Phân quyền và audit

- Admin/BGH: xem và quản lý toàn bộ.
- Đoàn trường/BTC: tạo hoạt động, duyệt, nhập kết quả, tổng kết, export.
- GVCN: đăng ký lớp/học sinh lớp mình và duyệt đăng ký thuộc lớp chủ nhiệm.
- Học sinh: xem hoạt động mở và đăng ký cho chính mình.
- Phụ huynh: xem hoạt động của học sinh đã liên kết.

Các thao tác tạo/sửa/xóa hoạt động, duyệt/từ chối đăng ký, nhập kết quả, upload file, tổng kết và cộng điểm đều kiểm quyền server-side. Tổng kết ghi audit và dùng `campaign_point_applications` để chống cộng điểm trùng.

## Export

Excel dùng `phpoffice/phpspreadsheet`. PDF dùng `barryvdh/laravel-dompdf`; trong môi trường chưa cài package, service vẫn tạo PDF tối thiểu để endpoint không lỗi, nhưng triển khai chuẩn cần chạy `composer install`/`composer update` theo `composer.lock`.
