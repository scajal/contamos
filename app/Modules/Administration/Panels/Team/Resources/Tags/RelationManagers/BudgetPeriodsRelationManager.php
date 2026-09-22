<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags\RelationManagers;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod;
use App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod;
use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\Amount;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BudgetPeriodsRelationManager extends RelationManager
{
    protected static ?string $inverseRelationship = 'tag';

    protected static string $relationship = 'budgetPeriods';

    protected CreateBudgetPeriod $createBudgetPeriod;

    protected DeleteBudgetPeriod $deleteBudgetPeriod;

    protected UpdateBudgetPeriod $updateBudgetPeriod;

    /**
     * Resolve the actions that write budget periods, which guard the
     * overlap rules a plain Eloquent write would skip.
     */
    public function boot(
        CreateBudgetPeriod $createBudgetPeriod,
        DeleteBudgetPeriod $deleteBudgetPeriod,
        UpdateBudgetPeriod $updateBudgetPeriod,
    ): void {
        $this->createBudgetPeriod = $createBudgetPeriod;
        $this->deleteBudgetPeriod = $deleteBudgetPeriod;
        $this->updateBudgetPeriod = $updateBudgetPeriod;
    }

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Budgets');
    }

    /**
     * Configure the relation manager's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('from_month')
            ->defaultSort('from_month', 'desc')
            ->columns($this->getColumns())
            ->headerActions($this->getHeaderActions())
            ->recordActions($this->getRecordActions());
    }

    /**
     * Get the columns for the relation manager's table.
     *
     * @return array<\Filament\Tables\Columns\Layout\Split>
     */
    protected function getColumns(): array
    {
        return [
            Tables\Columns\Layout\Split::make([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('amount')
                        ->formatStateUsing(fn (BudgetPeriod $record): string => new Amount()->format((string) $record->amount, $record->currency))
                        ->weight(FontWeight::Medium),
                    Tables\Columns\TextColumn::make('currency.code'),
                ]),
                Tables\Columns\TextColumn::make('from_month')
                    ->date('m/Y'),
                Tables\Columns\TextColumn::make('until_month')
                    ->placeholder(trans('Ongoing'))
                    ->date('m/Y'),
            ]),
        ];
    }

    /**
     * Get the schema a budget period is written with.
     *
     * @return array<int, \Filament\Forms\Components\Field>
     */
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('amount')
                ->placeholder(trans('Enter an amount'))
                ->prefixIcon(LucideIcon::DollarSign)
                ->inputMode('decimal')
                ->required()
                ->numeric()
                ->gt('0'),
            Forms\Components\Select::make('currency_id')
                ->placeholder(trans('Select a currency'))
                ->options(fn (): array => Currency::query()->isActive()->orderBy('code')->pluck('code', 'id')->all())
                ->searchable()
                ->required()
                ->preload(),
            Forms\Components\DatePicker::make('from_month')
                ->helperText(trans('The first month the budget applies to.'))
                ->prefixIcon(LucideIcon::Calendar)
                ->displayFormat('m/Y')
                ->required(),
            Forms\Components\DatePicker::make('until_month')
                ->helperText(trans('Leave it empty to keep the budget running.'))
                ->prefixIcon(LucideIcon::Calendar)
                ->displayFormat('m/Y'),
        ];
    }

    /**
     * Get the header actions for the relation manager's table.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(fn (array $data): BudgetPeriod => $this->createBudgetPeriod
                    ->for($this->getTeam())
                    ->targeting($this->getTag())
                    ->by($this->getOperator())
                    ->using($this->attributes($data)))
                ->modalWidth(Width::Medium)
                ->schema($this->getFormSchema())
                ->createAnother(false)
                ->slideOver(),
        ];
    }

    /**
     * Get the row actions for the relation manager's table.
     *
     * @return array<\Filament\Actions\ActionGroup>
     */
    protected function getRecordActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make()
                    ->using(fn (BudgetPeriod $record, array $data): BudgetPeriod => $this->updateBudgetPeriod
                        ->for($this->getTeam())
                        ->by($this->getOperator())
                        ->using($record, $this->attributes($data)))
                    ->modalWidth(Width::Medium)
                    ->schema($this->getFormSchema())
                    ->slideOver(),
                Actions\DeleteAction::make()
                    ->using(function (BudgetPeriod $record): bool {
                        $this->deleteBudgetPeriod
                            ->for($this->getTeam())
                            ->using($record);

                        return true;
                    }),
            ]),
        ];
    }

    /**
     * Get the attributes the budget period is written from.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'amount' => $data['amount'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
            'from_month' => $data['from_month'] ?? null,
            'until_month' => $data['until_month'] ?? null,
        ];
    }

    /**
     * Get the operator writing the budget period.
     */
    protected function getOperator(): User
    {
        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    /**
     * Get the tag the budget periods belong to.
     */
    protected function getTag(): Tag
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Tag $tag */
        $tag = $this->getOwnerRecord();

        return $tag;
    }

    /**
     * Get the team the budget periods are written for, which
     * is the one the tag belongs to.
     */
    protected function getTeam(): Team
    {
        return $this->getTag()->team;
    }
}
