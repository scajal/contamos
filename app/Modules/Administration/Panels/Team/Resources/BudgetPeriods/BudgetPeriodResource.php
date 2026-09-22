<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BudgetPeriodResource extends Resource
{
    protected static ?string $model = BudgetPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'from_month';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Get the resource's label.
     */
    #[\Override]
    public static function getModelLabel(): string
    {
        return trans('budget period');
    }

    /**
     * Get the resource's plural label.
     */
    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return trans('budget periods');
    }

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Finance');
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\BudgetPeriodsTable::configure($table);
    }

    /**
     * Get the resource's pages.
     *
     * @return array<\Filament\Resources\Pages\PageRegistration>
     */
    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBudgetPeriods::route('/'),
        ];
    }
}
