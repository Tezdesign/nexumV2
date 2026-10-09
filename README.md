Nexum is a web platform that brings project management, task tracking, financial analysis, team chat, resource booking and employee training into one application. It is built with **Symfony 6.4** and shares one **MySQL** database with a legacy **JavaFX desktop app**.



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
