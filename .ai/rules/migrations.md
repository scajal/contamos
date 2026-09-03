---
paths:
    - "database/migrations/**"
---

# Migrations

## ULID primary keys on domain models

Domain tables use $table->ulid("id")->primary(). Pivot tables may use $table->id().

## Foreign keys via foreignIdFor

Define foreign keys with $table->foreignIdFor(Model::class), chaining ->constrained() and cascade modifiers when needed.

## Store enums as string columns
Persist enum-backed attributes as `$table->string(...)` and cast them to the PHP enum in `casts()`. Do not use `$table->enum()`.
