<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\Pages;

use App\Modules\Administration\Panels\Team\Resources\Accounts\AccountResource;
use App\Modules\Core\Finance\Actions\Team\UpdateAccount as UpdateAccountAction;
use App\Modules\Core\Finance\Models\Team\Account;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected UpdateAccountAction $updateAccount;

    /**
     * Resolve the action that updates the account.
     */
    public function boot(UpdateAccountAction $updateAccount): void
    {
        $this->updateAccount = $updateAccount;
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
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('activate')
                        ->action(fn (Account $record): bool => $record->activate())
                        ->visible(fn (Account $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Check)
                        ->color(Color::Green),
                    Actions\Action::make('deactivate')
                        ->action(fn (Account $record): bool => $record->deactivate())
                        ->visible(fn (Account $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::XMark)
                        ->color(Color::Red),
                ])->dropdown(false),
            ]),
        ];
    }

    /**
     * Fill the currencies the account already holds into the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
        $account = $this->getRecord();

        $data['currency_ids'] = $account->currencies()->pluck('currencies.id')->all();

        return $data;
    }

    /**
     * Update the account through the domain action, so adding or
     * removing a currency keeps its balance rows in step.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account $record */
        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = Filament::auth()->user();

        /** @var array<int, string> $currencyIds */
        $currencyIds = $data['currency_ids'] ?? [];

        return $this->updateAccount
            ->for($record)
            ->by($user)
            ->using(['name' => $data['name'], 'type' => $data['type']], $currencyIds);
    }
}
