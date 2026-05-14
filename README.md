# Nexum – Esprit PIDEV 3A3 (2025–2026)

Nexum is a **Symfony 6.4** platform designed around collaborative project management, task tracking, financial analysis, communication, and AI-assisted workflows.

This branch also includes a **Python AI service** for advanced financial and project-report capabilities.

---

## Table of Contents

- [Overview](#overview)
- [Main Features](#main-features)
- [Tech Stack](#tech-stack)
- [Repository Structure](#repository-structure)
- [Architecture](#architecture)
- [Prerequisites](#prerequisites)
- [Installation & Setup](#installation--setup)
- [Environment Variables](#environment-variables)
- [Database & Migrations](#database--migrations)
- [Run the Project](#run-the-project)
- [Testing & Quality](#testing--quality)
- [AI Services](#ai-services)
- [Common Commands](#common-commands)
- [Security Notes](#security-notes)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

Nexum is a modular web platform that combines:

- User and admin management
- Project and task lifecycle tools
- Financial dashboards and budgeting
- Messaging/chat components
- Notifications and integrations
- AI-powered analysis and report generation

The codebase is centered on Symfony, with complementary Python services for ML/AI scenarios.

---

## Main Features

### 1. User & Administration

- User accounts and profile handling
- Admin-side user, reclamation, and audit management
- Mobile-oriented authentication endpoints

### 2. Project Management

- Project CRUD and assignment flows
- Project file support
- Project progress-related logic

### 3. Task Management

- Task workflows and Kanban support
- Calendar integration
- Workload-related engine logic

### 4. Financial Analysis

- Budget profiles and project budgets
- Transactions and financial dashboards
- Expense draft and policy evaluation support

### 5. Communication

- Conversations and messages
- Message attachments
- Notification endpoints

### 6. AI Capabilities

- Intent extraction from natural language drafts
- Expense policy evaluation
- Financial projection and analysis assistance
- Streaming project report generation using SSE

---

## Tech Stack

### Backend

- PHP 8.2+
- Symfony 6.4
- Doctrine ORM
- Doctrine Migrations
- Twig

### Data & Infrastructure

- MySQL for the application database
- PostgreSQL available through Docker Compose for optional workflows
- Redis support through Predis for messenger/transport scenarios
- Mailpit for local mail testing

### AI & Data

- Python
- Flask
- Transformers
- OpenVINO for local inference
- scikit-learn
- NumPy

---

## Repository Structure

```text
.
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
│   ├── Command/
│   └── aitools/
├── config/
├── templates/
├── migrations/
├── tests/
├── python/
├── public/
├── compose.yaml
├── compose.override.yaml
├── composer.json
└── app.py
```

---

## Architecture

Nexum uses a hybrid architecture composed of a Symfony web application and a Python AI service.

### Symfony Web Application

The Symfony application is the main platform. It handles:

- Domain logic
- Routing
- Persistence
- Views
- Feature modules

### Python AI Service

The Python AI service is exposed through `app.py` and provides AI endpoints consumed by Symfony components.

Available endpoints include:

```text
/api/nexum/intent
/api/nexum/evaluate
/api/nexum/analyze
/api/nexum/rapport/stream
```

### Auxiliary ML Scripts

The project also includes auxiliary ML scripts, such as:

```text
python/forecast.py
```

This script is used for quantity forecasting from time-series-like input.

---

## Prerequisites

Before running the project, make sure you have the following installed:

- PHP 8.2+
- Composer 2+
- MySQL 8+ or compatible
- Python 3.10+ recommended
- Node.js, only if mobile or Capacitor-related assets are used
- Docker, optional for local services such as database and Mailpit

---

## Installation & Setup

### 1. Clone the Repository

```bash
git clone <repo-url>
cd Esprit-PIDEV-3A3-2526-Nexum
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the required `.env` values into a local override file:

```bash
.env.local
```

Then configure your local values for:

- Database
- Mailer
- AI service
- Integrations

### 4. Prepare the Database

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

### 5. Optional: Start Local Infrastructure with Docker

```bash
docker compose up -d
```

---

## Environment Variables

Core variables used in this branch include:

```env
APP_ENV=
APP_SECRET=
DATABASE_URL=
MAILER_DSN=
AI_API_URL=
AI_BACKEND=
LM_STUDIO_URL=
```

Other variables may also be required for:

- Chat and call signaling
- Cloud providers
- Media providers
- AI backend configuration

Use local environment files for machine-specific values and secrets:

```text
.env.local
.env.<env>.local
```

---

## Database & Migrations

This branch includes a legacy-database-aware migration process.

Please read:

```text
MIGRATION_GUIDE.md
```

Recommended flow when generating migrations:

```bash
php bin/console make:migration
php bin/clean_migration.php
```

Then:

1. Review the generated migration manually.
2. Run the migration:

```bash
php bin/console doctrine:migrations:migrate
```

---

## Run the Project

### Symfony Application

```bash
symfony server:start
```

Or:

```bash
php -S 127.0.0.1:8000 -t public
```

### Python AI Service

```bash
python app.py
```

---

## Testing & Quality

### PHPUnit

```bash
php bin/phpunit
```

The project uses `phpunit.dist.xml` with a SQLite test database configuration.

### PHPStan

```bash
vendor/bin/phpstan analyse
```

---

## AI Services

The `app.py` file loads the Nexum AI engine and exposes endpoints for:

- Intent extraction from user text
- Policy evaluation for expense drafts
- Financial analysis with recommendations
- Streaming project report generation in French using SSE output

Additional local AI tooling is available under:

```text
src/aitools/userai/
```

This folder contains Gemma, OpenVINO, and LM Studio-oriented utilities.

---

## Common Commands

```bash
# Clear Symfony cache
php bin/console cache:clear

# List routes
php bin/console debug:router

# Run Doctrine migrations
php bin/console doctrine:migrations:migrate

# Run tests
php bin/phpunit

# Run static analysis
vendor/bin/phpstan analyse
```

---

## Security Notes

- Never commit real secrets, tokens, or production credentials.
- Rotate exposed credentials immediately if they were ever committed.
- Keep API keys and DSNs in local/private environment files.
- Validate and sanitize all user-provided input, especially in AI-related flows.

---

## Contributing

1. Create a feature branch.
2. Keep commits focused and descriptive.
3. Run tests and static analysis before opening a pull request.
4. Follow existing project and module conventions.
5. Document important behavior changes.

---

## License

This project is marked as proprietary in `composer.json`.

Use and distribution are subject to the repository owner or team policy.
