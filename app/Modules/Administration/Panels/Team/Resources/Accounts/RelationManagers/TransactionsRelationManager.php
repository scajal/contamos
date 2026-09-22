<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers;

use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Support\Helpers\Amount;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TransactionsRelationManager extends RelationManager
{
    protected static ?string $inverseRelationship = 'account';

    protected static string $relationship = 'transactions';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Transactions');
    }

    /**
     * Configure the relation manager's table.
     *
     * Movements are recorded through the finance actions that keep
     * the account balances in step, so this list only reads them.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('occurred_at', 'desc')
            ->columns($this->getColumns());
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
                    Tables\Columns\TextColumn::make('description')
                        ->weight(FontWeight::Medium)
                        ->placeholder('-')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('occurred_at')
                        ->dateTime(),
                ]),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->state(fn (Transaction $record): string => new Amount()->format((string) $record->amount, $record->currency)),
            ]),
        ];
    }
}
