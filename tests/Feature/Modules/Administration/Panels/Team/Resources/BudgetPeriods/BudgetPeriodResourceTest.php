<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\BudgetPeriodResource;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\BudgetPeriodResource.
 */
it('names budget periods in the current locale', function (): void {
    app()->setLocale('es');

    expect(BudgetPeriodResource::getModelLabel())->toBe('presupuesto')
        ->and(BudgetPeriodResource::getPluralModelLabel())->toBe('presupuestos');
});
