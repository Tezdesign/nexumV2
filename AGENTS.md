# Nexum

Esprit PIDEV 3A3 (2025/2026). A Symfony web platform for projects, tasks, financial analysis, chat, resources and training, sharing one MySQL database with a legacy JavaFX desktop app.

## Stack

- **Language / Runtime**: PHP 8.2+ (composer platform pinned to 8.2.0), Python 3 for the AI side service
- **Framework**: Symfony 6.4, Doctrine ORM 3, Twig, AssetMapper with Stimulus and Turbo (no Node build step)
- **Key dependencies**: knp-paginator, symfony/ux-chartjs and ux-live-component, dompdf, phpspreadsheet, predis
- **Database**: MySQL 8 (`DATABASE_URL`), the same schema the Java app uses
- **AI side service**: Flask `app.py` plus `nexum_engine.py` (OpenVINO, Gemma), called over HTTP at `AI_API_URL`
- **Package manager**: composer (PHP), pip (Python). No Node tooling

## Build approach

<TBD, set by /scope>

## Commands

```bash
composer install
php bin/console doctrine:migrations:migrate
symfony server:start          # or: php -S 127.0.0.1:8000 -t public
python app.py                 # AI service on :5000, needs the OpenVINO model
php bin/phpunit               # SQLite test DB, see tests/DatabaseWebTestCase.php
php vendor/phpstan/phpstan/phpstan.phar analyse --memory-limit=1G   # level 8 on src/. `vendor/bin/phpstan` prints nothing here, use the phar. About 70 older errors exist, so check your own files
php bin/console make:migration && php bin/clean_migration.php   # always run the cleaner, see migrations/AGENTS.md
```

## Rules

- Namespaces follow folders under `src/`. Entities use PHP attributes, no annotations. Routes are attributes on controllers (`config/routes.yaml` scans `src/Controller/`).
- Controllers extend `AbstractController`. Business logic goes in `src/Service/<Area>/`, queries in `src/Repository/<Area>/`. Services autowire, with scalar args wired in `config/services.yaml`.
- Auth is custom, not the Symfony firewall. `App\Service\AuthService` logs in against `utilisateurs`, stores a `user` array in the session and also sets a security token. Controllers guard themselves with `AuthService::isLoggedIn()`, `isAdmin()`, `isManager()` and redirect. There are no `access_control` rules and no `#[IsGranted]`, so every new route must check access itself.
- Role lists live in `Utilisateur::SELF_REGISTRATION_ROLES` (public sign up, no admin) and `Utilisateur::ASSIGNABLE_ROLES` (admin forms). Keep the sign up and admin form dropdowns in step with them.
- Roles are free text strings in `utilisateurs.role`, matched with lowercase `str_contains`. Statuses `active` and `actif` both mean enabled.
- Passwords are compared in plain text (`UtilisateurRepository::login`). Do not copy this pattern. Changing it affects the Java app, which reads the same column.
- Read config through `config/services.yaml` parameters or `%env()%`. Some services still read `$_ENV` directly (AI URL, Redis). Do not hardcode hosts or IPs; the latest commits moved them into `.env`.
- UI text is mixed French and English. Reuse the existing wording of the page you edit.
- Run PHPStan before finishing. Its ignore list in `phpstan.neon` is intentional.

## Gotchas

- `.env` is tracked by git (despite `.gitignore`) and holds real looking secrets (mail, SMS, Cloudinary, Gemini keys). Put local values in `.env.local`, never add new secrets to `.env`, and flag rotation to the owner.
- The `remote` Doctrine connection was removed from `config/packages/doctrine.yaml`, but `SyncCommand`, `DatabaseHealthService` and `SyncStatusController` still call `getConnection('remote')`. Bidirectional sync is unfinished, so treat it as broken until the connection is restored.
- The database is legacy (created by the Java app). Never accept Doctrine's auto generated renames of indexes or foreign keys.
- `AI_API_URL` defaults to `http://127.0.0.1:5000`. `nexum_engine.py` hardcodes a Windows model path and a GPU device, so it only runs on the team's AI machine. The PHP side returns `status: ERROR` arrays when the engine is down, so keep that fallback.
- Redis (`REDIS_URL`, via Predis) backs draft notifications and the sync toggle cache. Without it features degrade silently.
- Live call and websocket hosts come from `UNIVERSEL_WB_URL` and the `CHAT_CALL_*` variables.
- Scratch files sit at the repo root (`test_*.php`, `test_*.py`, `find_lm_models.py`, `fix_indexes.php`). They are not part of the suite. The real tests live in `tests/Controller`, `tests/Entity`, `tests/Service`.
- Test coverage is thin: only Project, Task and Financial Analysis have tests, and `DatabaseWebTestCase` creates just four tables.
- `java_fa_sync_plan.md` is a planning note, not source of truth.

## Specs

Stored in `docs/specs/`. Format: `docs/specs/NNNN-title.md`. None exist yet.

## Layout

| Path | Owns |
|---|---|
| `src/Controller/` | Web routes by module: `Project`, `tasks`, `FinancialAnalysis`, `chat`, `ResourcesManagement`, `Admin`, `user`, `Api`, `Deploy`, plus flat Formation, Quiz, Rating, Rapport and Reclamation controllers |
| `src/Entity/` | Doctrine entities, one folder per module (`UserHandling`, `Projects`, `Tasks`, `FinancialAnalysis`, `Chat`, `ResourcesManagement`) plus flat training entities (`Formation`, `Quiz`, `Resultat`, `Participer`, `Rating`) |
| `src/Service/` | Integrations (Telegram, Infobip SMS, Cloudinary, mail, PDF, QR, certificates) and per module logic |
| `src/Command/`, `src/EventSubscriber/` | Console commands and `SyncLogSubscriber` (writes `sync_log` rows) |
| `templates/` | Twig views, see `templates/AGENTS.md` |
| `migrations/`, `bin/clean_migration.php` | Schema changes against the legacy DB |
| `app.py`, `nexum_engine.py`, `python/forecast.py`, `src/aitools/` | Python AI service and helper scripts |

## Context files

- [templates/AGENTS.md](templates/AGENTS.md) (layouts, Turbo, duplicate folders)
- [migrations/AGENTS.md](migrations/AGENTS.md) (legacy Java database migration workflow)
- [src/Service/FinancialAnalysis/AGENTS.md](src/Service/FinancialAnalysis/AGENTS.md) (budgets, expense drafts, AI calls, Redis, DB sync)

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
