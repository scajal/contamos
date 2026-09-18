import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { Transfer } from "@/types/app/modules/core/finance/models/team/transfer";

/**
 * The transactions a transfer owns, resolved out of the loaded relation.
 */
export type TransferLegs = {
    destinationFee: Transaction | null;
    destinationLeg: Transaction | null;
    originFee: Transaction | null;
    originLeg: Transaction | null;
};

/**
 * Split the transactions a transfer owns into its legs and its fees, matching
 * each fee to the side it sits on, as the Transfer model does server side.
 */
export function transferLegs(transfer: Transfer): TransferLegs {
    const transactions = transfer.transactions ?? [];
    const originLeg = transactions.find((transaction) => transaction.type === "transfer_out") ?? null;
    const destinationLeg = transactions.find((transaction) => transaction.type === "transfer_in") ?? null;
    const feeOn = (leg: Transaction | null): Transaction | null =>
        leg
            ? (transactions.find(
                  (transaction) => transaction.type === "expense" && transaction.account_id === leg.account_id,
              ) ?? null)
            : null;

    return {
        destinationFee: feeOn(destinationLeg),
        destinationLeg,
        originFee: feeOn(originLeg),
        originLeg,
    };
}

/**
 * The account on the other side of the transfer the transaction belongs to,
 * so a leg or fee row can say where the money went or came from.
 */
export function counterpartAccountName(transaction: Transaction): string | null {
    const transactions = transaction.transfer?.transactions ?? [];

    return (
        transactions.find(
            (candidate) =>
                (candidate.type === "transfer_in" || candidate.type === "transfer_out") &&
                candidate.account_id !== transaction.account_id,
        )?.account?.name ?? null
    );
}
