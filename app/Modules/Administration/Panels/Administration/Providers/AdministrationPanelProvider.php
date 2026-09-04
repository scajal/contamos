<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Providers;

use App\Modules\Administration\Providers\PanelProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;

class AdministrationPanelProvider extends PanelProvider
{
    /**
     * Configure the administration panel.
     */
    #[\Override]
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->id('administration')
            ->colors(['primary' => Color::Green])
            ->discoverClusters(in: app_path('Modules/Administration/Panels/Administration/Clusters'), for: 'App\Modules\Administration\Panels\Administration\Clusters')
            ->discoverLivewireComponents(in: app_path('Modules/Administration/Panels/Administration/Livewire/Components'), for: 'App\Modules\Administration\Panels\Administration\Livewire\Components')
            ->discoverPages(in: app_path('Modules/Administration/Panels/Administration/Pages'), for: 'App\Modules\Administration\Panels\Administration\Pages')
            ->discoverResources(in: app_path('Modules/Administration/Panels/Administration/Resources'), for: 'App\Modules\Administration\Panels\Administration\Resources')
            ->discoverWidgets(in: app_path('Modules/Administration/Panels/Administration/Widgets'), for: 'App\Modules\Administration\Panels\Administration\Widgets')
            ->pages([\Filament\Pages\Dashboard::class])
            ->path('administration');
    }
}
