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
 * Split the transactions a transfer owns into its legs and its fees. The
 * server resolves which row is which and sends the keys, so the matching
 * rules live only on the Transfer model.
 */
export function transferLegs(transfer: Transfer): TransferLegs {
    const transactions = transfer.transactions ?? [];
    const byId = (id: string | null): Transaction | null =>
        (id ? transactions.find((transaction) => transaction.id === id) : null) ?? null;

    return {
        destinationFee: byId(transfer.destination_fee_id),
        destinationLeg: byId(transfer.destination_leg_id),
        originFee: byId(transfer.origin_fee_id),
        originLeg: byId(transfer.origin_leg_id),
    };
}

/**
 * The account on the other side of the transfer the transaction belongs to,
 * so a leg row can say where the money went or came from.
 */
export function counterpartAccountName(transaction: Transaction): string | null {
    const transfer = transaction.transfer;

    if (!transfer) {
        return null;
    }

    const counterpartId =
        transaction.id === transfer.origin_leg_id
            ? transfer.destination_leg_id
            : transaction.id === transfer.destination_leg_id
              ? transfer.origin_leg_id
              : null;

    return (transfer.transactions ?? []).find((candidate) => candidate.id === counterpartId)?.account?.name ?? null;
}
