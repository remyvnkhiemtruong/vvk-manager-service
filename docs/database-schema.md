# Database schema Phase 2

## 1. Muc tieu

Phase 2 thiet lap baseline database cho he thong quan ly noi bo Truong THPT Vo Van Kiet. Schema duoc thiet ke de mo rong theo nhieu nam hoc, nhieu hoc ky, nhieu vai tro va cac nghiep vu nhay cam co audit/revision.

Nguyen tac quan trong:

- PostgreSQL la database chinh trong Docker/local.
- Moi thay doi schema phai di qua Laravel migration.
- Seed chi dung du lieu gia voi ma demo va email `.local`/`.test`.
- `students` khong gan cung voi `classes`.
- Quan he hoc sinh - lop theo nam hoc/hoc ky nam o `student_class_enrollments`.
- Thao tac nhay cam phai co `audit_logs` hoac bang revision/log rieng.

## 2. Nhom bang he thong

- `users`: tai khoan dang nhap, status, soft delete.
- `roles`: vai tro nhu Admin, BGH, Giao vu, GVCN, Giao vien bo mon, Doan truong, Giam thi, Ke toan, Phu huynh, Hoc sinh.
- `permissions`: permission key dang `module.resource.action`.
- `user_roles`: gan nhieu vai tro cho mot user.
- `role_permissions`: gan nhieu permission cho mot role.
- `audit_logs`: log bat bien o tang ung dung cho thao tac nhay cam, gom actor, action, subject, before/after, IP, user agent, metadata.
- `login_logs`: ghi nhan dang nhap thanh cong/that bai.

`audit_logs`, `login_logs`, `user_roles`, `role_permissions` khong soft delete.

## 3. Nhom truong hoc

- `school_years`: nam hoc, ngay bat dau/ket thuc, active/status.
- `semesters`: hoc ky thuoc nam hoc, term number, active/status.
- `grades`: khoi 10, 11, 12.
- `classes`: lop hoc theo nam hoc va khoi, co GVCN qua `homeroom_teacher_id`.
- `subjects`: danh muc mon hoc.
- `teachers`: ho so giao vien/nhan su, thay the concept `staff` cu; van giu `staff_code` nullable de tuong thich code hien tai.
- `teaching_assignments`: phan cong giao vien - lop - mon - hoc ky.

Quan he chinh:

- `semesters.school_year_id -> school_years.id`
- `classes.school_year_id -> school_years.id`
- `classes.grade_id -> grades.id`
- `classes.homeroom_teacher_id -> teachers.id`
- `teaching_assignments.teacher_id -> teachers.id`
- `teaching_assignments.class_id -> classes.id`
- `teaching_assignments.subject_id -> subjects.id`

## 4. Nhom hoc sinh

- `students`: ho so hoc sinh; khong co cot `class_id`.
- `guardians`: ho so phu huynh/nguoi giam ho.
- `student_guardians`: lien ket nhieu phu huynh voi nhieu hoc sinh.
- `student_documents`: metadata giay to ho so hoc sinh.
- `student_class_enrollments`: xep lop theo `student_id`, `class_id`, `school_year_id`, `semester_id`, trang thai va ngay xep lop.

Rang buoc quan trong:

- Mot hoc sinh chi co mot enrollment cho moi hoc ky: unique `student_id + school_year_id + semester_id`.
- Chuyen lop/chuyen trang thai khong sua cot tren `students`; tao/cap nhat ban ghi enrollment theo ky.

## 5. Nhom diem hoc tap

- `score_types`: loai diem/he so.
- `score_columns`: cot diem theo nam hoc, hoc ky, mon va loai diem.
- `student_scores`: diem hoc sinh theo nam hoc, hoc ky, lop, mon, loai/cot diem.
- `score_change_logs`: revision log cho thay doi diem.
- `academic_results`: tong ket hoc tap theo ky.
- `teacher_comments`: nhan xet cua giao vien/GVCN.

Audit/revision:

- `student_scores` la bang nghiep vu nhay cam, co soft delete va audit qua `audit_logs`.
- Moi cap nhat diem tao ban ghi `score_change_logs` voi before/after va reason.
- Giao vien bo mon chi duoc thao tac diem trong pham vi `teaching_assignments`.

## 6. Nhom diem ren luyen

- `conduct_rules`: tieu chi cong/tru diem ren luyen.
- `conduct_records`: ghi nhan diem/tieu chi chi tiet.
- `conduct_score_summaries`: tong hop diem va xep loai ren luyen theo hoc sinh/hoc ky.
- `conduct_rating_rules`: quy tac xep loai theo nguong diem.
- `conduct_adjustments`: dieu chinh diem ren luyen.
- `conduct_approval_logs`: log duyet/chot diem ren luyen.

`conduct_adjustments` va `conduct_approval_logs` khong soft delete de giu lich su xu ly.

## 7. Nhom diem danh

- `attendance_sessions`: phien diem danh theo nam hoc, hoc ky, lop, mon, giao vien, ngay/tiet.
- `attendance_records`: trang thai tung hoc sinh trong phien diem danh.

