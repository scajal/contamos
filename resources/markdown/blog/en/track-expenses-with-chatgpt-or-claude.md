---
title: "Track expenses with ChatGPT or Claude, properly"
description: "A chat is not a ledger. How to use ChatGPT or Claude as an expense tracker, recording and asking about spending without losing anything."
published: "2026-11-07"
author: "Martina Olivera"
image: "/images/blog/track-expenses-with-chatgpt-or-claude.png"
translations: {"es": "llevar-gastos-con-chatgpt-o-claude"}
---

More and more people are trying to track their spending by talking to an AI assistant: "I spent 45 on groceries", "how much have I spent on food this month?". It is a great idea, because telling a chat about an expense is much faster than opening a spreadsheet. But if it all happens inside a conversation, sooner or later the numbers stop adding up.

This guide explains why, and how to use ChatGPT or Claude for your expenses in a way that actually works.

## What an assistant does really well

- **Understanding you as you talk.** "Paid the electricity, 60" is enough; there is no form to fill in.
- **Answering questions.** "What did we spend most on in September?", "how much did the trip cost?".
- **Summarising and comparing.** One month against another, one category against another, in plain language.

## Why a chat is not a ledger

The problem is not the assistant, it is where the data lives:

- **A conversation is not a database.** In long chats an assistant can lose track of expenses you mentioned weeks ago, or add them up wrong. Start a new chat and you start from zero.
- **It is only yours.** If you share expenses with a partner or friends, each of you has your own chat with your own version of the numbers.
- **You cannot review it.** There is no list of transactions to filter, correct or export; there are messages.
- **The model does the maths.** Adding up dozens of amounts from memory is not its strength. A wrong total in a chat goes unnoticed until it is too late.

## The way that works: the assistant talks, the ledger keeps

The fix is to separate the two jobs. The assistant understands what you say; the data lives in a real ledger, with balances worked out by software rather than by the model.

That is what MCP (Model Context Protocol) makes possible. It is the standard ChatGPT, Claude and other assistants use to connect to apps. With an app connected over MCP:

- when you say "I spent 45 on groceries", the assistant **records a transaction** in the app, not in the conversation;
- when you ask "how much have we spent on food?", the assistant **asks the app**, which does the exact sum;
- tomorrow, in another chat or from another assistant, the data is still there.

## Is it safe?

A few precautions apply to any use of AI with your money:

- **Never give an assistant your bank login** or card details.
- Prefer apps that **do not need to connect to your bank**: you decide what gets recorded.
- Check **what the assistant can do** once connected, and how to disconnect it.

## Prompts that work

Once connected, try things like:

- "Record 45 of groceries on the debit card account, tagged food."
- "How much did we spend in total in October, by category?"
- "We paid 80 dollars for dinner on the trip, from the kitty."
- "Compare what we spent on food in September and October."

## Frequently asked questions

### Does ChatGPT remember my expenses?

Not reliably. Memory features keep some facts between chats, but they are not a ledger: you cannot list, correct or export what it remembers, and it does not add things up exactly.

### Is it safe to share my finances with an AI assistant?

Share amounts and descriptions, never bank logins or card numbers. An app connected over MCP only sees what is recorded in it, and you can disconnect it at any time.

### Can it read my bank account?

Not through Contamos, which does not connect to banks. You record transactions yourself or through the assistant. If you share money in more than one currency, see [how to track shared money in two currencies](/en/blog/shared-money-in-two-currencies), or [how to run a shared kitty](/en/blog/how-to-run-a-shared-kitty) if your group pools money.

## Doing it with Contamos

[Contamos](/en) is a shared ledger for any group that shares money, and it runs an MCP server you can connect to [Claude or ChatGPT](/en/docs/ai-assistants):

- the assistant can read balances, transactions and the monthly report, and record income, expenses and transfers;
- it acts as you, with your role on each team, and everything it records is visible to the other members, along with [who recorded it](/en/docs/members-and-roles);
- balances are kept [per currency](/en/docs/accounts-and-currencies), and the assistant never invents an exchange rate: when there is no rate for a date, it asks you;
- before it changes or deletes anything, it asks you first, and you can disconnect it from the app at any time.

Contamos does not connect to your bank or move money: it records what you or your assistant tell it. It works on every plan, and the [AI assistants guide](/en/docs/ai-assistants) walks you through connecting one.
