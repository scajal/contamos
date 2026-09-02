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
