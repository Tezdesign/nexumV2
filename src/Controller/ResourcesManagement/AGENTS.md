# Resources management

## Overview

Lets admins keep an inventory of resources (physical or digital), lets users request quantities for a project, and lets admins accept, decline, track returns and send overdue reminders. Returns affect a user's score. A small Python script forecasts demand.

## Key files

| File | Owns |
|---|---|
| `ResourceController.php` | Admin CRUD for resources (`/admin/resources`), the returns list, mark returned, the assignment calendar and the demand forecast pages |
| `ClientResourceController.php` | User side (`/client/resources`): own assignments, request form, submit (returns a PDF), edit and delete own assignment. Also the admin accept and decline routes and the request emails |
| `../../Entity/ResourcesManagement/Resource.php` | Table `resources`: code, name, type (`PHYSICAL` or other), `total_quantity`, `available_quantity`, `unit_cost`, `image_path` |
| `../../Entity/ResourcesManagement/ResourceAssignment.php` | Table `resource_assignment`: `resource_id` as a plain integer (no relation), `project_code`, `quantity`, status, dates, `returned`, user |
| `../../Service/Pdf/RequestPdfService.php` | Request receipt PDF |
| `../../../python/forecast.py` | Linear regression forecast, run by `proc_open("python ...")` with JSON on stdin |
| `../../../templates/resources-management/` | The views in use |

## Conventions

- Assignment statuses: `PENDING`, `ACCEPTED`, `DECLINED`. Only an `ACCEPTED` assignment that is not `returned` holds stock. Accept only works on `PENDING` requests; users can only edit `PENDING` or `DECLINED` ones.
- A request must name a project the user created, is assigned to, or is a member of, and that project must have an end date. The request form carries a CSRF token. A request's return date is the project end date. Users with score 0 are declined at once. Score 100 or more with 3 accepted requests is accepted at once.
- Nothing is stored on the user. The request page and the submit action refuse a request when the risk score passes 50 or the user already has 2 pending requests (a third would reach the limit of 3); it clears by itself once pending requests are decided.
- Returning early or on time adds 10 to 20 score, returning late removes 10 per day (never below 0).

## Gotchas

- Admin routes are guarded with `#[RequireAdmin]` (all of `ResourceController`, and `manage_requests`, `accept_request`, `decline_request`). Accept, decline and the overdue SMS are POST routes with a CSRF token. `mark_returned` is still a GET link. Assignment edit and delete are limited to the owner or an admin.
- `available_quantity` is owned by `App\Service\ResourcesManagement\ResourceStockService`: it is always total minus accepted, unreturned assignments, rewritten with `recalculate()` after any change (inside a transaction, with the resource row locked). Never add or subtract on it by hand. `php bin/console app:resources:recalculate-stock` repairs old drifted values.
- Routes under `ResourceController` have a class prefix of `/admin/resources`, so `admin_calendar` really lives at `/admin/resources/admin/calendar`.
- `templates/resources_management/` is not rendered by any controller (old scaffolding). Edit `templates/resources-management/`.
- The forecast runs through `ResourceForecastService` (`python/forecast.py` on stdin). The interpreter defaults to `python`; set `PYTHON_BIN` (for example `python3`) in `.env.local` if that is not on the PATH of the PHP process. A failure is logged and the prediction page shows a warning, while the browser computes its own estimate.
- Uploaded resource images go to `public/uploads/`. `ResourceType` accepts only real JPEG, PNG, GIF or WebP up to 2 MB (no SVG, which could carry script), and the file is saved only after the whole form is valid.
- Calendar statuses come from `ResourceController::returnStatus()`, which compares by day. A return date has no time, so never compare it with `new \DateTime()`.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
