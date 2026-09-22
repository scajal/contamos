# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Groups of people who share money: couples, flatmates, families, small side projects. The primary user is a member of one of those groups who keeps the group's shared ledger. Usage is split roughly half and half between phone and desktop (confirmed 2026-09-21): quick entry of a movement from the phone in the moment, and calmer loading and review from a desktop, often at month end. Neither context dominates; both must be first-class.

Spanish is the primary interface language; English is also supported. Users are not accountants and are not expected to know accounting vocabulary.

## Product Purpose

Contamos is a shared multi-currency ledger for any group that shares money. It records the group's real money movements (income, expenses, transfers between accounts) across several accounts and currencies, tagged, so everyone in the group sees the same truth and can review it monthly. Success is a group that keeps its ledger up to date with little friction and trusts what it shows.

## Positioning

A shared ledger, not a debt splitter and not accounting software. Unlike Splitwise-style apps it does not compute who owes whom; unlike accounting tools it has no double-entry vocabulary. Its distinctive mechanism is multi-currency with an exchange rate ("cotización") per movement, so a group that lives across currencies (for example pesos and dollars) can keep one honest ledger.

## Operating Context

- Teams ("equipos") are the unit of sharing. A user can belong to several teams and switches between them from the sidebar header.
- Core objects: accounts (cuentas), transactions (movimientos: ingreso / gasto), transfers between accounts, tags (etiquetas), monthly view, dashboard.
- Transaction entry is amount-first: type and amount lead; date, notes, tags and rate sit behind chips. Income is shown in the success colour, expenses in the destructive colour.
- Exchange rates come from a catalog but are always overridable per movement.
- Team management: members, roles (owner / admin / member), invitations, activity log, team rename and delete, personal account deletion.
- Onboarding checklist in the app layout: create an account, add a first transaction, invite a member.
- Billing through Paddle: Free and Pro plans. Free limits accounts per team, members per team and free teams per user; Pro removes limits. Prices are set in Paddle, not in code, and are not to be repriced after launch.

## Capabilities and Constraints

- Stack: Laravel 12 on PHP 8.4, Inertia v3 with React 19, Tailwind CSS v4, shadcn/ui components, Lucide icons, Recharts for charts, Motion for animation, inertiaui/modal for modal and slideover routes. Wayfinder generates typed routes.
- Interface copy is translation keyed (lang/*.json); every user-facing string goes through `t()`.
- Currencies, transactions and tags are free and unlimited on every plan. Member roles, and future features such as recurring transactions, budgets, attachments, imports and bank sync, are Pro-only.
- Terminology to preserve: "grupo" and "libro" in marketing; in the app, "equipo", "cuenta", "movimiento", "ingreso", "gasto", "transferencia", "etiqueta", "tipo de cambio". Never "hogar" or "casa".
- Undecided: localised per-country pricing (deliberately deferred).

## Brand Commitments

- Name: Contamos. The wordmark is always lowercase "contamos"; in prose it is "Contamos".
- Isotype: the BrandMark component (resources/js/components/brand-mark.tsx): a rounded plate, three ledger lines and a lime #b2e016 dot. It inverts on dark surfaces; no PNG dark variant.
- Typefaces already committed in code: Geist Variable for body, Switzer for headings.
- The 2026-09-21 design pass is a refinement: keep the current identity (Geist, Switzer, lime accent, shadcn base) and improve hierarchy, spacing, copy, states and detail. Not a redesign.

## Evidence on Hand

- Working product with real screens for finance, identity and billing under resources/js/pages.
- Marketing home page at resources/js/pages/marketing/home.
- No testimonials, customer logos, press or case studies exist. Do not fabricate any.

## Product Principles

1. The ledger is shared truth: every member sees the same numbers, and amounts are never ambiguous about currency, sign or rate.
2. Entry must be fast on a phone; review must be calm on a desktop. Both contexts are first-class.
3. Plain language over accounting vocabulary, in Spanish first.
4. Free is a complete personal ledger; Pro removes limits. Upsell never blocks recording money.
5. Honest by default: destructive actions, billing consequences and rate overrides are explained before they happen.

## Accessibility & Inclusion

Standard web accessibility expectations (keyboard navigation, screen reader labels, colour not the only signal for income vs expense). No stricter standard has been mandated.
