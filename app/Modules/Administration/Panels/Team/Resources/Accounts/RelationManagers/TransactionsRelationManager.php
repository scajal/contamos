<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
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
     * @return array<int, \Filament\Tables\Columns\TextColumn>
     */
    protected function getColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('occurred_at')
                ->dateTime(),
            Tables\Columns\TextColumn::make('type')
                ->badge(),
        ];
    }
}
