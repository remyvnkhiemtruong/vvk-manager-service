# Module hội thi/hội thao cấp trường

## Tổng quan

Phase 8 mở rộng các bảng `events` có sẵn thành module chuyên biệt để BTC/Đoàn trường tổ chức hội thao, hội thi học thuật và hội thi phong trào. Workflow chính gồm lập kế hoạch, tạo nội dung thi, đăng ký, duyệt, chia bảng, lập lịch, nhập tỷ số/chấm điểm, trao giải, tổng kết, cộng điểm và export.

## Schema chính

- `events`: thông tin sự kiện, loại sự kiện, đơn vị tổ chức, địa điểm, đối tượng, thời gian, trạng thái, điểm rèn luyện mặc định, điểm thi đua lớp mặc định và báo cáo tổng kết.
- `event_categories`: nội dung thi, hình thức cá nhân/đội/lớp, luật thể thao hoặc chế độ chấm điểm, giới hạn số lượng, tiêu chí/điều kiện tham gia.
- `event_registrations`, `event_teams`, `event_team_members`: đăng ký cá nhân/đội/lớp, thành viên đội, trạng thái duyệt.
- `event_schedules`, `event_matches`, `event_match_sets`, `event_group_standings`: lịch đấu, tỷ số, điểm set/lượt, bảng xếp hạng vòng bảng.
- `event_category_criteria`, `event_judge_scores`, `event_results`: tiêu chí, điểm giám khảo, kết quả/xếp hạng.
- `event_awards`, `event_award_recipients`: giải thưởng và người nhận giải.
- `event_class_scores`, `event_point_applications`: điểm thi đua lớp và audit chống cộng trùng.
- `event_files`: file kế hoạch, thể lệ, minh chứng và báo cáo lưu private disk.

## Workflow

1. BTC/Admin/BGH tạo sự kiện và upload file kế hoạch/thể lệ.
2. BTC tạo nội dung thi, chọn `sport` cho hội thao hoặc `judged` cho hội thi chấm điểm.
3. Học sinh/GVCN/BTC đăng ký theo cấu hình cá nhân, đội hoặc tập thể lớp.
4. GVCN duyệt đăng ký thuộc lớp chủ nhiệm; BTC duyệt toàn bộ.
5. Hội thao dùng bốc thăm/chia bảng để sinh đội trong bảng, lịch và trận.
6. Trọng tài/BTC nhập tỷ số; hệ thống tính standings theo luật môn.
7. Hội thi chấm điểm lưu điểm từng tiêu chí theo từng giám khảo; mặc định lấy trung bình, có thể bật bỏ điểm cao/thấp.
8. BTC trao giải và chuyển sự kiện sang `summarized`.
9. Khi tổng kết, hệ thống tạo `conduct_records`, `event_class_scores`, `rewards`, `event_award_recipients` và ghi `event_point_applications` để không cộng trùng.

## Luật hỗ trợ

- Bóng đá: thắng/hòa/thua tính 3/1/0; xếp theo điểm, hiệu số, bàn thắng; nếu vẫn hòa thì đánh dấu cần BTC chốt.
- Bóng chuyền, cầu lông, đá cầu: best-of-3, tính set thắng, hiệu số set, hiệu số điểm.
- Kéo co: best-of-3 lượt kéo.
- Cờ vua/cờ tướng: Swiss 5 ván ở mức ghép cặp cơ bản, tính 1/0.5/0 và Buchholz.
- Chạy tiếp sức và nội dung đặc thù: hỗ trợ nhập kết quả thủ công hoặc xếp theo điểm/thời gian do BTC nhập.

## API

- `GET/POST/PUT/DELETE /api/events`
- `POST /api/events/{event}/files`, `GET /api/events/{event}/files/{file}`
- `GET/POST /api/events/{event}/categories`, `PUT /api/event-categories/{category}/criteria`
- `GET/POST /api/events/{event}/registrations`
- `POST /api/event-registrations/{registration}/approve|reject|cancel`
- `GET /api/events/{event}/teams`
- `POST /api/events/{event}/groups/draw`
- `GET/POST /api/events/{event}/schedules`
- `GET /api/events/{event}/matches`, `POST /api/event-matches/{match}/score`
- `GET/POST /api/events/{event}/scoring`
- `POST /api/events/{event}/results`
- `GET/POST /api/events/{event}/awards`
- `POST /api/events/{event}/summarize`
- `GET /api/events/{event}/exports/{registrations|schedule|results|rankings|awards}?format=xlsx|pdf`

## Phân quyền

- Admin/BGH: xem và quản lý toàn bộ.
- Đoàn trường/BTC: tạo sự kiện, duyệt, chia bảng, lập lịch, nhập kết quả, chấm điểm, trao giải, tổng kết.
- GVCN: đăng ký lớp/học sinh lớp chủ nhiệm và duyệt đăng ký thuộc lớp mình.
- Học sinh: xem và đăng ký trong phạm vi mở đăng ký.
- Phụ huynh: xem sự kiện liên quan đến học sinh đã liên kết.

## Export

Excel dùng PhpSpreadsheet, PDF dùng Dompdf. Tất cả export đều đi qua endpoint có kiểm quyền và scope dữ liệu.
