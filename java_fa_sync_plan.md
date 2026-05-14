# JavaFX Legacy Application: Financial Analysis (FA) Synchronization Plan

**Environment:** JavaFX 21, Java SDK 17
**Database:** Shared MySQL database with the Symfony application.

This document outlines the architectural changes required to bring the legacy Java application's Financial Analysis module up to date with the current Symfony implementation. To ensure clarity and prevent overlap, the updates are divided into two distinct plans.

---

## Plan 1: Refactoring the Fiscal Year System

### Objective
The Java application currently utilizes a Singleton pattern to manage the active Fiscal Year. The Symfony application has evolved this into a robust, database-backed entity (`BudgetProfile` / `budget_profile` table) that supports multiple profiles, custom dates, and currencies. The Java application must be refactored to read from and interact with this database structure.

### 1. Model/Entity Updates
Create or update the Java model (e.g., `BudgetProfile.java`) to map to the shared `budget_profile` table. Ensure the following missing fields are added using appropriate Java 17 data types:
- `base_currency` (`String`)
- `start_date` (`java.time.LocalDate`)
- `end_date` (`java.time.LocalDate`)
- `budget_disposable` (`java.math.BigDecimal`)
- `total_expense` (`java.math.BigDecimal`)
- `margin_profit` (`Double`)
- `status` (`String` - e.g., "ACTIVE", "DRAFT", "CLOSED")

### 2. Architecture Changes
- **Deprecate the Singleton:** Remove or deprecate the existing Fiscal Year Singleton class.
- **DAO Implementation:** Create a `BudgetProfileDAO` to query the database for the active fiscal year profile (e.g., `SELECT * FROM budget_profile WHERE status = 'ACTIVE' LIMIT 1`).
- **Session Management:** Inject the retrieved active `BudgetProfile` into the application's context or dependency injection container at startup, rather than relying on the static Singleton.

### 3. JavaFX UI Updates
- **New Interface:** Create a new JavaFX view (e.g., `BudgetProfileSelectionView.fxml`) if users need to toggle between historical and active fiscal years.
- **Data Binding:** Update existing FA dashboards to bind to the new `base_currency` field when formatting financial KPIs, rather than assuming a hardcoded currency.

---

## Plan 2: Implementing the Expense Draft System (Project Space)

### Objective
Integrate the Expense Draft feature into the Java application. Crucially, this implementation is restricted to the **Project Space**. The Java application will act as the data-entry and viewing portal for drafts. The complex evaluation logic (AI Policy checks, Z-Score mathematical validation) remains exclusively in the Symfony FA module.

### 1. Model/Entity Updates
Create the `ExpenseDraft.java` model to map to the shared `expense_draft` table:
- `amount` (`Double` or `java.math.BigDecimal`)
- `subject` (`String`)
- `description` (`String`)
- `category` (`String` - HARDWARE, SOFTWARE, SERVICES, TRAVEL, MARKETING, OTHER)
- `status` (`String` - PENDING, APPROVED, REJECTED, FLAGGED)
- `created_at` (`java.time.LocalDateTime`)
- `createdBy` (Foreign Key -> User Model)
- `projectBudgetRelated` (Foreign Key -> ProjectBudget Model)

### 2. DAO/Service Implementation
- Implement basic CRUD operations in `ExpenseDraftDAO` (Insert, Select, Update, Delete).
- **Constraint:** Do *not* port over the `BudgetAdvService` or AI evaluation scripts. When a Java user saves a draft, simply insert it into the database with a default status of `"PENDING"`. The Symfony backend (or a database trigger/cron) will handle the actual evaluation.

### 3. JavaFX UI Updates (Project Module)
- **Draft Creation Form:** Build a JavaFX Dialog or Form (`CreateDraftDialog.fxml`) within the Project view containing text fields for Subject, Amount, Category (ComboBox), and Description (TextArea).
- **Drafts List View:** Create a `TableView` or `ListView` inside the Project Dashboard to display existing drafts associated with the project.
- **Status Indicators:** Map the `status` string from the database to JavaFX UI indicators (e.g., color-coded labels or icons for PENDING, APPROVED, REJECTED) so users can see the outcome of the Symfony-side evaluations.