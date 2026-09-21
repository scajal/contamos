/**
 * JSON shape of the history window on the transactions index, built by
 * App\Modules\Core\Finance\Http\Controllers\Team\TransactionController.
 *
 * A plan with unlimited history is not limited and carries no window:
 * `since` and `months` are null and nothing is hidden.
 */
export interface TransactionHistory {
    has_older: boolean;
    limited: boolean;
    months: number | null;
    since: string | null;
}
