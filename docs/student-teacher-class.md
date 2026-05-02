# Student, Teacher, and Class Management

## Overview

Phase 4 adds a dedicated Academic module for school operations:

- Student CRUD and detail pages.
- Guardian CRUD and student-guardian links.
- Teacher CRUD.
- School years, semesters, grades, and classes.
- Student enrollment by school year and semester.
- Class transfer history.
- Homeroom teacher assignment.
- Subject teacher assignment.
- Student Excel import and class-based Excel export.

All seed/import examples must remain fake demo data. Do not import real student records into development or tests.

## Web UI

Academic UI routes are protected by Laravel session auth and RBAC.

- `/academic/students`: student list with search, class filter, pagination, Excel import, and delete confirmation.
- `/academic/students/create`: create student.
- `/academic/students/{id}`: student detail, guardians, enrollment history, transfer history.
- `/academic/students/{id}/edit`: edit student.
- `/academic/teachers`: teacher list and inline CRUD form.
- `/academic/classes`: class list and inline CRUD form.
- `/academic/classes/{id}`: class detail with students, GVCN, subject teachers, and sĩ số.
- `/academic/classes/{id}/students/export`: export active student list to Excel.

Navigation points `students`, `teachers`, and `classes` to the dedicated Academic pages. Generic `/manage/{resource}` remains as fallback for other resources.

## API

Academic JSON API routes are under `/api/academic` and require `Authorization: Bearer <JWT access token>`.

Important endpoints:

- `GET /api/academic/students`
- `POST /api/academic/students`
- `GET /api/academic/students/{student}`
- `PUT /api/academic/students/{student}`
- `DELETE /api/academic/students/{student}`
- `POST /api/academic/students/import`
- `GET /api/academic/classes/{class}/students/export`
- `POST /api/academic/students/{student}/guardians`
- `DELETE /api/academic/students/{student}/guardians/{guardian}`
- `POST /api/academic/students/{student}/enrollments`
- `POST /api/academic/students/{student}/transfer`
- CRUD routes for guardians, teachers, school years, semesters, grades, classes, and teaching assignments.

List endpoints support pagination through `per_page` and common filters such as `search`, `status`, `class_id`, `school_year_id`, `semester_id`, and `grade_id` where applicable.

## Excel Import

Student import accepts `.xlsx`, `.xls`, and `.csv`. Supported columns:

- `student_code`
- `full_name`
- `gender`
- `birth_date`
- `phone`
- `email`
- `address`
- `class_code`
- `guardian_name`
- `guardian_phone`
- `guardian_email`
- `guardian_relationship`

`student_code` is the natural key. If a matching student exists, the import updates that record. If not, it creates a new student. If `class_code` matches a class code or name, the student is enrolled in the active school year and semester. If guardian fields are present, the guardian is created or reused and linked to the student.

## Authorization and Scope

Server-side RBAC and data scope are authoritative:

- Admin, BGH, and Giáo vụ can manage academic data according to permissions.
- GVCN only sees homeroom classes and students in those classes.
- Subject teachers only see assigned classes and subject assignments.
- Parents and students do not access internal academic management pages or API beyond portal scope.

UI hiding is only ergonomic. Every route still checks permission and scope on the server.

## Audit

The system writes audit logs for:

- Student create/update/delete.
- Student import create/update.
- Student-guardian link/unlink.
- Student enrollment create/update.
- Class transfer.
- Homeroom assignment.
- Teaching assignment create/update/delete.
- Teacher, guardian, class, school year, semester, and grade CRUD.

Class transfer is transactional: the old active enrollment is marked transferred, the new active enrollment is written, and a row is added to `student_class_transfer_logs`.
