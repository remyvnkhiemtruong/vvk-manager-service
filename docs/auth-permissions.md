# Authentication and Permissions

## Overview

The system uses two auth surfaces:

- Web UI: Laravel session auth for React/Inertia pages.
- Stateless API: JWT access token plus opaque refresh token for external clients.

There is no public self-registration. Accounts are created by users with `identity.users.create`.

## Web Routes

- `GET /login`: login page.
- `POST /login`: accepts `login` plus `password`; `login` can be email or username.
- `POST /logout`: ends the session.
- `GET /profile`: shows account, roles, permissions, and linked staff/student/guardian context.
- `PUT /profile/password`: changes password after validating the current password.

Passwords are hashed through Laravel hashing. The default project hash driver should remain bcrypt or argon2.

## API Routes

All API routes are under `/api/auth`.

`POST /api/auth/login`

```json
{
  "login": "admin",
  "password": "password"
}
```

Returns `access_token`, `token_type`, `expires_in`, `expires_at`, `refresh_token`, and `user`.

`POST /api/auth/refresh`

```json
{
  "refresh_token": "opaque-refresh-token"
}
```

Rotates the refresh token. The old refresh token is revoked and cannot be used again.

`POST /api/auth/logout`

Requires `Authorization: Bearer <access_token>`. If `refresh_token` is supplied, only that refresh token is revoked. If omitted, all active refresh tokens for the user are revoked.

`GET /api/auth/profile`

Requires `Authorization: Bearer <access_token>`. Returns profile, roles, permissions, and linked context.

## Token Policy

- Access token: JWT HS256, default TTL `JWT_ACCESS_TTL=900` seconds.
- Refresh token: opaque random token, stored only as SHA-256 hash, default TTL `JWT_REFRESH_TTL=43200` minutes.
- JWT secret defaults to `JWT_SECRET`, then falls back to `APP_KEY`.
- Logout revokes refresh tokens. Existing access tokens remain usable until their short expiry.

## Roles and Permissions

Role and permission keys are seeded from `config/school.php`.

- `admin`: full system access.
- `bgh`: broad management, reports, and audit view.
- `giao_vu`: academic operations and communication.
- `gvcn`: homeroom class scope.
- `giao_vien_bo_mon`: assigned class-subject scope.
- `doan_truong`: activities, competitions, movements, and related announcements.
- `giam_thi`: conduct, discipline, and attendance.
- `ke_toan`: finance module.
- `phu_huynh`: portal and linked student announcements.
- `hoc_sinh`: portal and own-student announcements.

Route middleware:

- `auth`: web session login.
- `jwt.auth`: API Bearer JWT login.
- `permission:{key}`: static permission checks.
- `resource.permission:{action}`: resource permission checks from `config/school.php`.

## Context Scope

The server applies data scope after RBAC:

- GVCN only sees/manages records tied to homeroom classes.
- Subject teachers only see/write score records for assigned class-subject pairs in `teaching_assignments`.
- Parents only see linked students through `student_guardians`.
- Students only see their own `students.user_id` context.
- Accountant access is limited by role matrix to finance resources.
- Doan truong/BTC access is limited by role matrix to activities and allowed announcements.

The UI may hide unavailable actions, but server-side permission and scope checks are authoritative.

## Audit Actions

The following auth/identity events are audited:

- `auth.login_failed`
- `auth.login`
- `auth.logout`
- `auth.password_changed`
- `users.created`, `users.updated`, `users.deleted`
- `roles.created`, `roles.updated`, `roles.deleted`
- `permissions.created`, `permissions.updated`, `permissions.deleted`

Audit snapshots redact password and token fields. User snapshots include `role_ids`; role snapshots include `permission_ids`.

## Demo Accounts

All demo accounts use password `password`.

| Username | Email | Role |
| --- | --- | --- |
| `admin` | `admin@vvk.local` | Admin |
| `bgh` | `bgh@vvk.local` | Ban giam hieu |
| `giaovu` | `giaovu@vvk.local` | Giao vu |
| `gvcn` | `gvcn@vvk.local` | Giao vien chu nhiem |
| `giaovien` | `giaovien@vvk.local` | Giao vien bo mon |
| `doantruong` | `doantruong@vvk.local` | Doan truong/BTC |
| `giamthi` | `giamthi@vvk.local` | Giam thi |
| `ketoan` | `ketoan@vvk.local` | Ke toan |
| `phuhuynh` | `phuhuynh@vvk.local` | Phu huynh |
| `hocsinh` | `hocsinh@vvk.local` | Hoc sinh |

Demo seed data uses fake `DEMO` codes and `.local` or `.test` emails only.
