---
paths:
    - "resources/js/**"
---

# Js

## Inertia v3 + React

Server renders via Inertia::render() / Inertia::modal(). Client pages live in resources/js/pages. Blade is only the root template.

## Wayfinder for routes and forms

Import route helpers from @/routes/ (or @/actions/ for controller actions). Use .form() for Inertia <Form> props. Do not hardcode URLs.

## JSON sentence keys for i18n

Use full English strings as keys in lang/{locale}.json. Frontend: useLaravelReactI18n().t("Sentence"). Backend: trans("Sentence").

## Persistent Page.layout for full pages

Assign layout with `Page.layout = (page) => <ApplicationLayout>{page}</ApplicationLayout>` (or `WizardLayout`). Omit layout on Inertia modal pages. Do not use `setLayoutProps`.

## Modal plus Sheet for Inertia modals

Render Inertia modal pages as `<Modal>` wrapping `<Sheet {...rootProps}>`. Put title and description in `SheetHeader` and actions in `SheetFooter`.

## Colocate form fields in _components

Share create/edit form fields via a colocated `./_components/fields` module next to the page. Do not inline large field groups in create/edit pages.

## Inertia prop types mirror PHP modules

Put Inertia/serialized domain types under `@/types/app/modules/...` mirroring the PHP `App\Modules\...` path (kebab-case filenames). Import those types in pages instead of ad-hoc interfaces for domain shapes.
