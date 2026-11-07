# contamos

One shared ledger for everyone you share money with.

Contamos is a multi-currency ledger for groups that share money: couples, flatmates, families, small side projects. Every member records the group's real money movements (income, expenses and transfers between accounts) and everyone sees the same numbers. It is not a debt splitter and it is not accounting software. Each movement can carry its own exchange rate, so a group that lives across currencies (for example pesos and dollars) can keep one honest ledger.

> **Status:** Contamos ran as a hosted service at contamos.xyz until November 2026. The service has been shut down and this repository is the complete source code, published as-is under the MIT license. It is not actively maintained, but you are welcome to run it, fork it and learn from it.

The interface is in Spanish first, with English also supported.

## Features

- **Teams.** A team is the unit of sharing. Users can belong to several teams, invite members by email and give them roles (owner, admin, member). Teams also keep an activity log.
- **Accounts and movements.** You get accounts in any currency, income and expense movements, transfers between accounts, and tags.
- **Multi-currency.** Exchange rates come from a currency catalog, and you can override the rate on any movement. Rates for the Uruguayan peso sync daily from the BCU (Banco Central del Uruguay).
- **Monthly view, dashboard and charts.** You can review a month at a glance.
- **Recurring movements, budgets and a monthly summary email.**
- **MCP server.** AI assistants can read and write a team's ledger over the [Model Context Protocol](https://modelcontextprotocol.io), with OAuth authorization.
- **Billing.** Free and Pro plans run through Paddle. An operator can also grant Pro to a team by hand from the administration panel.
- **Administration.** Filament panels let operators manage users, teams and the currency catalog.

## Stack

- PHP 8.4, Laravel 13
- Inertia v3, React 19, TypeScript
- Tailwind CSS v4, shadcn/ui, Lucide icons, Recharts, Motion
- Filament 5 for the administration panels
- Laravel Wayfinder for typed routes, Laravel Pennant for plan features, Laravel MCP
- Pest for tests, PHPStan (Larastan), Rector and Pint for static analysis and style
- SQLite locally; MySQL 8 in production

## Requirements

- PHP 8.4 with the usual Laravel extensions and `bcmath`
- Composer 2
- Node 22+ and pnpm 11
- A [WorkOS](https://workos.com) account for sign-in (AuthKit). Contamos has no password login, so you need a WorkOS application before you can sign in. The free tier is enough.

Optional:

- [Paddle](https://www.paddle.com) (a sandbox account works) to use subscriptions and checkout
- [Resend](https://resend.com) to send real email. Locally, `MAIL_MAILER=log` writes emails to the log instead.
- Google Analytics, through `GOOGLE_ANALYTICS_MEASUREMENT_ID`

## Getting started

```bash
git clone https://github.com/scajal/contamos.git
cd contamos
composer setup
```

`composer setup` installs the PHP and JS dependencies, copies `.env.example` to `.env`, generates the app key, creates the SQLite database, runs the migrations and builds the frontend.

Next, fill in `.env`:

| Key | Purpose |
| --- | --- |
| `WORKOS_CLIENT_ID`, `WORKOS_API_KEY`, `WORKOS_AUTHKIT_DOMAIN` | Sign-in. Add `${APP_URL}/authenticate` as a redirect URI in WorkOS. |
| `ADMINISTRATION_OPERATORS` | A comma separated list of emails allowed into the Filament panels at `/administration`. |
| `PADDLE_*`, `PRODUCT_PRO_*` | Billing. These are the Paddle product and price ids for the Pro plan. |
| `RESEND_API_KEY` | Outgoing email, when `MAIL_MAILER=resend`. |

Seed the base currency (the Uruguayan peso, UYU), then start the dev processes (the web server, queue worker, logs and Vite):

```bash
php artisan db:seed
composer dev
```

The app runs at `http://localhost:8000`.

In production, also run the scheduler (`php artisan schedule:run` every minute). It syncs exchange rates, generates recurring movements and sends the monthly summaries.

## Tests and checks

```bash
composer test          # Rector (dry run), PHPStan, Pint and the Pest suite
composer test:coverage # Pest with a 100% coverage threshold
composer test:types    # Pest type coverage, 100% threshold
pnpm run check         # Frontend lint and format check
pnpm run types:check   # TypeScript
```

CI (`.github/workflows/tests.yml`) runs the suite on SQLite and again on MySQL 8.4 in strict mode.

## Project layout

The application code lives in modules under `app/Modules`:

| Module | What it holds |
| --- | --- |
| `Core/Identity` | Users, teams, members, roles, invitations, activity log |
| `Core/Finance` | Accounts, movements, transfers, tags, budgets, recurring rules, reports |
| `Core/Catalog` | Currencies and exchange-rate quotes, and the quote providers |
| `Billing` | Plans, Paddle subscriptions, plan features |
| `Authentication` | WorkOS AuthKit sign-in |
| `Administration` | Filament panels |
| `Mcp` | The MCP server, tools and prompts |
| `Marketing` | Public pages, legal pages, sitemap |
| `Support` | Shared concerns, middleware and helpers |

The React pages live in `resources/js/pages`, and translations in `lang/` (`es.json`, `en.json`).

## Working with AI agents

The repository was built with coding agents and keeps their context in the tree:

- `AGENTS.md` and `CLAUDE.md` hold the Laravel Boost guidelines.
- `.ai/rules/` holds the project rules, mapped to file globs in `.ai/rules/index.md`.
- `PRODUCT.md` and `DESIGN.md` hold the product and design briefs.
- `.agents/skills/` holds the agent skills.

Note that the legal pages under `resources/markdown/legal` were written for the hosted service. They do not apply to your own deployment.

## License

[MIT](LICENSE) © 2026 Santiago Cajal
