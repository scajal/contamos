---
paths:
    - "resources/js/**"
    - "resources/js/**/*.tsx"
---

# Js

## Inertia v3 + React

Server renders via Inertia::render() / Inertia::modal(). Client pages live in resources/js/pages. Blade is only the root template.

## Wayfinder for routes and forms

Import route helpers from @/routes/ (or @/actions/ for controller actions). Use .form() for Inertia <Form> props. Do not hardcode URLs.

## JSON sentence keys for i18n

Use full English strings as keys in lang/{locale}.json. Frontend: useLaravelReactI18n().t("Sentence"). Backend: trans("Sentence"). Spanish UI copy uses tú (Agrega, Ingresa, Selecciona, Edita), not usted.

## Persistent Page.layout for full pages

Assign layout with `Page.layout = (page) => <ApplicationLayout>{page}</ApplicationLayout>` (or `WizardLayout`). Omit layout on Inertia modal pages. Do not use `setLayoutProps`.

## Modal plus Dialog for Inertia modals

Render Inertia modal pages as `<Modal>` wrapping `<Dialog {...rootProps}>`. Put title and description in `DialogHeader`, the body in a `flex-1` div, and actions in `DialogFooter`. Do not use `Sheet` for modal pages — it is only the mobile sidebar drawer.

## Colocate form fields in _components

Share create/edit form fields via a colocated `./_components/fields` module next to the page. Do not inline large field groups in create/edit pages.

## Inertia prop types mirror PHP modules

Put Inertia/serialized domain types under `@/types/app/modules/...` mirroring the PHP `App\Modules\...` path (kebab-case filenames). Import those types in pages instead of ad-hoc interfaces for domain shapes.

## Frontend team routes use slug

Wayfinder {team} params use user.current_team.slug (or the page team.slug), never current_team_id, team.id, or *.team_id. HandleInertiaRequests loads currentTeam when current_team_id is present; Inertia serializes it as current_team.

## Shared Inertia user and admin flag

Share the authenticated user as top-level `user` with `Inertia::always` (eager-load `currentTeam` when present). Share `auth.canAccessAdministration` from the administration Filament panel. Do not nest the user under `auth` or re-share it from controllers.

## Layout reads shared user.teams

The shared Inertia user includes teams (memberships) and current_team. The sidebar team switcher reads user.teams and user.current_team; do not refetch teams in the layout.

## Kebab-case files, named arrow components, default-export pages
Name files kebab-case; export components, layouts and hooks by name (arrow const for components), one per file. Pages are the only default exports: const Page + Page.layout when they render inside a layout, export default function for modal pages. Keep page sections as local non-exported consts.

## Format through lib/utils with an explicit locale
Format money with formatAmount(amount, locale, currency) and dates with the formatX(value, locale) helpers in lib/utils; never call Intl or date-fns from a page, never parse amounts to floats. Take the locale from useLaravelReactI18n().currentLocale.

## Translate with t() and :placeholder interpolation
Read t (and currentLocale) from useLaravelReactI18n(); interpolate with Laravel-style :name placeholders and an object argument, never template literals around t().

## cn from lib/utils, lucide icons, Base UI toast
Import cn from @/lib/utils in app code (only components/ui keeps the generated 'cn' package import); use lucide-react icons; raise notifications with toast from components/ui/toast, not sonner. No CSS modules.
