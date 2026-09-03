<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Inertia\Support\Header;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $team = Team::factory()->create();

        $this->get(route('finance.teams.accounts.index', $team))
            ->assertRedirectToRoute('login');
    });

    it('forbids members of another team', function () {
        $user = User::factory()->create();
        $user->teams()->attach(Team::factory()->create());
        $team = Team::factory()->create();

        $this->actingAs($user)
            ->get(route('finance.teams.accounts.index', $team))
            ->assertForbidden();
    });

    it('returns only active accounts when the is_active filter is true', function () {
        [$user, $team] = memberWithTeam();
        $active = Account::factory()->for($team)->create([
            'is_active' => true,
            'name' => 'Checking',
            'type' => AccountType::Bank,
        ]);
        Account::factory()->for($team)->create([
            'is_active' => false,
            'name' => 'Cash',
            'type' => AccountType::Cash,
        ]);

        $this->actingAs($user)
            ->withHeaders(accountsIndexHeaders())
            ->get(route('finance.teams.accounts.index', ['team' => $team, 'filter' => ['is_active' => 'true']]))
            ->assertOk()
            ->assertJsonPath('component', 'finance/teams/accounts/index')
            ->assertJsonCount(1, 'props.accounts.data')
            ->assertJsonPath('props.accounts.data.0.id', $active->id)
            ->assertJsonPath('props.filters.is_active', 'true');
    });

    it('returns only inactive accounts when the is_active filter is false', function () {
        [$user, $team] = memberWithTeam();
        Account::factory()->for($team)->create([
            'is_active' => true,
            'name' => 'Checking',
            'type' => AccountType::Bank,
        ]);
        $inactive = Account::factory()->for($team)->create([
            'is_active' => false,
            'name' => 'Cash',
            'type' => AccountType::Cash,
        ]);

        $this->actingAs($user)
            ->withHeaders(accountsIndexHeaders())
            ->get(route('finance.teams.accounts.index', ['team' => $team, 'filter' => ['is_active' => 'false']]))
            ->assertOk()
            ->assertJsonPath('component', 'finance/teams/accounts/index')
            ->assertJsonCount(1, 'props.accounts.data')
            ->assertJsonPath('props.accounts.data.0.id', $inactive->id)
            ->assertJsonPath('props.filters.is_active', 'false');
    });

    it('rejects an is_active filter that is not true or false', function () {
        [$user, $team] = memberWithTeam();

        $this->actingAs($user)
            ->from(route('finance.teams.accounts.index', $team))
            ->get(route('finance.teams.accounts.index', ['team' => $team, 'filter' => ['is_active' => 'yes']]))
            ->assertRedirectToRoute('finance.teams.accounts.index', ['team' => $team])
            ->assertSessionHasErrors('filter.is_active');
    });
});

/**
 * @return array{0: User, 1: Team}
 */
function memberWithTeam(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team);
    $user->switchTeam($team);

    return [$user, $team];
}

/**
 * @return array<string, string>
 */
function accountsIndexHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        Header::INERTIA => 'true',
        Header::VERSION => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
        Header::PARTIAL_COMPONENT => 'finance/teams/accounts/index',
        Header::PARTIAL_ONLY => 'accounts,filters',
    ];
}
