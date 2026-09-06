---
paths:
  - app/Modules/Core/Identity/Models/Team.php
---

# Identity Models

## Team slugs get a random uniqueness suffix
When a team slug collides, append a hyphen plus 6 lowercase alphanumeric characters from Str::random(6). Do not use an incrementing numeric suffix. Keep the unsuffixed slug when it is free, including when a similarly prefixed slug such as acme-extra already exists. Retry the random suffix until the candidate is unique. In tests, pin the suffix with Str::createRandomStringsUsing().
