<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Tables;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class BudgetPeriodsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('from_month', 'desc')
            ->columns(self::getColumns());
    }

    /**
     * Get the table columns.
     *
     * @return array<int, \Filament\Tables\Columns\TextColumn>
     */
    protected static function getColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('target')
                ->label(trans('Target'))
                ->state(fn (BudgetPeriod $record): string => self::targetLabel($record)." ({$record->targetKind()->getLabel()})")
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('range')
                ->state(fn (BudgetPeriod $record): string => "{$record->from_month->format('m/Y')} - {$record->until_month?->format('m/Y')}")
                ->placeholder(trans('Ongoing')),
            Tables\Columns\TextColumn::make('status')
                ->state(fn (BudgetPeriod $record): string => $record->status(CarbonImmutable::now())->getLabel())
                ->color(fn (BudgetPeriod $record): array => $record->status(CarbonImmutable::now()) === BudgetPeriodStatus::Running ? Color::Green : Color::Gray)
                ->badge(),
        ];
    }

    /**
     * Name a period by what it follows, which is the category's
     * own label or the tag's name.
     */
    protected static function targetLabel(BudgetPeriod $record): string
    {
        $target = $record->target();

        return $target instanceof Category ? $target->getLabel() : (string) $target?->name;
    }
}
