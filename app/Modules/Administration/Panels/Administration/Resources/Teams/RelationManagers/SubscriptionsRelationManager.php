<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Subscriptions');
    }

    /**
     * Configure the relation manager's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('paddle_id')
            ->defaultSort('created_at', 'desc')
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
            Tables\Columns\TextColumn::make('paddle_id')
                ->searchable(isIndividual: true),
            Tables\Columns\TextColumn::make('type')
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('status')
                ->badge(),
        ];
    }
}
