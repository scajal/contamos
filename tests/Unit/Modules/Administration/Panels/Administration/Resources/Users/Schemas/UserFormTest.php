<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Users\Schemas\UserForm;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

/**
 * Unit tests for \App\Modules\Administration\Panels\Administration\Resources\Users\Schemas\UserForm.
 */
it('configures a single-column schema when creating a user', function (): void {
    $schema = UserForm::configure(Schema::make()->operation(Operation::Create->value));

    expect($schema->getOperation())->toBe(Operation::Create->value);
});

it('configures a multi-column schema when editing a user', function (): void {
    $schema = UserForm::configure(Schema::make()->operation(Operation::Edit->value));

    expect($schema->getOperation())->toBe(Operation::Edit->value);
});
