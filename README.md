# QR Attend

QR Attend is a PHP/MySQL attendance management system for recording student attendance with QR codes. It includes a web dashboard, student and user management, attendance exports, API-token authentication for external/mobile clients, and optional EgoSMS notifications for parents and password reset codes.

## Features

- Secure login with PHP sessions for the web dashboard.
- API token support for mobile or external clients.
- Admin and staff roles.
- Student records with course/section, parent contact details, and generated QR code values.
- QR attendance recording with duplicate same-day attendance protection.
- Parent SMS notifications when attendance is recorded.
- Password reset by SMS OTP.
- Attendance filtering and Excel-compatible export.
- Demo-data seeding for pagination and filter testing.

## Tech Stack

- PHP 8+
- MySQL or MariaDB
- PDO for database access
- XAMPP/Apache for local development
- Bootstrap and plain JavaScript/CSS assets
- EgoSMS plain HTTP API for SMS delivery

## Project Structure

```text
.
|-- assets/
|   |-- css/              Dashboard styles
|   |-- img/              Logo and favicon assets
|   `-- js/               Dashboard client logic
|-- conn/
|   |-- auth.php          Session/API token authentication helpers
|   |-- config.php        Main app configuration and .env loader
|   |-- conn.php          PDO database connection
|   |-- schema.php        Lightweight user-column migration helper
|   |-- session.php       Session bootstrap
|   |-- sms.php           EgoSMS and phone helpers
|   `-- validate.php      Request validation helpers
|-- endpoint/             JSON/action endpoints
|-- pages/                Dashboard page fragments
|-- attendance.sql        Database schema and starter data
|-- index.php             Main application entry point
|-- migrate-profile.php   Adds profile/password-reset columns if needed
|-- seed-demo-data.php    Generates demo students and attendance rows
|-- test-sms.php          EgoSMS credential and delivery test
`-- .env.example          Local configuration template
```

## Requirements

1. XAMPP with Apache and MySQL enabled.
2. PHP extensions commonly bundled with XAMPP:
   - `pdo_mysql`
   - `curl` for SMS requests
   - `mbstring` for SMS message truncation
3. A database named `attendance`, unless you change `DB_NAME`.
4. Optional: an EgoSMS account for live SMS delivery.

## Local Setup

1. Place the project in your XAMPP web root:

   ```bash
   C:\xampp\htdocs\attend
   ```

2. Start Apache and MySQL from the XAMPP Control Panel.

3. Create the database in phpMyAdmin or MySQL:

   ```sql
   CREATE DATABASE attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Import the schema:

   - Open `http://localhost/phpmyadmin`
   - Select the `attendance` database
   - Import `attendance.sql`

5. Copy the environment template:

   ```bash
   copy .env.example .env
   ```

6. Update `.env` for your local machine:

   ```env
   APP_NAME=QR Attend
   APP_URL=http://localhost/attend

   DB_HOST=localhost
   DB_NAME=attendance
   DB_USER=root
   DB_PASS=
   DB_CHARSET=utf8mb4
   ```

7. Open the app:

   ```text
   http://localhost/attend/
   ```

## Configuration

All main configuration lives in [conn/config.php](conn/config.php). Local secrets should be placed in `.env`, not committed to Git.

Configuration priority:

1. Server environment variables or `.env`.
2. Fallback values in `conn/config.php`.

Important environment values:

