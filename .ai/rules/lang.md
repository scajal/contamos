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
