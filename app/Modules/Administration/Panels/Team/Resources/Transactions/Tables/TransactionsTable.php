<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Tables;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Support\Helpers\Amount;
use Filament\Actions;
use Filament\Forms;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions(self::getRecordActions());
    }

    /**
     * Get the table columns.
     *
     * @return array<int, \Filament\Tables\Columns\Layout\Split>
     */
    protected static function getColumns(): array
    {
        return [
            Tables\Columns\Layout\Split::make([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('description')
                        ->weight(FontWeight::Medium)
                        ->placeholder('-')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('account.name')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('category')
                    ->placeholder('-')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn (Transaction $record): string => new Amount()->format((string) $record->amount, $record->currency)),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->dateTime(),
            ]),
        ];
    }

    /**
     * Get the table filters.
     *
     * @return array<int, \Filament\Tables\Filters\BaseFilter>
     */
    protected static function getFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('type')
                ->options(TransactionType::class),
            Tables\Filters\SelectFilter::make('category')
                ->options(Category::class),
            Tables\Filters\SelectFilter::make('account')
                ->relationship('account', 'name')
                ->searchable()
                ->preload(),
            Tables\Filters\SelectFilter::make('tags')
                ->relationship('tags', 'name')
                ->multiple()
                ->preload(),
            Tables\Filters\Filter::make('occurred_at')
                ->schema([
                    Forms\Components\DatePicker::make('from'),
                    Forms\Components\DatePicker::make('until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $from = $data['from'] ?? null;
                    $until = $data['until'] ?? null;

                    if (is_string($from)) {
                        $query->whereDate('occurred_at', '>=', $from);
                    }

                    if (is_string($until)) {
                        $query->whereDate('occurred_at', '<=', $until);
                    }

                    return $query;
                }),
        ];
    }

    /**
     * Get the row actions for the table.
     *
     * @return array<int, \Filament\Actions\ActionGroup>
     */
    protected static function getRecordActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn (Transaction $record): bool => ! $record->transfer()->exists()),
            ]),
        ];
    }
}
