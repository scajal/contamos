---
paths:
  - 'app/Modules/Mcp/**'
---

# Mcp

## Tool anatomy
A tool extends Laravel\Mcp\Server\Tool with #[Name('snake_case')], a #[Description] that spells out inputs, defaults, side effects and the sibling tool to prefer, and #[IsReadOnly] or #[IsDestructive] where they apply. Define inputs in schema(JsonSchema $schema): array by spreading shared fragments (teamSchema, cursorSchema, timezoneSchema, resource schemas). handle(Request $request, ...) method-injects Core Actions and Reports; never re-implement domain logic in a tool. Tools and prompts are auto-discovered from the folder, so never register them by hand.

## Team scoping via forTeam
Every tool but list_teams takes a required team slug and wraps its body in $this->forTeam($request, fn (Team $team, User $user) => ...), which resolves the team among the user's own, checks the MCP feature and scopes Pennant. Gate plan features with $this->ensureFeature($team, Feature::X) mirroring the web route. Never read the user's current team.

## Validation shared with web
Validate tool input with the same rule traits the Form Requests use (Http/Requests/Team/*/Concerns Defines*Rules, Validates*, Resolves*) via $request->validate([...]), after normalising with NormalizesInputs (dates to UTC, currency codes, tag names, recorded quotes). Malformed input throws ValidationException::withMessages; a refusal the assistant should relay throws ToolError with a full sentence, and a plan refusal appends the upgrade URL.

## Structured responses
Return Response::structured([...]) keyed by resource name (account, balances, deleted); never Response::text or json from a tool. Present records through Presents* concerns: amounts as decimal strings via amount(), instants via instant() (UTC plus a _local key), lists via page() as {items, next_cursor}. Refresh a written model before presenting it.

## Tool folders and concerns
Group tools by resource folder (Tools/Accounts, Tools/Budgets, ...) with {Create,Update,Delete,Get,List,Set*}{Noun} class names. Put shared logic in Tools/Concerns named InteractsWithX (schema, validation and presentation for one resource), PresentsX, ResolvesXArguments, each with @mixin \Laravel\Mcp\Server\Tool and a docblock listing the traits it requires.
