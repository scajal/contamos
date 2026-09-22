<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Activities;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Identity\Models\Team\Activity;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Team');
    }

    /**
     * Configure the resource's infolist.
     */
    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return Schemas\ActivityInfolist::configure($schema);
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\ActivitiesTable::configure($table);
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
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }
}
