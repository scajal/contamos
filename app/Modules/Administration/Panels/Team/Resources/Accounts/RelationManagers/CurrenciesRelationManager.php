<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Helpers\Amount;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CurrenciesRelationManager extends RelationManager
{
    protected static string $relationship = 'currencies';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Balances');
    }

    /**
     * Configure the relation manager's table.
     *
     * Balances are written by the finance actions that record
     * transactions, so they are shown here but never edited.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
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
                    Tables\Columns\TextColumn::make('code')
                        ->weight(FontWeight::Medium)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('name'),
                ]),
                Tables\Columns\TextColumn::make('pivot.balance')
                    ->label(trans('Balance'))
                    ->formatStateUsing(fn (string $state, Currency $record): string => new Amount()->format($state, $record)),
            ]),
        ];
    }
}
