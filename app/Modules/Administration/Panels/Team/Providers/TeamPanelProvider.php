<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Providers;

use App\Modules\Administration\Providers\PanelProvider;
use App\Modules\Core\Identity\Models\Team;
use Filament\Facades\Filament;
use Filament\Navigation;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class TeamPanelProvider extends PanelProvider
{
    /**
     * Configure the team administration panel.
     */
    #[\Override]
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->id('administration:team')
            ->colors(['primary' => Color::Lime])
            ->discoverClusters(in: app_path('Modules/Administration/Panels/Team/Clusters'), for: 'App\Modules\Administration\Panels\Team\Clusters')
            ->discoverLivewireComponents(in: app_path('Modules/Administration/Panels/Team/Livewire/Components'), for: 'App\Modules\Administration\Panels\Team\Livewire\Components')
            ->discoverPages(in: app_path('Modules/Administration/Panels/Team/Pages'), for: 'App\Modules\Administration\Panels\Team\Pages')
            ->discoverResources(in: app_path('Modules/Administration/Panels/Team/Resources'), for: 'App\Modules\Administration\Panels\Team\Resources')
            ->discoverWidgets(in: app_path('Modules/Administration/Panels/Team/Widgets'), for: 'App\Modules\Administration\Panels\Team\Widgets')
            ->navigationGroups([
                // Define the order of the navigation groups.
                Navigation\NavigationGroup::make(trans('Finance')),
                Navigation\NavigationGroup::make(trans('Team')),
            ])
            ->navigationItems([
                Navigation\NavigationItem::make(trans('Administration panel'))
                    ->icon(Heroicon::OutlinedArrowLeft)
                    ->url(fn (): string => Filament::getPanel('administration')->getResourceUrl(Filament::getTenant(), 'view')), // @phpstan-ignore-line argument.type Tenant is always set when executed.
            ])
            ->pages([Dashboard::class])
            ->path('administration/team')
            ->tenant(Team::class, slugAttribute: 'slug', ownershipRelationship: 'teams');
    }
}
