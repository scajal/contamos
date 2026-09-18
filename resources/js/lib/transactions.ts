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
