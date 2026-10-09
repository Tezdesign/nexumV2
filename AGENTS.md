# Nexum

Esprit PIDEV 3A3 (2025/2026). A Symfony web platform for projects, tasks, financial analysis, chat, resources and training, sharing one MySQL database with a legacy JavaFX desktop app.

## Stack

- **Language / Runtime**: PHP 8.2+ (composer platform pinned to 8.2.0), Python 3 for a few helper scripts
- **Framework**: Symfony 6.4, Doctrine ORM 3, Twig, AssetMapper with Stimulus and Turbo (no Node build step)
- **Key dependencies**: knp-paginator, symfony/ux-chartjs and ux-live-component, dompdf, phpspreadsheet
- **Database**: MySQL 8 (`DATABASE_URL`), the same schema the Java app uses
- **AI**: Gemini over HTTPS for project task suggestions. The local AI engine (Flask) was removed and is to be rebuilt; PHP code that calls `AI_API_URL` stays and fails gracefully until it returns
- **Package manager**: composer (PHP), pip (Python). No Node tooling

## Build approach

<TBD, set by /scope>

## Commands

```bash
composer install
php bin/console doctrine:migrations:migrate
symfony server:start          # or: php -S 127.0.0.1:8000 -t public
php bin/phpunit               # SQLite test DB, see tests/DatabaseWebTestCase.php
php vendor/phpstan/phpstan/phpstan.phar analyse --memory-limit=1G   # level 8 on src/. `vendor/bin/phpstan` prints nothing here, use the phar. About 70 older errors exist, so check your own files
php bin/console make:migration && php bin/clean_migration.php   # always run the cleaner, see migrations/AGENTS.md
```

## Rules

- Namespaces follow folders under `src/`. Entities use PHP attributes, no annotations. Routes are attributes on controllers (`config/routes.yaml` scans `src/Controller/`).
- Controllers extend `AbstractController`. Business logic goes in `src/Service/<Area>/`, queries in `src/Repository/<Area>/`. Services autowire, with scalar args wired in `config/services.yaml`.
- Auth is custom, not the Symfony firewall. `App\Service\AuthService` logs in against `utilisateurs`, stores a `user` array in the session and also sets a security token. Controllers guard themselves with `AuthService::isLoggedIn()`, `isAdmin()`, `isManager()` and redirect. There are no `access_control` rules and no `#[IsGranted]`, so every new route must check access itself. Put `#[RequireLogin]` or `#[RequireAdmin]` (`src/Attribute/`) on a controller class or action and `AccessGuardSubscriber` enforces it: a visitor opening a page goes to `/welcome`, a fetch or Ajax call gets 401, and for `RequireAdmin` a non admin gets 403.
- Role lists live in `Utilisateur::SELF_REGISTRATION_ROLES` (public sign up, no admin) and `Utilisateur::ASSIGNABLE_ROLES` (admin forms). Keep the sign up and admin form dropdowns in step with them.
- Roles are free text strings in `utilisateurs.role`, matched with lowercase `str_contains`. Statuses `active` and `actif` both mean enabled.
- Passwords are compared in plain text (`UtilisateurRepository::login`). Do not copy this pattern. Changing it affects the Java app, which reads the same column.
- Read config through `config/services.yaml` parameters or `%env()%`. Some services still read `$_ENV` directly (AI URL). Do not hardcode hosts or IPs; the latest commits moved them into `.env`.
- UI text is mixed French and English. Reuse the existing wording of the page you edit.
- Run PHPStan before finishing. Its ignore list in `phpstan.neon` is intentional.

## Gotchas

- `.env` is tracked by git (despite `.gitignore`) and holds real looking secrets (Cloudinary, Gemini keys). Put local values in `.env.local`, never add new secrets to `.env`, and flag rotation to the owner.
- The `remote` Doctrine connection was removed from `config/packages/doctrine.yaml`, but `SyncCommand`, `DatabaseHealthService` and `SyncStatusController` still call `getConnection('remote')`. Bidirectional sync is unfinished, so treat it as broken until the connection is restored.
- The database is legacy (created by the Java app). Never accept Doctrine's auto generated renames of indexes or foreign keys.
- The local AI engine is removed for now. `AI_API_URL` (default `http://127.0.0.1:5000`) is still read by the project report and financial analysis services, which return `status: ERROR` or a stream error while nothing answers. Keep that fallback.
- Email goes through Mailjet (`symfony/mailjet-mailer`). `.env` holds only safe defaults (`MAILER_DSN=null://null`, empty `MAILER_FROM` and `ADMIN_ALERT_EMAIL`). The real `MAILER_DSN=mailjet+api://API_KEY:SECRET_KEY@api.mailjet.com` and the two addresses live in `.env.local`. `AdminAlertMailer` sends the "new account waiting" alert after the response and does nothing while an address is empty.
- Live call and websocket hosts come from `UNIVERSEL_WB_URL` and the `CHAT_CALL_*` variables.
- Scratch files sit at the repo root (`test_*.php`, `test_*.py`, `fix_indexes.php`). They are not part of the suite. The real tests live in `tests/Controller`, `tests/Entity`, `tests/Service`.
- Test coverage is thin: only Project, Task and Financial Analysis have tests, and `DatabaseWebTestCase` creates just four tables.
- `java_fa_sync_plan.md` is a planning note, not source of truth.

## Specs

Stored in `docs/specs/`. Format: `docs/specs/NNNN-title.md`. None exist yet.

## Layout

| Path | Owns |
|---|---|
| `src/Controller/` | Web routes by module: `Project`, `tasks`, `FinancialAnalysis`, `chat`, `ResourcesManagement`, `Admin`, `user`, `Api`, `Deploy`, plus flat Formation, Quiz, Rating, Rapport and Reclamation controllers |
| `src/Entity/` | Doctrine entities, one folder per module (`UserHandling`, `Projects`, `Tasks`, `FinancialAnalysis`, `Chat`, `ResourcesManagement`) plus flat training entities (`Formation`, `Quiz`, `Resultat`, `Participer`, `Rating`) |
| `src/Service/` | Integrations (Cloudinary, PDF, QR, certificates) and per module logic |
| `src/Command/`, `src/EventSubscriber/` | Console commands and `SyncLogSubscriber` (writes `sync_log` rows) |
| `templates/` | Twig views, see `templates/AGENTS.md` |
| `migrations/`, `bin/clean_migration.php` | Schema changes against the legacy DB |
| `python/forecast.py`, `src/aitools/` | Python helpers: resource forecast (`ResourceController`) and the reclamation audit wrapper (`AIAuditService`) |

## Context files

- [templates/AGENTS.md](templates/AGENTS.md) (layouts, Turbo, duplicate folders)
- [migrations/AGENTS.md](migrations/AGENTS.md) (legacy Java database migration workflow)
- [src/Controller/AGENTS.md](src/Controller/AGENTS.md) (training: formations, quizzes, ratings, certificates; the flat controllers)
- [src/Controller/chat/AGENTS.md](src/Controller/chat/AGENTS.md) (conversations, messages, attachments, calls and their external servers)
- [src/Controller/ResourcesManagement/AGENTS.md](src/Controller/ResourcesManagement/AGENTS.md) (resource inventory, requests, returns, stock rules)
- [src/Service/FinancialAnalysis/AGENTS.md](src/Service/FinancialAnalysis/AGENTS.md) (budgets, expense drafts, AI calls, DB sync)

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
