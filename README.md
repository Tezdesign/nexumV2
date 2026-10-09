# Nexum

**Esprit PIDEV 3A3 (2025/2026)**

Nexum is a web platform that brings project management, task tracking, financial analysis, team chat, resource booking and employee training into one application. It is built with **Symfony 6.4** and shares one **MySQL** database with a legacy **JavaFX desktop app**.

---

## Table of contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [How access works](#how-access-works)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Email](#email)
- [Database and migrations](#database-and-migrations)
- [Running the tests and checks](#running-the-tests-and-checks)
- [Project structure](#project-structure)
- [Known limitations](#known-limitations)
- [Security notes](#security-notes)
- [Contributing](#contributing)

---

## Features

| Module | What it does |
|---|---|
| **Accounts** | Sign up with a profile photo, login with a captcha, profile page with password change. A new account stays `pending` until an administrator activates it. |
| **Administration** | Users (create, edit, activate, delete, PDF export), reclamations (complaints with attachments and a history log), audit pages with AI comments. |
| **Projects** | Project creation with a team, files, progress, an AI task suggestion helper (Gemini) and a project report page. |
| **Tasks** | Personal and project tasks, Kanban board, calendar, workload check before assigning. |
| **Resources** | Inventory with derived stock, resource requests with admin approval, returns, calendar, quantity forecast. |
| **Chat** | Direct and group conversations, attachments, GIFs, link previews, call invitations. |
| **Training** | Formations with videos, progress milestones, quizzes, ratings, PDF certificates with a QR code, translation of descriptions, AI quiz generation from a PDF. |
| **Financial analysis** | Fiscal year profiles, project budgets, transactions, expense drafts reviewed by a consultant, trends, currency rates. |

---

## Tech stack

- **PHP 8.2+**, **Symfony 6.4**, Doctrine ORM 3, Twig, AssetMapper with Stimulus and Turbo (no Node build step)
- **MySQL 8** (shared with the Java app)
- Libraries: KnpPaginator, Symfony UX (Chart.js, Live Components), Dompdf, PhpSpreadsheet, Endroid QR Code
- **Email:** Mailjet through Symfony Mailer
- **AI:** Gemini over HTTPS for task suggestions. The local AI engine (Flask) was removed and will be rebuilt.
- **Python 3** helper scripts (resource forecast, reclamation audit wrapper, optional AI quiz generator)

---

## How access works

Authentication is custom (no Symfony firewall). A user logs in with email and password, and the session keeps a `user` array and a security token.

- **Visitor:** can only see the welcome, login and sign up pages. Any other page redirects to `/welcome`; fetch calls get 401.
- **Logged in user:** the app modules. Routes marked `#[RequireLogin]` are checked by `AccessGuardSubscriber`.
- **Administrator:** a user whose `role` contains `admin` (case insensitive). Routes marked `#[RequireAdmin]` answer 403 to everyone else. The last active administrator cannot be demoted or deleted, and nobody can remove their own admin access.
- **Roles for sign up:** `employee`, `consultant`, `formateur`, `manager`. The admin form also offers `admin`, `hr` and `finance`.
- **Activation:** only users with status `active` (or `actif`) can log in.
- All state changing forms carry a CSRF token.

---

## Getting started

### Requirements

- PHP 8.2 or newer with the usual Symfony extensions (`pdo_mysql`, `intl`, `mbstring`, `gd`, `fileinfo`)
- Composer 2
- MySQL 8
- Python 3.10+ (only for the helper scripts)

### Install

```bash
git clone https://github.com/Tezdesign/nexumV2.git
cd nexumV2
composer install
```

### Configure

Create a local file for your own values. It is ignored by git. Only put the values you need to change in it, starting with the database:

```env
# .env.local
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/nexum?serverVersion=8.0&charset=utf8mb4"
```

See [Configuration](#configuration) for the other variables.

### Prepare the database

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

The database is shared with the Java app. Read [`MIGRATION_GUIDE.md`](MIGRATION_GUIDE.md) before changing the schema.

### Create the first administrator

Public sign up cannot create administrators. Insert one directly (choose your own password):

```sql
INSERT INTO utilisateurs (nom, prenom, email, role, statut, date_inscription, password, score)
VALUES ('Admin', 'Nexum', 'admin@example.com', 'admin', 'active', CURDATE(), 'choose-a-password', 100);
```

Passwords are stored as plain text because the Java app reads the same column. Change it from the profile page after the first login.

### Run

```bash
symfony server:start
# or
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000`.

---

## Configuration

Put real values in `.env.local`. The tracked `.env` must only hold safe defaults.

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_SECRET` | Symfony environment and secret |
| `DATABASE_URL` | MySQL connection |
| `MAILER_DSN`, `MAILER_FROM`, `ADMIN_ALERT_EMAIL` | Email, see [Email](#email) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Project task suggestions |
| `AI_API_URL` | Local AI engine address (removed for now, features answer "offline") |
| `CURRENCY_API_KEY` | Exchange rates for budget profiles |
| `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET` | Media storage |
| `UNIVERSEL_WB_URL`, `CHAT_CALL_*` | Chat call and websocket servers |
| `QUIZ_PYTHON_BIN`, `QUIZ_GENERATOR_SCRIPT` | Optional AI quiz generator from a PDF |
| `HOLIDAY_COUNTRY_CODE` | Holidays shown on the calendar |

---

## Email

Mail is sent through **Mailjet**. The only email today is an alert to the administrator when a new account is waiting for activation. It is sent after the page response, so a mail problem never blocks a sign up.

In `.env.local`:

```env
MAILER_DSN=mailjet+api://YOUR_API_KEY:YOUR_SECRET_KEY@api.mailjet.com
MAILER_FROM=an-address-verified-in-mailjet@example.com
ADMIN_ALERT_EMAIL=the-admin-inbox@example.com
```

While `MAILER_FROM` or `ADMIN_ALERT_EMAIL` is empty, nothing is sent.

---

## Database and migrations

The database was created by the Java app. Rules:

- Never accept Doctrine's automatic renames of indexes or foreign keys.
- After generating a migration, always run the cleaner:

```bash
php bin/console make:migration
php bin/clean_migration.php
```

- Review the migration by hand, then run `php bin/console doctrine:migrations:migrate`.

---

## Running the tests and checks

```bash
php bin/phpunit                 # PHPUnit, SQLite test database
php vendor/phpstan/phpstan/phpstan.phar analyse --memory-limit=1G   # static analysis (level 8)
php bin/console lint:container
php bin/console lint:twig templates
```

Notes:

- `vendor/bin/phpstan` may print nothing on some setups, so use the phar as shown. A number of older findings exist, so check the files you touched.
- Tests that extend `KernelTestCase` or `WebTestCase` need `KERNEL_CLASS=App\Kernel` in the PHPUnit environment. Without it they error before running.
- Several newer tests build their own kernel with SQLite, so they run without MySQL.

---

## Project structure

```text
.
├── src/
│   ├── Attribute/        RequireLogin, RequireAdmin
│   ├── Controller/       Web routes by module (Admin, Project, tasks, chat, ResourcesManagement, ...)
│   ├── Entity/           Doctrine entities, one folder per module
│   ├── Repository/       Queries
│   ├── Service/          Business logic and integrations
│   ├── EventSubscriber/  Access guard, sync log
│   ├── Command/          Console commands
│   └── aitools/          Python wrapper for the audit AI comments
├── templates/            Twig views (layouts, auth, admin, one folder per module)
├── migrations/           Schema changes against the legacy database
├── python/               forecast.py (resource quantity forecast)
├── config/               Symfony configuration and services
├── tests/                Controller, Service and Entity tests
└── public/               Web root and uploaded assets
```

Each large area has its own `AGENTS.md` with the rules and gotchas of that module.

---

## Known limitations

- **Plain text passwords.** They are compared as plain text because the Java app reads the same column. Changing this affects the desktop app.
- **No limit on failed logins.** The captcha slows scripts down, but accounts are not locked after repeated failures.
- **Database sync is unfinished.** The `remote` connection was removed. `/api/sync/*` answers "not configured" and the top bar widget hides itself.
- **Local AI engine removed.** The project report, the financial draft analysis and the audit comments use fallbacks or show an offline message until it returns.
- **Forgot password flow removed.** An administrator changes a forgotten password.
- **AI quiz generator** needs a Python script that is not in the repository (`QUIZ_GENERATOR_SCRIPT`).

---

## Security notes

- Never commit secrets. `.env` is tracked by git, so keep real keys in `.env.local` only.
- If a credential was ever committed, treat it as exposed and rotate it.
- Uploaded files (photos, attachments, certificates) are checked by real content type and size. Certificates and QR images live outside `public/`.
- Report a security problem privately to the maintainers, not in a public issue.

---

## Contributing

1. Create a branch for your change.
2. Keep each commit small and clear.
3. Run the tests and PHPStan before opening a pull request.
4. Follow the conventions of the module you edit (see its `AGENTS.md`).
5. Write down behavior changes in the pull request description.

---

## License

Proprietary, as set in `composer.json`. Use and distribution follow the policy of the repository owner.
