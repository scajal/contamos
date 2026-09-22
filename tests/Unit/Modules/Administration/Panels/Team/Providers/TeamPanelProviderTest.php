<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use Filament\Facades\Filament;

/**
 * Unit tests for \App\Modules\Administration\Panels\Team\Providers\TeamPanelProvider.
 */
it('registers the team panel with tenancy on the team slug', function (): void {
    $panel = Filament::getPanel('team');

    expect($panel->getId())->toBe('team')
        ->and($panel->getPath())->toBe('administration/team')
        ->and($panel->hasTenancy())->toBeTrue()
        ->and($panel->getTenantModel())->toBe(Team::class)
        ->and($panel->getTenantSlugAttribute())->toBe('slug');
});

it('keeps the administration panel as the default one', function (): void {
    expect(Filament::getDefaultPanel()->getId())->toBe('administration');
});
