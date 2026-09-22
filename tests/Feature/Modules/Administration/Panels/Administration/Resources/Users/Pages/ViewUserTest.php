<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Users\Pages\ViewUser;
use App\Modules\Core\Identity\Models\User;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Users\Pages\ViewUser.
 */
it('redirects guests to login', function (): void {
    $user = User::factory()->create();

    $this->get(route('filament.administration.resources.users.view', ['record' => $user->getKey()]))
        ->assertRedirectToRoute('login');
});

it('renders the user', function (): void {
    actingOperator();
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    Livewire::test(ViewUser::class, ['record' => $user->getKey()])
        ->assertOk();
});
