---
paths:
  - 'app/Modules/Mcp/Prompts/**'
---

# Prompts

## Prompt shape
A prompt extends Prompt with #[Name] and #[Description], declares arguments() as new Argument(...) entries, and returns Response::text() with numbered steps that name the exact tools and arguments to call.
