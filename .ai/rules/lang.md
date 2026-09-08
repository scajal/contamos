---
paths:
    - "lang/*.json"
    - "lang/*/validation.php"
---

# Lang

## JSON sentence keys for i18n

Use full English strings as keys in lang/{locale}.json. Frontend: useLaravelReactI18n().t("Sentence"). Backend: trans("Sentence").

Spanish UI copy uses tú (Agrega, Ingresa, Selecciona, Edita), not usted.

## Validation attributes in lang files

Put custom validation attribute labels in lang/{locale}/validation.php under attributes. Do not add Form Request attributes() or messages() methods.

## Restart the SSR process after changing translations
Vite inlines lang/*.json into bootstrap/ssr/app.js, and `php artisan inertia:start-ssr` loads that file once at boot. Node's ESM cache then pins the dictionary for the life of the process, while lazily-imported page chunks (new hash each build) do reload. A long-running SSR process therefore renders NEW components with OLD translations, which surfaces as a React hydration mismatch on non-English pages, and intermittently as "Cannot find module .../bootstrap/ssr/assets/<page>-<hash>.js" when it points at a chunk a rebuild deleted.

After editing lang/*.json: run `pnpm run build:ssr` (plain `pnpm run build` does NOT rebuild the SSR bundle) and restart the SSR process. Production is unaffected because deploys start a fresh process.
