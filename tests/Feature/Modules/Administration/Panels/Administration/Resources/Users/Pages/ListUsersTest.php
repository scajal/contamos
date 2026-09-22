<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Users\Pages\ListUsers;
use App\Modules\Core\Identity\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Users\Pages\ListUsers.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.users.index'))
        ->assertRedirectToRoute('login');
});

it('renders the users index for an operator', function (): void {
    actingOperator();

    $this->get(route('filament.administration.resources.users.index'))
        ->assertOk();
});

it('lists users', function (): void {
    actingOperator();
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    Livewire::test(ListUsers::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$user]);
});

it('searches users by email', function (): void {
    actingOperator();
    $matching = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $other = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    Livewire::test(ListUsers::class)
        ->loadTable()
        ->searchTable('ada@example.com')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('activates the user', function (): void {
    actingOperator();
    $user = User::factory()->inactive()->create();

    Livewire::test(ListUsers::class)
        ->loadTable()
        ->callAction(TestAction::make('activate')->table($user))
        ->assertNotified(trans('Saved'));

    expect($user->fresh()?->is_active)->toBeTrue();
});

it('deactivates the user', function (): void {
    actingOperator();
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->loadTable()
        ->callAction(TestAction::make('deactivate')->table($user))
        ->assertNotified(trans('Saved'));

    expect($user->fresh()?->is_active)->toBeFalse();
});
