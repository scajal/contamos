---
title: "Notifications"
description: "The emails Contamos sends about a team, who receives them, when budget alerts fire, and how to turn each kind off or unsubscribe."
pro: false
---

Contamos emails a team's Owner and Admins when something happens that they would want to know about: people joining or leaving, a budget running out, a recurring transaction that could not be recorded, and the month that just ended. Each person chooses which of these they receive, team by team.

## Who receives them

Notifications go to the team's Owner and Admins. On the Free plan everyone except the owner acts as an Admin, so every member receives them. See [Members and roles](/en/docs/members-and-roles).

The person who caused a change never gets an email about it: whoever removes a member or changes a role is not told what they just did.

Every email is sent in the language you chose in Contamos, or in English if you never chose one.

## What Contamos emails

| Email | When it is sent | Plan |
| --- | --- | --- |
| **Member changes** | Someone joins, leaves, is removed or has their role changed. | Any |
| **Budget alerts** | A running budget reaches its warning threshold or goes over its amount. | Pro |
| **Recurring failures** | A recurring transaction could not be recorded. | Pro |
| **Monthly summary** | A month has ended. | Any |

### Member changes

Contamos emails when someone accepts an invitation and joins the team, leaves it, is removed from it, or has their role changed. A role change names the old role and the new one. Deleting your account counts as leaving each team you shared.

### Budget alerts

A budget can carry a warning threshold, set with **Warn me at** on the budget's form, or by an AI assistant. Contamos then emails:

- when the month's spending reaches that percentage of the amount;
- when the month's spending goes over the amount.

The alert for going over is sent whether or not a threshold is set, and a single transaction that passes both sends only that one. Each alert is sent at most once per budget per month, and a new month starts both afresh. Raising the budget's amount so that spending falls back under a threshold makes that alert able to fire again. Editing or deleting transactions does not.

Only running budgets alert, not paused, scheduled or ended ones. Spending counts wherever it was recorded: in the app, by an AI assistant, by a recurring transaction or by an import. An import sends at most one email per budget. The spent amount and the percentage are the same ones the **Budgets** page shows, so income filed under the budget still adds to what can be spent. See [Budgets](/en/docs/budgets).

### Recurring failures

Contamos emails when a recurring transaction cannot be recorded, because its account was deactivated, its currency is no longer on the account, or there is no exchange rate for the due date. The email links to the rule.

You get one email when the rule starts failing, and nothing more while it stays failed, even though Contamos tries again every hour. Once the rule records again or is edited, a later failure sends a new email. See [Recurring transactions](/en/docs/recurring-transactions).

### Monthly summary

On the 1st of each month at 09:00 UTC, Contamos emails a summary of the month before:

- income, expenses and net, in each currency;
- the top five spending categories, in each currency;
- each budget's amount, what was spent and how it ended;
- a link to that month's **Monthly** report.

A team with no transactions in the month gets no summary.

## Other emails

- **Pro ended.** When a team goes from Pro back to Free, its owner gets one email explaining what changes. It is sent when the subscription actually ends or a granted Pro period expires, not when a cancellation is only scheduled. This email cannot be turned off. See [Plans and limits](/en/docs/plans-and-limits).
- **Payments.** Failed payments and scheduled cancellations are emailed by Paddle, which handles billing, not by Contamos.
- **Invitations.** An invitation is emailed to the person invited. See [Members and roles](/en/docs/members-and-roles).

## Choosing what you receive

Open **Configuration** in the sidebar, then the **Notifications** tab. It shows a switch for each of the four kinds above: **Member changes**, **Budget alerts**, **Recurring failures** and **Monthly summary**. Every switch starts on.

The switches are yours alone and apply to that team only: turning one off changes nothing for the rest of the team, or for your other teams. Only Owners and Admins see the tab, since they are the ones who receive these emails.

## Unsubscribing from an email

Every email you can switch off ends with two links:

- **Unsubscribe** turns off that kind of email for that team, at once and without signing in. The link never expires.
- **Manage notifications** opens the **Notifications** tab, to change any of the switches.

AI assistants cannot change notification settings. They are managed from the team's settings in the app.
