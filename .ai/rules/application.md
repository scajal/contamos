---
paths:
  - 'resources/js/layouts/application/**'
---

# Application

## Layout owns default header breadcrumbs
ApplicationLayout renders a BreadcrumbPage for the current Inertia page (Dashboard, Accounts, Transactions). Pass the breadcrumb prop only to override that trail. Use BreadcrumbPage for the current page and BreadcrumbLink for ancestors. Do not hide the header breadcrumb on mobile.
