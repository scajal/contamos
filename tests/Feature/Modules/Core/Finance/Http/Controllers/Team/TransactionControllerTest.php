<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Inertia\Support\Header;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $team = Team::factory()->create();

        $this->get(route('finance.teams.transactions.index', $team))
            ->assertRedirectToRoute('login');
    });

    it('forbids members of another team', function () {
        $user = User::factory()->create();
        $user->teams()->attach(Team::factory()->create());
        $team = Team::factory()->create();

        $this->actingAs($user)
            ->get(route('finance.teams.transactions.index', $team))
            ->assertForbidden();
    });

    it('returns only expense transactions when the type filter is expense', function () {
        [$user, $team] = memberWithTeam();
        $account = Account::factory()->for($team)->create();
        $currency = Currency::factory()->create();
        $expense = Transaction::factory()
            ->for($team)
            ->for($account)
            ->for($currency)
            ->for($user)
            ->create([
                'type' => TransactionType::Expense,
                'occurred_at' => '2026-01-15 12:00:00',
            ]);
        Transaction::factory()
            ->for($team)
            ->for($account)
            ->for($currency)
            ->for($user)
            ->create([
                'type' => TransactionType::Income,
                'occurred_at' => '2026-01-16 12:00:00',
            ]);

        $this->actingAs($user)
            ->withHeaders(transactionsIndexHeaders())
            ->get(route('finance.teams.transactions.index', ['team' => $team, 'filter' => ['type' => 'expense']]))
            ->assertOk()
            ->assertJsonPath('component', 'finance/teams/transactions/index')
            ->assertJsonCount(1, 'props.transactions.data')
            ->assertJsonPath('props.transactions.data.0.id', $expense->id)
            ->assertJsonPath('props.filters.type', 'expense');
    });

    it('returns only transactions in the date range when from and to are set', function () {
        [$user, $team] = memberWithTeam();
        $account = Account::factory()->for($team)->create();
        $currency = Currency::factory()->create();
        $inRange = Transaction::factory()
            ->for($team)
            ->for($account)
            ->for($currency)
            ->for($user)
            ->create([
                'occurred_at' => '2026-01-15 12:00:00',
            ]);
        Transaction::factory()
            ->for($team)
            ->for($account)
            ->for($currency)
            ->for($user)
            ->create([
                'occurred_at' => '2026-02-20 12:00:00',
            ]);

        $this->actingAs($user)
            ->withHeaders(transactionsIndexHeaders())
            ->get(route('finance.teams.transactions.index', [
                'team' => $team,
                'filter' => ['from' => '2026-01-01', 'to' => '2026-01-31'],
            ]))
            ->assertOk()
            ->assertJsonPath('component', 'finance/teams/transactions/index')
            ->assertJsonCount(1, 'props.transactions.data')
            ->assertJsonPath('props.transactions.data.0.id', $inRange->id)
            ->assertJsonPath('props.filters.from', '2026-01-01')
            ->assertJsonPath('props.filters.to', '2026-01-31');
    });

    it('rejects a type filter that is not a transaction type', function () {
        [$user, $team] = memberWithTeam();

        $this->actingAs($user)
            ->from(route('finance.teams.transactions.index', $team))
            ->get(route('finance.teams.transactions.index', ['team' => $team, 'filter' => ['type' => 'transfer']]))
            ->assertRedirectToRoute('finance.teams.transactions.index', ['team' => $team])
            ->assertSessionHasErrors('filter.type');
    });
});

/**
 * @return array<string, string>
 */
function transactionsIndexHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        Header::INERTIA => 'true',
        Header::VERSION => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
        Header::PARTIAL_COMPONENT => 'finance/teams/transactions/index',
        Header::PARTIAL_ONLY => 'transactions,filters',
    ];
}
