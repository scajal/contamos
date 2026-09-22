<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\RecurringRule\Status;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Enums\RecurringRule\Status.
 */
it('translates the recurring rule status label', function (Status $status, string $label): void {
    App::setLocale('en');

    expect($status->getLabel())->toBe($label);
})->with([
    'active' => [Status::Active, 'Active'],
    'failed' => [Status::Failed, 'Failed'],
    'paused' => [Status::Paused, 'Paused'],
]);
