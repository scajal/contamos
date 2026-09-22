<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Memberships;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Identity\Models\Membership;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\MembershipForm::configure($schema);
    }

    /**
     * Get the resource model's plural label.
     */
    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return trans('Members');
    }

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Team');
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\MembershipsTable::configure($table);
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
            'index' => Pages\ListMemberships::route('/'),
            'edit' => Pages\EditMembership::route('/{record}/edit'),
        ];
    }
}
