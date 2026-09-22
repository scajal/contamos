<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transfers\TransferResource;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer as CreateTransferAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransfer extends CreateRecord
{
    protected static string $resource = TransferResource::class;

    protected static bool $canCreateAnother = false;

    protected CreateTransferAction $createTransfer;

    /**
     * Resolve the action that records the transfer.
     */
    public function boot(CreateTransferAction $createTransfer): void
    {
        $this->createTransfer = $createTransfer;
    }

    /**
     * Record the transfer through the domain action, which writes
     * both of its legs and the balances they move.
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

        return $this->createTransfer
            ->for($team)
            ->by($user)
            ->using(TransferResource::attributes($data), $tagIds);
    }
}
