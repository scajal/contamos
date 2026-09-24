---
title: "Exchange rates"
description: "How every transaction stores the exchange rate of its day, where the rate comes from, and when and how to type your own."
pro: false
---

Money in Contamos stays in the currency it was recorded in. Balances and reports are never converted into one currency, so a team that lives in pesos and dollars sees how many pesos and how many dollars it has. What exchange rates add is a record of what each transaction was worth on the day it happened.

## One rate per transaction

Every transaction stores its own exchange rate, including each side of a transfer and each opening balance. A rate is written as how much of the currency one US dollar buys, such as **1 US dollar = 40.12 UYU**.

Because the rate is stored with the transaction, it never changes afterwards, even as the market moves. The transaction's details show its rate and its approximate value in US dollars, and the CSV export includes the rate of every transaction.

## Where the rate comes from

Contamos keeps a catalogue of rates for the currencies it supports, updated automatically. When you record a transaction without touching the rate, it takes the latest rate in the catalogue at the transaction's date and time.

The **Rate** option on the transaction form shows the rate that will be used and the date it comes from.

## Typing your own rate

The rate you actually got is often not the published one: a card, an exchange house or a friend gives you their own. Open **Rate** and type it, and that rate is stored instead of the catalogue's.

- A rate you type stays as it is if you change the transaction's date.
- A rate you did not type follows the date: pick another day and it takes that day's rate.
- Editing a transaction keeps the rate it was saved with.

## When there is no rate

If the catalogue has no rate for a currency at the date you picked, the **Rate** option opens on its own and asks for one, since a transaction cannot be saved without it. The same applies to an opening balance.
