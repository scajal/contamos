---
paths:
  - 'app/Modules/**/Commands/**'
---

# Commands

## Artisan command shape
Name commands {Verb}{Plural}Command with #[Signature('module:verb-noun')] and #[Description] attributes. handle(Action $action): int method-injects the Actions, walks models with each/eachById, claims each row with a conditional whereNull(...)->update() so overlapping runs never repeat work, ends with one $this->info() count line and returns self::SUCCESS. Schedule it with withoutOverlapping() and a comment on the cadence.
