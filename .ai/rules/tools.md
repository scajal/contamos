---
paths:
  - 'app/Modules/Mcp/Tools/**'
---

# Tools

## Pagination per surface
MCP list tools use cursorPaginate driven by a 'cursor' argument; page computed collections with forPage(). Never paginate() or simplePaginate() in a tool.

## Money is bcmath on strings
Carry amounts as decimal strings and compute or compare them with bcadd/bcsub/bcmul/bcdiv/bccomp at an explicit SCALE of 18. Never use floats, round() or number_format for money.
