<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages;

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\BudgetPeriodResource;
use App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod;
use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBudgetPeriod extends EditRecord
{
    protected static string $resource = BudgetPeriodResource::class;

    protected DeleteBudgetPeriod $deleteBudgetPeriod;

    protected UpdateBudgetPeriod $updateBudgetPeriod;

    /**
     * Resolve the actions that write budget periods.
     */
    public function boot(DeleteBudgetPeriod $deleteBudgetPeriod, UpdateBudgetPeriod $updateBudgetPeriod): void
    {
        $this->deleteBudgetPeriod = $deleteBudgetPeriod;
        $this->updateBudgetPeriod = $updateBudgetPeriod;
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
                Actions\DeleteAction::make()
                    ->using(function (BudgetPeriod $record): bool {
                        /** @var \App\Modules\Core\Identity\Models\Team $team */
                        $team = Filament::getTenant();

                        $this->deleteBudgetPeriod
                            ->for($team)
                            ->using($record);

                        return true;
                    }),
            ]),
        ];
    }

    /**
     * Show the target the period was opened on, which the form
     * then leaves alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod $record */
        $record = $this->getRecord();

        $data['target_kind'] = $record->targetKind()->value;

        return $data;
    }

    /**
     * Change the period through the domain action, keeping what
     * it follows as it was.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod $record */
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = Filament::auth()->user();

        return $this->updateBudgetPeriod
            ->for($team)
            ->by($user)
            ->using($record, [
                'amount' => $data['amount'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'from_month' => $data['from_month'] ?? null,
                'until_month' => $data['until_month'] ?? null,
            ]);
    }
}
