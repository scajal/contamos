<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Tables;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
     * @return array<int, \Filament\Tables\Columns\Layout\Split>
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

    /**
     * Narrow the table to the periods following one kind of
     * target, which is the column the other kind leaves
     * empty.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected static function whereKind(Builder $query, mixed $kind): Builder
    {
        return match ($kind) {
            BudgetTargetKind::Category->value => $query->whereNotNull('category'),
            BudgetTargetKind::Tag->value => $query->whereNull('category'),
            default => $query,
        };
    }

    /**
     * Narrow the table to the periods sitting where the status
     * says relative to the current month. A stretch whose
     * months are over reads as ended when another one
     * took over after it or when it only ever covered
     * a single month, and as paused otherwise.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected static function whereStatus(Builder $query, mixed $status): Builder
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $first = $month->toDateString();

        return match ($status) {
            BudgetPeriodStatus::Running->value => $query->covering($month),
            BudgetPeriodStatus::Scheduled->value => $query->whereDate('from_month', '>', $first),
            BudgetPeriodStatus::Paused->value => self::whereOver($query, $first)
                ->where(fn (Builder $query): Builder => self::whereSucceeded($query, false)),
            BudgetPeriodStatus::Ended->value => self::whereOver($query, $first)
                ->where(fn (Builder $query): Builder => self::whereSucceeded($query, true)),
            default => $query,
        };
    }

    /**
     * Narrow the query to the stretches whose months are
     * already behind the current one.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected static function whereOver(Builder $query, string $first): Builder
    {
        return $query->whereNotNull('until_month')
            ->whereDate('until_month', '<', $first);
    }

    /**
     * Narrow the query to the stretches another one took over
     * from, or to the ones nothing followed. A single-month
     * objective counts as taken over, since it ran its
     * course rather than being stopped.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected static function whereSucceeded(Builder $query, bool $isSucceeded): Builder
    {
        $period = new BudgetPeriod;
        $successor = fn (Builder $query): Builder => $query->whereColumn('successors.team_id', $period->qualifyColumn('team_id'))
            ->whereColumn('successors.currency_id', $period->qualifyColumn('currency_id'))
            ->whereColumn('successors.from_month', '>', $period->qualifyColumn('from_month'))
            ->where(fn (Builder $query): Builder => $query
                ->whereColumn('successors.tag_id', $period->qualifyColumn('tag_id'))
                ->orWhereColumn('successors.category', $period->qualifyColumn('category')));

        $objective = fn (Builder $query): Builder => $query->whereColumn('until_month', 'from_month');

        // A period that ended reads so because something else
        // took over or because it was a one-month objective.
        if ($isSucceeded) {
            return $query->where($objective)
                ->orWhereHas('team.budgets', fn (Builder $query): Builder => $successor($query->from($period->getTable().' as successors')));
        }

        return $query->whereNot($objective)
            ->whereDoesntHave('team.budgets', fn (Builder $query): Builder => $successor($query->from($period->getTable().' as successors')));
    }
}