Trang thai goi y: `present`, `late`, `excused`, `absent`.

## 8. Nhom phong trao

- `campaigns`: phong trao nhu truong hoc khong dien thoai, STEM, bao tuong, huong nghiep.
- `campaign_criteria`: tieu chi cham diem phong trao.
- `campaign_participants`: ca nhan/lop/nhom tham gia.
- `campaign_results`: ket qua theo nguoi/lop/nhom.
- `campaign_class_scores`: diem phong trao theo lop va tieu chi.

Bang `campaign_criteria` co ten so it theo yeu cau Phase 2, nen cac FK `campaign_criteria_id` phai chi dinh ro table nay trong migration.

## 9. Nhom hoi thi/hoi thao

- `events`: hoi thi/hoi thao/su kien cap truong.
- `event_categories`: noi dung thi dau/thi.
- `event_organizers`: giao vien/nhan su to chuc.
- `event_registrations`: dang ky ca nhan/lop.
- `event_teams`: doi thi dau/doi thi.
- `event_team_members`: thanh vien doi.
- `event_schedules`: lich thi dau/lich thi.
- `event_matches`: tran dau/luot thi.
- `event_scores`: diem/ti so.
- `event_judges`: giam khao/trong tai.
- `event_results`: ket qua xep hang.
- `event_awards`: giai thuong.

## 10. Nhom khen thuong/ky luat

- `reward_types`: loai khen thuong.
- `rewards`: khen thuong hoc sinh, giao vien hoac lop/doi.
- `discipline_types`: loai vi pham/ky luat.
- `discipline_cases`: ho so vi pham.
- `discipline_actions`: bien phap xu ly.

Tao/sua/xoa ky luat la thao tac nhay cam va phai co audit log.

## 11. Nhom hoc phi

- `fee_types`: loai khoan thu.
- `fee_plans`: ke hoach thu theo nam hoc/hoc ky.
- `student_fees`: khoan phai thu theo hoc sinh.
- `payments`: giao dich thu/hoan tien.
- `receipts`: bien nhan.
- `fee_exemptions`: mien/giam.

Thu tien cap nhat `student_fees.paid_amount` va `student_fees.status`; moi giao dich payment phai co audit.

## 12. Nhom thong bao

- `announcements`: thong bao theo doi tuong.
- `announcement_recipients`: nguoi/lop/hoc sinh nhan thong bao.
- `notification_reads`: trang thai da doc.

Thong bao co the gan theo nam hoc/hoc ky de loc theo boi canh van hanh.

## 13. Index va rang buoc

Migration them index cho cac cot thuong filter khi co mat:

- `school_year_id`
- `semester_id`
- `class_id`
- `student_id`
- `teacher_id`
- `status`
- `created_at`

Composite index/unique quan trong:

- `student_class_enrollments`: unique `student_id, school_year_id, semester_id`.
- `teaching_assignments`: unique `teacher_id, class_id, subject_id, semester_id`.
- `student_scores`: index `school_year_id, semester_id`, `class_id`, `student_id`.
- `student_fees`: index `school_year_id, semester_id`, `class_id`, `student_id`.
- `campaign_class_scores`: unique `campaign_id, campaign_criteria_id, class_id`.

## 14. Soft delete policy

Co soft delete:

- Ho so/cau hinh co the thay doi: `users`, `school_years`, `semesters`, `grades`, `classes`, `subjects`, `teachers`, `students`, `guardians`.
- Bang nghiep vu mutable: enrollments, diem, ren luyen, diem danh, phong trao, su kien, khen thuong, ky luat, hoc phi, thong bao.

Khong soft delete:

- Log va pivot bat bien: `audit_logs`, `login_logs`, `user_roles`, `role_permissions`, `score_change_logs`, `conduct_approval_logs`.

## 15. Seed data gia

`DatabaseSeeder` tao du lieu demo an toan:

- 1 nam hoc.
- 2 hoc ky.
- 3 khoi 10, 11, 12.
- 6 lop gia.
- 30 hoc sinh gia.
- 10 giao vien gia.
- 10 vai tro va bo permissions co ban.
- Tieu chi diem ren luyen mau.
- 3 phong trao mau.
- 1 hoi thao cap truong mau co category, doi, lich, tran, diem, giam khao, ket qua va giai thuong.

Khong co du lieu hoc sinh that trong seed.

## 16. Lenh migrate/seed

Local neu da co PHP/Composer:

```bash
composer install
php artisan migrate:fresh --seed
php artisan migrate --seed
php artisan test
```

Docker:

```bash
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php artisan migrate:fresh --seed
docker compose run --rm app php artisan migrate --seed
docker compose run --rm app php artisan test
```

Chay day du service:

```bash
docker compose up --build
```

Neu chua co `.env`, tao tu file mau va sinh key:

```bash
cp .env.example .env
php artisan key:generate
```

Tren Windows PowerShell:

```powershell
Copy-Item .env.example .env
docker compose run --rm app php artisan key:generate --force
```
