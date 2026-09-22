<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages;

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\BudgetPeriodResource;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod as CreateBudgetPeriodAction;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBudgetPeriod extends CreateRecord
{
    protected static string $resource = BudgetPeriodResource::class;

    protected static bool $canCreateAnother = false;

    protected CreateBudgetPeriodAction $createBudgetPeriod;

    /**
     * Resolve the action that opens the budget period.
     */
    public function boot(CreateBudgetPeriodAction $createBudgetPeriod): void
    {
        $this->createBudgetPeriod = $createBudgetPeriod;
    }

    /**
     * Open the period through the domain action, so the overlap
     * and target guards a plain Eloquent write would skip are
     * the same ones the app itself runs.
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

        return $this->createBudgetPeriod
            ->for($team)
            ->targeting($this->target($data))
            ->by($user)
            ->using([
                'amount' => $data['amount'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'from_month' => $data['from_month'] ?? null,
                'until_month' => $data['until_month'] ?? null,
            ]);
    }

    /**
     * The category or tag the form chose, which the action then
     * refuses when it is neither.
     *
     * @param  array<string, mixed>  $data
     */
    protected function target(array $data): Category|Tag|null
    {
        $category = $data['category'] ?? null;

        if (is_string($category)) {
            return Category::tryFrom($category);
        }

        /** @var \App\Modules\Core\Finance\Models\Team\Tag|null $tag */
        $tag = Tag::query()->whereKey($data['tag_id'] ?? null)->first();

        return $tag;
    }
}
