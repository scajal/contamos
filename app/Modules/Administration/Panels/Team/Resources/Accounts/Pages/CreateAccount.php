<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\Pages;

use App\Modules\Administration\Panels\Team\Resources\Accounts\AccountResource;
use App\Modules\Core\Finance\Actions\Team\CreateAccount as CreateAccountAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected static bool $canCreateAnother = false;

    protected CreateAccountAction $createAccount;

    /**
     * Resolve the action that creates the account.
     */
    public function boot(CreateAccountAction $createAccount): void
    {
        $this->createAccount = $createAccount;
    }

    /**
     * Create the account through the domain action, so the balance
     * rows it holds per currency are written with it.
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

        /** @var array<int, string> $currencyIds */
        $currencyIds = $data['currency_ids'] ?? [];

        return $this->createAccount
            ->for($team)
            ->by($user)
            ->using(['name' => $data['name'], 'type' => $data['type']], $currencyIds);
    }
}