| Key | Purpose |
| --- | --- |
| `APP_NAME` | Display name used by the app and SMS messages. |
| `APP_URL` | Optional public base URL. |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` | Database connection settings. |
| `EGOSMS_ENABLED` | Enables/disables SMS sending. |
| `EGOSMS_USERNAME`, `EGOSMS_PASSWORD`, `EGOSMS_SENDER` | EgoSMS credentials and sender ID. |
| `SMS_NOTIFY_PARENT_ON_ATTENDANCE` | Sends an SMS after a successful attendance scan. |
| `SMS_ATTENDANCE_TEMPLATE` | Optional parent-notification message template. |
| `AUTH_PASSWORD_MIN`, `AUTH_PASSWORD_REQUIRE_LETTER`, `AUTH_PASSWORD_REQUIRE_NUMBER` | Password policy. |
| `AUTH_SESSION_NAME` | PHP session cookie name. |
| `PASSWORD_RESET_OTP_TTL`, `PASSWORD_RESET_OTP_LENGTH` | Password reset OTP settings. |
| `CORS_ENABLED`, `CORS_ALLOWED_ORIGINS` | Optional CORS support for browser clients. |

## Database

The main tables are:

- `tbl_user`: dashboard users, roles, API tokens, phone numbers, and password reset fields.
- `tbl_student`: students, parent contacts, course/section, and QR code values.
- `tbl_attendance`: attendance records linked to students.

After importing older database exports, run:

```bash
php migrate-profile.php
```

This ensures the profile and password-reset columns exist on `tbl_user`.

## Demo Data

To generate demo students and attendance rows:

```bash
php seed-demo-data.php
```

The seeder creates `SEED000001` through `SEED000048` QR values and spreads attendance across recent dates. Re-running it clears the previous `SEED*` batch before inserting a fresh one.

## SMS Setup

1. Create or confirm an EgoSMS account.
2. Put the credentials in `.env`:

   ```env
   EGOSMS_ENABLED=true
   EGOSMS_USERNAME=your_username
   EGOSMS_PASSWORD=your_password
   EGOSMS_SENDER=QRAttend
   EGOSMS_ENDPOINT=https://www.egosms.co/api/v1/plain/
   ```

3. Test credentials without sending:

   ```bash
   php test-sms.php
   ```

4. Send a real test SMS:

   ```bash
   php test-sms.php 0700123456 "QR Attend SMS test"
   ```

Phone numbers are normalized for Uganda formats such as `07XXXXXXXX`, `7XXXXXXXX`, and `2567XXXXXXXX`.

## Authentication And Roles

- `admin`: full control, including user management.
- `staff`: authenticated dashboard/API access without admin-only user management.

The login endpoint issues an API token and also starts a session:

```text
POST endpoint/login.php
```

Protected endpoints accept the PHP session or a bearer token:

```http
Authorization: Bearer <api_token>
```

Token-in-POST support is controlled by `AUTH_ALLOW_TOKEN_IN_POST`.

## Common Endpoints

Most action endpoints live in `endpoint/` and return JSON unless they are exporting a file.

| Endpoint | Purpose |
| --- | --- |
| `endpoint/login.php` | Log in and issue an API token. |
| `endpoint/logout.php` | Clear the current session/token. |
| `endpoint/me.php` | Return the authenticated user. |
| `endpoint/add-attendance.php` | Record attendance from a QR code. |
| `endpoint/add-student.php` | Add a student. |
| `endpoint/update-student.php` | Update a student. |
| `endpoint/delete-student.php` | Delete a student and their attendance rows. |
| `endpoint/export-attendance.php` | Export filtered attendance as `.xls`. |
| `endpoint/forgot-password.php` | Send password reset OTP by SMS. |
| `endpoint/reset-password.php` | Reset password with OTP. |
| `endpoint/add-user.php` | Admin-only user creation. |
| `endpoint/update-user.php` | Admin-only user update. |
| `endpoint/delete-user.php` | Admin-only user deletion. |

## Development Guidelines

- Keep secrets in `.env`; never commit real credentials, API tokens, or local config files.
- Prefer the shared helpers in `conn/`:
  - Use `conn/conn.php` for database access.
  - Use `conn/session.php` before working with sessions.
  - Use `conn/auth.php` for authentication and authorization.
  - Use `conn/validate.php` for input validation.
  - Use `conn/sms.php` for phone normalization and SMS delivery.
- Use prepared PDO statements for all database queries.
- Return JSON from action endpoints with a consistent shape:

  ```json
  {
    "success": true,
    "message": "Action completed."
  }
  ```

- Validate request methods with `require_post_method()` for mutating actions.
- Protect authenticated actions with `require_authenticated_user($conn)`.
- Protect admin-only actions with `require_admin_user($conn)`.
- Escape all user-provided output in page templates with `htmlspecialchars()`.
- Keep page fragments in `pages/` and action logic in `endpoint/`.
- Avoid editing `conn/config.php` for local settings; use `.env` instead.

## Security Checklist

- Change any imported/default admin password before using the system with real data.
- Remove or rotate any API tokens included in database dumps.
- Do not expose `attendance.sql`, `.env`, or test scripts on a public server.
- Keep `EGOSMS_PASSWORD` only in environment configuration.
- Use HTTPS in production.
- Disable detailed PHP errors in production.
- Back up the database before running imports or migrations.

## Useful Commands

```bash
# Check SMS config without sending
php test-sms.php

# Send an SMS test
php test-sms.php 0700123456

# Ensure profile/password-reset columns exist
php migrate-profile.php

# Generate demo student and attendance records
php seed-demo-data.php
```

## Deployment Notes

For production, copy the project to the server, configure the web server document root, import the database, create a production `.env`, and confirm PHP has `pdo_mysql`, `curl`, and `mbstring` enabled.

Before going live:

1. Set a strong admin password.
2. Confirm SMS sender ID approval and account balance.
3. Set correct database credentials.
4. Restrict direct access to private files such as `.env` and SQL dumps.
5. Test login, student creation, QR attendance, SMS notification, export, and password reset.

## License

No license file is currently included. Add one before distributing or publishing the project.
