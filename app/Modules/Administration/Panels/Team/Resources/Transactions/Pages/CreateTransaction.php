<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transactions\TransactionResource;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction as CreateTransactionAction;
use App\Modules\Support\Helpers\Instant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected static bool $canCreateAnother = false;

    protected CreateTransactionAction $createTransaction;

    /**
     * Resolve the action that records the transaction.
     */
    public function boot(CreateTransactionAction $createTransaction): void
    {
        $this->createTransaction = $createTransaction;
    }

    /**
     * Record the transaction through the domain action, which applies
     * its effect on the account balance and logs it for the team.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = Filament::auth()->user();

        /** @var array<int, string> $tagIds */
        $tagIds = $data['tag_ids'] ?? [];

        return $this->createTransaction
            ->for($team)
            ->by($user)
            ->using([
                ...TransactionResource::attributes($data),
                'quote' => $this->resolveQuote($data),
            ], $tagIds);
    }

    /**
     * Get the rate the amount is converted with, falling back to the
     * catalog quote observed as of the transaction's instant.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveQuote(array $data): float|int|string|null
    {
        $quote = $data['quote'] ?? null;

        if (is_numeric($quote)) {
            return $quote;
        }

        $currencyId = $data['currency_id'] ?? null;

        $currency = is_string($currencyId)
            ? Currency::query()->whereKey($currencyId)->first()
            : null;

        return Instant::resolveQuote($currency, $data['occurred_at'] ?? null)?->rate;
    }
}
