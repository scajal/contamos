---
paths:
  - 'resources/js/pages/finance/teams/transactions/**'
---

# Transactions

## Day-grouped transaction tables share fixed columns
Transactions index renders one table per day. Use table-fixed with the same column width classes (and min-w so mobile still scrolls) on every day table and the skeleton so columns stay aligned across groups. Auto-sized tables drift when content differs.
