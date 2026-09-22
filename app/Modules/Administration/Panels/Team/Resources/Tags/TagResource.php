<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Finance\Models\Team\Tag;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\TagForm::configure($schema);
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
     * Get the resource's relations.
     *
     * @return array<string>
     */
    #[\Override]
    public static function getRelations(): array
    {
        return [
            RelationManagers\BudgetPeriodsRelationManager::class,
        ];
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\TagsTable::configure($table);
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
            'view' => Pages\ViewTag::route('/{record}'),
        ];
    }
}
