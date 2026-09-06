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

## Team invitations: derived status, accept flow outside team group
TeamInvitation status (pending/accepted/expired) is derived from accepted_at and expires_at via isPending()/isAccepted()/isExpired(), the pending/status scopes, and invitationStatus() in resources/js/lib/utils.ts. Do not add a status enum or column. There is no rejection: invitees accept or ignore; members cancel (delete) or resend (renew() regenerates code + expiry). The accept routes (identity.invitations.show/accept) live outside the teams/{team} group because EnsureTeamMembership 403s non-members; the invitee's email must match the invitation (isFor()). Invitations are emailed on-demand (Notification::route('mail', $email)) so unregistered invitees receive them.
