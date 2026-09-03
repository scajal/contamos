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
