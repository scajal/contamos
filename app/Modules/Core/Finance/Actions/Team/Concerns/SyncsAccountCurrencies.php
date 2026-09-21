<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Support\Helpers\TeamActivity;

/**
 * Replaces the currencies an account holds a balance in, recording
 * every slot that appeared or vanished, since a pivot row is not a
 * model change the activity log would otherwise see.
 */
trait SyncsAccountCurrencies
{
    /**
     * Replace the account's currencies with the given ones.
     *
     * @param  array<string>  $currencyIds
     */
    protected function syncCurrencies(Account $account, array $currencyIds): void
    {
        $changes = $account->currencies()->sync($currencyIds);

        // The pivot key is the currency the balance slot is for.
        $currency = $account->currencies()->getRelatedPivotKeyName();

        // Every currency the account now holds a balance in.
        foreach ($changes['attached'] as $currencyId) {
            TeamActivity::record($account, 'currency_attached', ['attributes' => [$currency => $currencyId]]);
        }

        // Every currency the account no longer holds a balance in.
        foreach ($changes['detached'] as $currencyId) {
            TeamActivity::record($account, 'currency_detached', ['old' => [$currency => $currencyId]]);
        }
    }
}
