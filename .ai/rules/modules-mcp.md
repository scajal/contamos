---
paths:
  - 'tests/Feature/Modules/Mcp/**'
---

# Modules Mcp

## MCP tool tests go through the server test client
Test a tool with ContamosServer::actingAs($user)->tool(Tool::class, $args) using a user from mcpMember(); assert the payload with assertStructuredContent(fn (AssertableJson $json) => ...->etc()) or assertHasErrors([...]), then expect() the database. Reserve mcpCall() for the auth middleware.
