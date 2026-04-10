# Objective
Implement the Fiscal Year Dashboard filtering, build the core DQL repository queries, implement the "110 Rule" update validation, and architect the KPI deviation system.

# Context & Architecture Plan

## 1. Project Budget Filtering (DQL & Repository)
We will add a DQL function in `ProjectBudgetRepository.php` named `findByFiscalYearScope(\DateTimeInterface $startDate, \DateTimeInterface $endDate)`. 
- **The Scope Logic:** A Project Budget falls into a Fiscal Year if its `dueDate` is between the `start_date` and `end_date` of the `BudgetProfile`.
- We will update `FinancialDashboardController::overview` to fetch only these filtered budgets.

## 2. Empty State Graphic for FY Dashboard
If `findByFiscalYearScope` returns an empty array, we will render a beautiful "No Project Budgets Found for this Fiscal Year" graphic (using Tabler icons) inside the grid/list area instead of a blank screen.

## 3. The 110 Rule Validation (Entity + Controller)
Symfony Entities cannot securely query the database directly inside an `#[Assert\Callback]`. 
- **The Architecture:** We will add two **transient** attributes to `BudgetProfile`: `private ?float $transientAllocatedBudgets` and `private ?float $transientProjectExpenses`. 
  - *Note for User: "Transient" simply means we are adding normal PHP variables to the class, but we are intentionally NOT adding the `#[ORM\Column]` attribute above them. Because they have no ORM mapping, Doctrine completely ignores them when saving or loading from the database. They exist purely in server memory for the split second the form is validating!*
- **The Flow:** When you submit an update in the Controller, the Controller will use DQL to calculate the SUM of all project budgets and the SUM of all actual expenses for that specific FY. It will inject these numbers into the transient attributes using setter functions *before* Symfony runs the form validation.
- **The Math:** Inside `validateUpdateLogic()`, we simply verify: `new_budget >= $this->transientAllocatedBudgets` AND `new_budget >= $this->transientProjectExpenses * 1.10`. If it fails, we throw the violation!

## 4. KPI Deviation Architecture (Postponed)
- **User Feedback:** Because tracking historical data requires new database tables and migrations, we are postponing the historical cache implementation for the KPI deviations.
- **Current Action:** The KPI values will be wired to show the **current live calculations** dynamically using Twig math, but the percentage deviations text underneath them will remain static placeholders or be hidden for now until we finish CRUD across all entities and build the history architecture later.

# Implementation Steps
1. Write DQL in `ProjectBudgetRepository` (`findByFiscalYearScope` and `getTotalsForFiscalYear`).
2. Update `FinancialDashboardController` to fetch the filtered budgets and calculate the FY scope totals.
3. Update `BudgetProfile.php` with transient properties (`$transientAllocatedBudgets`, `$transientProjectExpenses`) and execute the 110 rule math inside `validateUpdateLogic()`.
4. Update `overview.html.twig` to handle the empty state if no projects are found, and map the live KPI values.