import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";

/**
 * The sign shown before a transaction's amount, by type.
 */
export const TRANSACTION_AMOUNT_SIGN: Record<TransactionType, string> = {
    expense: "-",
    income: "+",
    opening_balance: "+",
};

/**
 * The color of a transaction's amount, by type.
 */
export const TRANSACTION_AMOUNT_COLOR: Record<TransactionType, string> = {
    expense: "text-destructive",
    income: "text-success",
    opening_balance: "text-muted-foreground",
};

/**
 * Shared column widths so day-grouped tables stay aligned.
 * Fixed layout + identical widths keep columns consistent across separate tables.
 */
export const TRANSACTION_TABLE_CLASS = "table-fixed min-w-[44rem]";
export const TRANSACTION_COLUMNS = {
    time: "w-16",
    account: "w-40",
    description: "w-48",
    tags: "w-36",
    amount: "w-36",
    actions: "w-12",
} as const;
