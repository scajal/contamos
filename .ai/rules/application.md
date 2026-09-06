---
paths:
    - "resources/js/layouts/application/**"
---

# Application

## Layout owns default header breadcrumbs

ApplicationLayout renders a BreadcrumbPage for the current Inertia page (Dashboard, Accounts, Transactions). Pass the breadcrumb prop only to override that trail. Use BreadcrumbPage for the current page and BreadcrumbLink for ancestors. Do not hide the header breadcrumb on mobile.

## Content column uses min-w-0 for table overflow

SidebarInset, main, and the max-w-5xl content column use min-w-0 so nested overflow-x-auto tables can shrink and scroll instead of blowing out the flex layout.
