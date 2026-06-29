# Attendance API

A Laravel-based REST API for employee attendance, leave requests, leave balances, and role-based workforce management.

The API uses Laravel Sanctum for token authentication and Spatie Laravel Permission for role-based access control.

## Features

- Token-based login and logout with Laravel Sanctum
- Role-based access for `admin`, `management`, and `employee`
- Employee and management user registration
- Attendance marking with check-in and check-out times
- Today's attendance status lookup
- Lunch break start/stop tracking
- Leave request creation, review, approval, rejection, and cancellation
- Leave balance tracking for sick, casual, and planned leaves
- Management views for employee attendance and leave balances

## Tech Stack

- PHP `^8.2`
- Laravel `^12.0`
- Laravel Sanctum `^4.0`
- Spatie Laravel Permission `^6.16`
- Vite `^6.0`
- Tailwind CSS `^4.0`
- PHPUnit `^11.5`

## Requirements

Install these before running the project:

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL or another Laravel-supported database

MySQL is recommended because the leave calendar code uses JSON database functions.

## Getting Started

Clone the repository and install the dependencies:

```bash
git clone <repository-url>
cd attendanceapi
composer install
npm install
```

Create and configure your Laravel environment file:

```bash
cp .env.example .env
php artisan key:generate
```

If `.env.example` is not available, create a `.env` file and configure the required Laravel values, especially:

```env
APP_NAME="Attendance API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendanceapi
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
```

Run migrations and seed the default roles/admin user:

```bash
php artisan migrate --seed
```

Start the local API server:

```bash
php artisan serve
```

The API will usually be available at:

```text
http://127.0.0.1:8000/api
```

For frontend assets during development, run:

```bash
npm run dev
```

To build production assets:

```bash
npm run build
```

## Default Seeded User

The database seeder creates three roles:

- `admin`
- `management`
- `employee`

It also creates a default admin account:

```text
Email: admin@gmail.com
Password: admin@1234
```

Change this password before using the project outside local development.

## Authentication

Login returns a Sanctum access token:

```http
POST /api/login
```

```json
{
  "email": "admin@gmail.com",
  "password": "admin@1234"
}
```

Use the returned token on protected endpoints:

```http
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

## API Endpoints

### Public

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/login` | Login and receive an access token |
| `POST` | `/api/password/forgot` | Send a password reset link |
| `POST` | `/api/password/reset` | Reset a password with token, email, password, and password confirmation |

### Authenticated Users

All endpoints in this section require a Bearer token.

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/logout` | Revoke the authenticated user's tokens |
| `GET` | `/api/user` | Get the authenticated user's profile and roles |
| `GET` | `/api/user/all` | Get users visible to the authenticated user's role |
| `POST` | `/api/user/update/name` | Update the authenticated user's name |

### User Management

Requires `admin` or `management`.

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/register/employee` | Create an employee user and default leave balance |
| `POST` | `/api/register/management` | Create a management user and default leave balance |

Example registration body:

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123"
}
```

### Attendance

| Method | Endpoint | Role | Description |
| --- | --- | --- | --- |
| `POST` | `/api/attendance/mark` | Authenticated | Mark attendance or add check-out time |
| `GET` | `/api/attendance/today` | Authenticated | Get today's attendance status |
| `GET` | `/api/attendance/get` | Authenticated | Get the authenticated user's attendance records for the current year |
| `GET` | `/api/attendance/get/status` | Admin, Management | Get today's employee attendance statuses |
| `GET` | `/api/attendance/get/{userId}` | Admin, Management | Get attendance records for a specific user |
| `POST` | `/api/lunchtime/toggle` | Authenticated | Start or stop lunch break tracking |

Example attendance body:

```json
{
  "date": "2026-06-29",
  "status": "present",
  "check_in": "09:30:00"
}
```

To check out:

```json
{
  "date": "2026-06-29",
  "status": "present",
  "check_out": "18:00:00"
}
```

## Leave Requests

| Method | Endpoint | Role | Description |
| --- | --- | --- | --- |
| `POST` | `/api/leave/request/send` | Authenticated | Send a leave request |
| `GET` | `/api/leave/request` | Authenticated | Get leave requests created by the authenticated user |
| `GET` | `/api/leave/request/{id}` | Authenticated | Get a leave request if the user created it or received it |
| `POST` | `/api/leave/request/update/{id}` | Authenticated | Update leave request status |
| `GET` | `/api/leave/request/sent` | Admin, Management | Get leave requests sent to the authenticated approver |
| `GET` | `/api/leave/request/calendar?month=3&year=2026` | Admin, Management | Get approved leaves for a month and year |

Example leave request body:

```json
{
  "leave_dates": ["2026-07-01", "2026-07-02"],
  "sent_to_ids": [1],
  "leave_type": "planned",
  "reason": "Family function"
}
```

Supported leave types:

- `planned`
- `sick`
- `casual`

Supported request statuses:

- `pending`
- `approved`
- `rejected`
- `cancelled`

When a leave request is approved, attendance records are created or updated as `leave`, and the user's leave balance is reduced.

## Leave Balances

| Method | Endpoint | Role | Description |
| --- | --- | --- | --- |
| `GET` | `/api/leave/balance` | Authenticated | Get the authenticated user's leave balance |
| `GET` | `/api/leave/balance/all` | Management | Get leave balances for all employee users |

Each new employee or management user starts with:

- 2 sick leaves
- 2 casual leaves
- 2 planned leaves

## Database Tables

The project includes migrations for:

- `users`
- `personal_access_tokens`
- `roles`, `permissions`, and related Spatie permission tables
- `attendance`
- `leave_request`
- `balance`
- Laravel cache and job tables

## Running Tests

Run the test suite with:

```bash
php artisan test
```

Or run PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Useful Commands

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan route:list
php artisan cache:clear
php artisan config:clear
php artisan test
```

## Project Structure

```text
app/
  Http/Controllers/     API controllers
  Models/               Eloquent models
database/
  migrations/           Database schema
  seeders/              Default roles and admin user
routes/
  api.php               API routes
resources/
  css/ js/ views/       Frontend assets and default view
tests/
  Feature/ Unit/        Test cases
```

## GitHub Publishing Notes

Before publishing or deploying:

- Do not commit `.env` or secrets.
- Add an `.env.example` file for other developers.
- Change the default seeded admin password.
- Configure mail settings if password reset emails should be sent.
- Review production database credentials.
- Run migrations on the target environment.
- Run the test suite.

## License

No dedicated license file is currently included. Add a license before publishing if this repository will be shared publicly.
