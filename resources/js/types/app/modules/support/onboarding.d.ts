/**
 * JSON shape of the setup steps of the current team, shared by
 * App\Modules\Support\Http\Middleware\HandleInertiaRequests.
 */
export interface Onboarding {
    has_account: boolean;
    has_member: boolean;
    has_transaction: boolean;
}
