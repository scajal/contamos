---
title: "Transactions and transfers"
description: "How to record income and expenses, move money between accounts, edit or delete what was recorded, filter the list and export it to CSV."
pro: false
---

A transaction is money coming into an account or leaving it. A transfer is money moving between two of the team's own accounts. Together they are everything the ledger records.

## Income and expenses

Open the **+** menu in the header and choose **New income** or **New expense**, or use the **Actions** menu on the **Transactions** page. Each transaction has:

- **Amount**, always positive: the type says whether money came in or went out.
- **Account** and currency. The account picker only appears when the team has more than one active account, and the currency must be one the account holds.
- **Category**, required. Categories are a fixed list, one for income and one for expenses, such as **Food**, **Housing** or **Salary**.
- The date and time, today by default.
- **Notes**, a free description.
- **Tags**, as many as you like. See [Tags](/en/docs/tags).
- **Rate**, the exchange rate of the day. See [Exchange rates](/en/docs/exchange-rates).

The account's balance updates as soon as you save, and the confirmation offers **Undo**.

With Pro, the **Repeat** option turns the transaction into a rule that records it again on a schedule. See [Recurring transactions](/en/docs/recurring-transactions).

## Transfers

A transfer moves money between two accounts of the same team, so it is neither income nor expense. Choose **Transfer** from the **+** menu and fill in both sides:

- **Origin** and **Destination**: an account and a currency on each side. The two accounts must be different.
- **Amount sent** and **Amount received**. With the same currency on both sides, the amount received follows the amount sent until you change it.
- Optionally, an **Origin fee** and a **Destination fee**: what the bank or network charged on each side.

The currencies on the two sides can differ. Moving dollars from a bank account to pesos in cash is one transfer: you type what left one account and what arrived in the other, and each side keeps its own rate.

A transfer records one outgoing movement on the origin account and one incoming movement on the destination account. Each fee is recorded as an expense on its side, described as a transfer fee. Notes and tags apply to the whole transfer. On the **Monthly** page, transfers show as money moved out and moved in, never as income or expense.

A movement that belongs to a transfer is edited or deleted through the transfer, not on its own.

## Editing and deleting

Every row on the **Transactions** page has a menu with **View**, **Edit** and **Delete**. Deleting a transaction reverses its effect on the balance and cannot be undone. Deleting a transfer removes both of its sides and its fees.

When you edit a transaction, it keeps the exchange rate it was recorded with, even if you change its date.

## Finding transactions

The **Transactions** page lists every transaction of the team, newest first. Search by ID or description, or open **More filters** to narrow the list by type, category, account, currency, tag or date.

## Exporting to CSV

Choose **Export CSV** from the **Actions** menu to download the transactions the list is showing, with the filters you have on. The file has the date, type, category, amount, currency, description, account, tags, who recorded it and its exchange rate.

## History on the Free plan

On the Free plan, the list, the export, the dashboard and the monthly report show the last 6 months, counting the current one. Older transactions are not removed and still count in every balance; Pro shows your full history. See [Plans and limits](/en/docs/plans-and-limits).
