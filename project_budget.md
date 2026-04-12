# Nexum Web Port: Project Budgets UI Architecture

## Context
This document outlines the front-end architecture for the "Project Budgets" section of the Financial Analysis module in the Nexum Symfony web application. The top KPI widgets are already completed. The current focus is transitioning the project budget layouts into a component-driven Twig structure.

## ⚠️ CLI Development Directives
* **Template vs. Custom CSS:** While the application generally utilizes a premium Envato UI template, **the Project Budget cards require custom HTML and CSS.** The built-in Envato elements do not fit the specific layout requirements for this display. You are authorized to generate custom, responsive HTML/CSS for these specific components.
* **Component-Driven:** Build these custom cards as reusable Twig partials (e.g., `_project_budget_card.html.twig`).

## Data Implementation Strategy (Mock to Live)
* **Current Phase (UI Testing):** The application is currently in the UI testing phase and does not yet use live database connections.
* **Dynamic Twig Construction:** Even though the data is mock data, **the Twig templates must be written as if the data is live.** * **Execution:** Inject data using Twig variables (e.g., `{{ project.title }}`, `{{ project.remaining }}`). The mock data must be passed from the Symfony Controller as an array or object. This ensures that when live data implementation begins, the frontend Twig files require absolutely zero changes.

## Structural Layout
The Project Budgets section utilizes a Master-Detail pattern:

### 1. The Parent Container
* **Header Area:** Contains the "Project Budgets" title and a primary action button.
* **Tab System:** Navigation tabs to toggle between "Budget vs Actual" and "Misc" views.
* **Grid Wrapper:** A responsive container that houses the individual project cards.

### 2. The Project Card Partial (`_project_budget_card.html.twig`)
Each card is a custom Twig component representing a single project's financial health.

**Dynamic Data Points Required:**
* **Header:** Project Name and Subtitle (e.g., "Unknown Project").
* **Status Badge:** A visual indicator (e.g., "ON TRACK").
* **Financial Metrics (3 Columns):** Total Budget, Actual Spend, and Remaining.
* **Footer:** Due Date.

## Navigation Logic
* **Nested Routing:** Clicking a custom project card acts as a drill-down link. It should route the user to a dedicated profile URL (e.g., `/app/financial/budget/{id}`) to access advanced CRUD functionalities.
* **Rendering Loop:** Use a `{% for project in projects %}` loop in the parent container to stamp out the custom card partial dynamically.