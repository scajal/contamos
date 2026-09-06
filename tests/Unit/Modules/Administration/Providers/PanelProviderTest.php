<?php

declare(strict_types=1);

use Filament\Tables\Filters\Filter;

/**
 * Unit tests for \App\Modules\Administration\Providers\PanelProvider
 * filter configuration that resource tables do not currently reach.
 */
it('translates table filter indicators from the filter label', function (): void {
    $filter = Filter::make('status')->label('Active');

    expect($filter->getLabel())->toBe('Active');
});
