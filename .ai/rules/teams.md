---
paths:
    - "app/Modules/Core/Finance/**"
    - "app/Modules/Core/Identity/**"
---

# Teams

## Team middleware and scoped queries

Protect team routes with `EnsureTeamMembership`, use `scopeBindings()` on nested resource routes, and load or create records via `$team->relation()` or validation rules scoped to the route team.

## Money as major-unit decimals
Persist and pass monetary amounts as major-unit decimal strings (`decimal:18` casts). Do not introduce Brick Money objects or minor-unit integers.

## Team tags live in Finance
Reusable team tags are Finance models under Team/Tag with finance.teams.tags.* CRUD (activate/deactivate, no destroy). Catalog stays currencies and quotes. Attach tags through Support HasTags after the host row exists. Do not use spatie/laravel-tags, and never mass-assign tag_ids onto the tagged model.
