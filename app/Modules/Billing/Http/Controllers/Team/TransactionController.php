<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Models\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Http\RedirectResponse;

class TransactionController
{
    /**
     * Redirect to the Paddle-hosted invoice PDF for the
     * specified resource.
     */
    public function invoice(Team $team, Transaction $billingTransaction): RedirectResponse
    {
        return $billingTransaction->redirectToInvoicePdf();
    }
}
