# Templates

## Overview

Twig views for the whole app: the desktop web UI (a Bootstrap style admin theme with a sidebar), an admin variant and auth pages.

## Key files

| File | Owns |
|---|---|
| `layouts/vertical.html.twig` | Main user layout, sets `data-turbo="false"` on `<body>`. Blocks: `css`, `importmap`, `content`, `footer`, `script` |
| `layouts/admin_vertical.html.twig` | Layout for `admin/*` pages |
| `layouts/auth.html.twig`, `auth/` | Login, password reset, verify code |
| `partials/` | Topbar, sidenav, footer scripts, notification item, shared design system bits |
| `components/TaskBoard.html.twig` | Twig/Live component for the kanban board |

## Conventions

- Pick the layout by area: `layouts/vertical` for user pages, `layouts/admin_vertical` for admin.
- Brand colors were aligned with the Java app in recent commits. Reuse existing CSS variables and partials instead of new colors.

## Gotchas

- Turbo is imported in `assets/app.js`, yet the main layout disables it with `data-turbo="false"` (a past fix for broken interfaces). Do not turn it back on globally.
- `resources-management/` and `resources_management/` both exist. Check which one the controller renders before editing.
- Several files are experiments or duplicates (`apps-chat2`, `financial-analysis/test_form`, `show_modern` next to `show`). Confirm a route uses a template before changing or deleting it.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
