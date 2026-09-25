---
paths:
  - 'app/Modules/**/Http/Requests/**/Concerns/**'
---

# Concerns

## Rules traits take plain arrays
Split request concerns in two: Defines*Rules / Validates*Inputs traits build rule arrays from plain parameters (Team $team, array $input, ?Model $existing) so MCP tools can reuse them; Resolves*Inputs / Filters* traits carry @mixin FormRequest and read the request. Keep both next to the Store and Update requests they serve.
