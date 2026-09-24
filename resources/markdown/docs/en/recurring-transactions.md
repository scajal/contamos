---
title: "Recurring transactions"
description: "Rules that record an income or an expense on their own schedule: how to create one, when it runs, and how to pause, change or delete it."
pro: true
---

A recurring rule records the same income or expense on a schedule: the rent every month, a salary every two weeks, a subscription every year. Each time it comes due, the rule records a transaction just as if a member had entered it. Recurring transactions need [Pro](/en#pricing).

## Creating a rule

The quickest way is from the transaction form. While recording an income or an expense, open **Repeat**, choose how often under **Every**, such as every 1 month or every 2 weeks, and optionally when it **Ends**. The transaction you are recording is the first one, and the rule records the next ones from there. If its date is in the future, the rule waits for it.

You can also open **Recurring** in the sidebar and choose **New rule**. A rule has:

- the **Type**, **Account**, **Amount**, **Currency**, **Category**, **Description** and **Tags** of the transaction it records;
- how often it repeats: every so many days, weeks, months or years;
- **Starts**: the first date, today or later, which also fixes the day of the month or week it falls on;
- the time of day, read in your timezone;
- **Ends**: optional; leave it empty to repeat forever.

A monthly rule that starts on the 31st falls on the last day of shorter months, and returns to the 31st when the month has one.

## When transactions are recorded

Contamos checks the rules every hour and records every transaction that has come due, dated at the moment it was due. Each one updates the balance, keeps its tags, takes the exchange rate of its date and shows in the activity log like any other transaction.

The **Recurring** page lists the rules by their next date, with a sentence describing each schedule.

## When a rule fails

A rule cannot record a transaction when its account has been deactivated, when its currency is no longer on the account, or when there is no exchange rate for the due date. The rule is then marked **Failed** with the reason, and tries again every hour until the cause is fixed.

## Pausing, changing and deleting

From a rule's menu:

- **Pause** stops it. **Resume** starts it again from its next date after today; the dates it missed while paused are skipped, not recorded later.
- **Edit** changes what it records or its schedule. Changes apply from the next occurrence on, and transactions already recorded are left as they are.
- **Delete** removes the rule. The transactions it already recorded stay in the ledger.

## On the Free plan

A Free team sees the **Recurring** page locked, and **Repeat** on the transaction form asks to upgrade. Rules created while the team was on Pro are kept, but record nothing until the team is back on Pro. The dates that passed in the meantime are skipped, so upgrading again never fills the ledger with missed transactions.
