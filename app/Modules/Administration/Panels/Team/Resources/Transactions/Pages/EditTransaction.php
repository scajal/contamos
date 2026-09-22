<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transactions\TransactionResource;
use App\Modules\Core\Finance\Actions\Team\DeleteTransaction as DeleteTransactionAction;
use App\Modules\Core\Finance\Actions\Team\UpdateTransaction as UpdateTransactionAction;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected DeleteTransactionAction $deleteTransaction;

    protected UpdateTransactionAction $updateTransaction;

    /**
     * Resolve the actions that write the transaction.
     */
    public function boot(DeleteTransactionAction $deleteTransaction, UpdateTransactionAction $updateTransaction): void
    {
        $this->deleteTransaction = $deleteTransaction;
        $this->updateTransaction = $updateTransaction;
    }

    /**
     * Get the header actions.
     *
     * @return array<int, \Filament\Actions\ActionGroup>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make()
                    ->using(function (Transaction $record): void {
                        /** @var \App\Modules\Core\Identity\Models\Team $team */
                        $team = Filament::getTenant();

                        $this->deleteTransaction->for($team)->using($record);
                    }),
            ]),
        ];
    }

    /**
     * Fill the tags the transaction carries into the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Transaction $transaction */
        $transaction = $this->getRecord();

        $data['tag_ids'] = $transaction->tags()->pluck('tags.id')->all();

        return $data;
    }

    /**
     * Update the transaction through the domain action, so the
     * balance it moved is reverted and applied again.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Transaction $record */
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var array<int, string> $tagIds */
        $tagIds = $data['tag_ids'] ?? [];

        return $this->updateTransaction
            ->for($team)
            ->using($record, TransactionResource::attributes($data), $tagIds);
    }
}
