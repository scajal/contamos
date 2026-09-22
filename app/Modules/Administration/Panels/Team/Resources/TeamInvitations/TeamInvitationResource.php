<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\TeamInvitations;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TeamInvitationResource extends Resource
{
    protected static ?string $model = TeamInvitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Get the resource model's plural label.
     */
    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return trans('Invitations');
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
        return Tables\TeamInvitationsTable::configure($table);
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
            'index' => Pages\ListTeamInvitations::route('/'),
        ];
    }
}
