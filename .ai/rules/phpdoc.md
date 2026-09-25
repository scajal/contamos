---
paths:
    - "app/**"
    - "tests/**"
    - "database/factories/**"
---

# PHPDoc and comments

## FQDN class references

In PHPDoc and comments, reference application (and other project) classes, enums, and traits with a leading-backslash FQDN, even when the file already imports the class with `use`. Do not use the short imported name in docs.

Example: `\App\Modules\Core\Identity\Models\User`, not `User`.

## Narrate steps with inline comments
Narrate method bodies: put a blank line and a short `// Sentence.` comment above each meaningful step, including the arms of a `match` that needs explaining. Give every class, method, property, and enum case a docblock that says what it is, not how it works. Wrap comment and docblock lines at roughly 60 characters. This overrides the general "prefer PHPDoc over inline comments" guidance.

## CarbonImmutable static calls
Build dates with CarbonImmutable::now(), ::parse() and ::createFromFormat() static calls. Do not use the now()/today() helpers, the Date facade, or Carbon::.
