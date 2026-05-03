# Module diem so hoc tap

## Tong quan

Phase 5 them module diem hoc tap chuyen biet cho Truong THPT Vo Van Kiet. Module dung cac bang nen da co va mo rong them khoa diem, cot diem theo lop/mon/hoc ky, lich su sua diem, import/export Excel va bao cao hoc tap.

Du lieu demo phai tiep tuc la du lieu gia, dung ma `DEMO` va email `.test` hoac `.local`. Khong nhap du lieu hoc sinh that vao moi truong dev/test.

## Schema va trang thai

Bang chinh:

- `score_types`: loai diem, he so, kieu nhap `numeric` hoac `comment`, co tinh vao diem trung binh hay khong.
- `score_columns`: cot diem theo nam hoc, hoc ky, lop, mon va loai diem.
- `student_scores`: diem hoac nhan xet cua tung hoc sinh theo cot diem.
- `score_change_logs`: lich su sua diem, gom gia tri cu, gia tri moi, nguoi sua, thoi gian va ly do.
- `score_lock_requests`: yeu cau mo khoa cot diem va ket qua xu ly.

Trang thai khoa cot diem:

- `open`: dang mo, giao vien duoc nhap/sua trong pham vi phan cong.
- `locked`: da khoa, khong duoc sua diem.
- `unlock_requested`: da co yeu cau mo khoa, cho giao vu/BGH xu ly.

## Cau hinh cong thuc

Cau hinh nam trong `config/school.php`, khoa `assessment`.

Loai diem mac dinh:

- `TX`: diem thuong xuyen, he so 1, tinh diem trung binh.
- `GK`: diem giua ky, he so 2, tinh diem trung binh.
- `CK`: diem cuoi ky, he so 3, tinh diem trung binh.
- `NX`: diem nhan xet, khong tinh diem trung binh.

Diem trung binh mon duoc tinh bang tong `diem * he_so` chia tong `he_so`. Cac mon co `subjects.assessment_mode = comment` va cac loai diem `counts_toward_average = false` bi bo qua khi tinh trung binh.

## Quyen va scope du lieu

- Admin/BGH: xem va quan ly toan truong theo quyen duoc cap.
- Giao vu: xem diem toan truong, quan ly cot diem va duyet mo khoa cot diem.
- Giao vien bo mon: nhap/sua/import diem cho lop-mon-hoc ky nam trong `teaching_assignments`; duoc yeu cau mo khoa khi cot da khoa.
- GVCN: xem tong hop diem lop chu nhiem, khong nhap diem hoc tap.
- Phu huynh: chi xem diem hoc sinh da lien ket qua `student_guardians`.
- Hoc sinh: chi xem diem cua chinh minh.

Tat ca route backend kiem tra permission va scope tren server. UI chi an/hien nut cho tien thao tac, khong thay the kiem tra quyen.

## Luong nghiep vu

Nhap diem:

1. Chon nam hoc, hoc ky, lop va mon.
2. He thong dung danh sach hoc sinh dang hoc trong lop/hoc ky va cac cot diem dang active.
3. GVBM nhap diem so hoac nhan xet theo tung cot.
4. Neu sua diem da co, bat buoc nhap ly do.
5. Moi thay doi ghi `score_change_logs` va `audit_logs`.

Khoa/mo khoa:

1. Giao vu/BGH khoa cot diem sau khi chot.
2. GVBM khong the sua/import cot da khoa.
3. GVBM gui yeu cau mo khoa kem ly do.
4. Giao vu/BGH duyet hoac tu choi. Ket qua duoc ghi audit.

## Import va export Excel

Import:

- Endpoint web: `POST /assessment/scores/import`.
- Endpoint API: `POST /api/assessment/scores/import`.
- Bat buoc chon `school_year_id`, `semester_id`, `class_id`, `subject_id`.
- Bat buoc co `revision_reason`.
- File gom cot `student_code`; cac cot diem dung ma trong `score_columns.code`, vi du `TX1`, `GK`, `CK`, `NX`.
- Neu diem da ton tai theo `student_id + score_column_id`, he thong cap nhat va ghi lich su sua diem.
- Dong khong tim thay hoc sinh trong lop duoc bo qua va tra ve trong `skipped`.

Export:

- Endpoint web: `GET /assessment/scores/export`.
- Endpoint API: `GET /api/assessment/classes/{class}/scores/export`.
- File gom STT, ma hoc sinh, ho ten, cac cot diem, diem trung binh mon.

## API chinh

- `GET /api/assessment/scorebooks`
- `GET /api/assessment/scorebooks/{class}/{subject}/{semester}`
- `POST /api/assessment/score-columns`
- `PUT /api/assessment/score-columns/{column}`
- `DELETE /api/assessment/score-columns/{column}`
- `PUT /api/assessment/scores/bulk`
- `POST /api/assessment/score-columns/{column}/lock`
- `POST /api/assessment/score-columns/{column}/request-unlock`
- `POST /api/assessment/score-columns/{column}/approve-unlock`
- `POST /api/assessment/score-columns/{column}/reject-unlock`
- `POST /api/assessment/scores/import`
- `GET /api/assessment/classes/{class}/scores/export`
- `GET /api/assessment/students/{student}/scores`
- `GET /api/assessment/score-revisions`
- `GET /api/assessment/reports`

Vi du payload nhap diem:

```json
{
  "school_year_id": 1,
  "semester_id": 2,
  "class_id": 1,
  "subject_id": 1,
  "revision_reason": "Dieu chinh diem nhap nham",
  "scores": [
    {
      "student_id": 1,
      "score_column_id": 10,
      "score": 8.5,
      "status": "submitted"
    }
  ]
}
```

## UI

- `/assessment/entry`: nhap diem theo lop/mon.
- `/assessment/classes`: bang diem lop.
- `/assessment/students/{student}`: chi tiet diem hoc sinh.
- `/assessment/revisions`: lich su sua diem.
- `/assessment/score-columns`: cau hinh cot diem va xu ly khoa/mo khoa.
- `/assessment/reports`: bao cao diem.

## Kiem thu

Can chay:

- `php artisan test`
- `npm run lint`
- `npm run build`

Nhom test can bao phu nhap diem dung/sai phan cong, khoa cot diem, revision/audit, scope phu huynh/hoc sinh, export Excel va cong thuc tinh diem trung binh.
