# Financial Analysis

## Overview

Budget profiles (fiscal years), project budgets, transactions and expense drafts. Drafts are parsed and judged with the Python AI service, and parts of the data sync between a local and a remote database.

## Key files

| File | Owns |
|---|---|
| `BudgetAdvService.php`, `BudgetDashboardService.php`, `BudgetProjectStService.php`, `BudgetTrendCacheService.php` | Budget math, dashboard figures, trends and their cache |
| `DraftPolicyService.php` | Evaluates an `ExpenseDraft` against policy (Z score check) |
| `ParseDraftIntentService.php` | Turns free text into a draft using the AI service |
| `OpenVinoAnalysisService.php` | POSTs budget data to `AI_API_URL/api/nexum/analyze` (300 s timeout) |
| `CurrencyExchangeService.php` | Exchange rates from exchangerate-api (HttpClient, key sent as a Bearer header, returns `{}` on failure), per budget profile base currency |
| `../DatabaseHealthService.php`, `../../Command/SyncCommand.php`, `../../EventSubscriber/SyncLogSubscriber.php`, `../../Controller/Deploy/SyncStatusController.php` | Local to remote sync plumbing |
| `../../Entity/FinancialAnalysis/` | `BudgetProfile`, `ProjectBudget`, `Transaction`, `ExpenseDraft`, `SyncLog` |

## Conventions

- Draft statuses are `PENDING`, `APPROVED`, `REJECTED`, `FLAGGED`. Categories are `HARDWARE`, `SOFTWARE`, `SERVICES`, `TRAVEL`, `MARKETING`, `OTHER`.
- Policy and statistical checks stay in Symfony. The Java app only enters and reads drafts (see `java_fa_sync_plan.md`).
- AI calls return an array with a `status` key. Check for `SUCCESS` and handle `ERROR` without throwing.
- Tests: `tests/Service/FinancialAnalysis`, `tests/Controller/FinancialAnalysis`, `tests/Entity/FinancialAnalysis`. Extend these when changing logic.

## Gotchas

- `SyncLogSubscriber` logs only `ExpenseDraft`, `Transaction` and `ProjectBudget`, using raw SQL on the `sync_log` table to avoid recursive flushes.
- Sync needs a `remote` DBAL connection that is currently not configured (see root `AGENTS.md`). The on/off toggle lives in cache key `database_auto_sync_active` and defaults to off. `SyncLogSubscriber` writes `sync_log` rows only while that toggle is on. `/api/sync/status` and `/api/sync/toggle` are admin only (the toggle needs the `sync_toggle` CSRF token in `X-CSRF-Token`), status never starts a sync (run `php bin/console app:sync:run` from cron), and with no remote connection status answers `{configured: false}` and the top bar hides the widget.
- `DatabaseHealthService` caches the remote ping and schema check for 5 minutes, so a fix can look ignored for a while.
- Tables and fields follow the Java schema (snake case like `budget_profile`, `expense_draft`). Check the entity mapping before renaming anything.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
