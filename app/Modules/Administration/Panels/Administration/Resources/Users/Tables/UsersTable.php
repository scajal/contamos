<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Users\Tables;

use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class UsersTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(isIndividual: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(isIndividual: true),
            ])
            ->recordActions([
                Actions\ActionGroup::make([])
                    ->actions([
                        Actions\ViewAction::make(),
                        Actions\EditAction::make(),
                    ]),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
