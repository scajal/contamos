<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Users;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Identity\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'email';

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\UserForm::configure($schema);
    }

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Application');
    }

    /**
     * Get the resource's relations.
     *
     * @return array<string>
     */
    #[\Override]
    public static function getRelations(): array
    {
        return [
            RelationManagers\TeamsRelationManager::class,
        ];
    }

    /**
     * Configure the resource's infolist.
     */
    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return Schemas\UserInfolist::configure($schema);
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\UsersTable::configure($table);
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
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
