<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Concerns\NormalizesInputs.
 */

/**
 * A tool that reports how the concern normalized its inputs,
 * standing in for the real tools.
 */
#[Name('probe_inputs')]
class ProbeInputsTool extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request): ResponseFactory {
            $occurredAt = $this->occurredAt($request->get('occurred_at', '2026-08-14'), $request->get('timezone'));

            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = $this->findOrFail($team->accounts(), $request->get('account'), 'account');

            $currency = $this->accountCurrency($account, $request->get('currency'));

            return Response::structured([
                'occurred_at' => $occurredAt,
                'currency' => $currency->code,
                'tags' => $this->tagIds($team, $request->get('tags')),
                'quote' => $request->get('with_quote') === true ? $this->quote($currency, $occurredAt, $request->get('quote')) : null,
            ]);
        });
    }
}

/**
 * The Contamos server with the probe registered next to the real
 * tools.
 */
class ProbeInputsServer extends ContamosServer
{
    protected function boot(): void
    {
        parent::boot();

        $this->tools[] = ProbeInputsTool::class;
    }
}

/**
 * Create a pro member with an account holding the given
 * currencies.
 *
 * @param  array<int, string>  $codes
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team, 2: \App\Modules\Core\Finance\Models\Team\Account}
 */
function memberWithAccountIn(array $codes): array
{
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->for($team)->create(['name' => 'Santander']);

    foreach ($codes as $code) {
        $account->currencies()->attach(Currency::factory()->fiat()->create(['code' => $code]), ['balance' => '0']);
    }

    return [$user, $team, $account];
}

it('stores a date alone at noon in the timezone, as UTC', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => '2026-08-14', 'timezone' => 'America/Montevideo'])
        ->assertOk()
        ->assertSee('"occurred_at":"2026-08-14T15:00:00+00:00"');
});

it('stores a date alone at noon UTC without a timezone', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => '2026-08-14'])
        ->assertOk()
        ->assertSee('"occurred_at":"2026-08-14T12:00:00+00:00"');
});

it('converts a local time in the timezone to UTC', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => '2026-08-14 22:30', 'timezone' => 'America/Montevideo'])
        ->assertOk()
        ->assertSee('"occurred_at":"2026-08-15T01:30:00+00:00"');
});

it('refuses a time without a timezone', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => '2026-08-14 22:30'])
        ->assertHasErrors(['A time was given without a timezone.']);
});

it('infers the currency of an account holding one', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id])
        ->assertOk()
        ->assertSee('"currency":"UYU"');
});

it('asks for the currency of an account holding several', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU', 'USD']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id])
        ->assertHasErrors(['The account "Santander" holds several currencies (USD, UYU). Ask the user which one and pass `currency`.']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'currency' => 'usd'])
        ->assertOk()
        ->assertSee('"currency":"USD"');
});

it('refuses a currency the account does not hold', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'currency' => 'EUR'])
        ->assertHasErrors(['The account "Santander" does not hold EUR. Its currencies are: UYU.']);
});

it('refuses an account of another team', function (): void {
    [$user, $team] = memberWithAccountIn(['UYU']);
    $foreign = Account::factory()->create();

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $foreign->id])
        ->assertHasErrors(['Account not found.']);
});

it('resolves tags by id and by name, ignoring case', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);
    $vacation = Tag::factory()->active()->for($team)->create(['name' => 'Vacation']);
    $food = Tag::factory()->active()->for($team)->create(['name' => 'Food']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'tags' => ['vacation', $food->id]])
        ->assertOk()
        ->assertSee('"tags":["'.$vacation->id.'","'.$food->id.'"]');
});

it('refuses an unknown or inactive tag by name', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);
    Tag::factory()->inactive()->for($team)->create(['name' => 'Old']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'tags' => ['Nope']])
        ->assertHasErrors(['Unknown tag "Nope".']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'tags' => ['old']])
        ->assertHasErrors(['The tag "Old" is inactive.']);

    expect(Tag::query()->count())->toBe(1);
});

it('uses the rate recorded for the date when no quote is given', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);
    $account->currencies()->sole()->quotes()->create(['observed_at' => '2026-08-01 00:00:00', 'rate' => '40.25', 'source' => 'manual']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'with_quote' => true])
        ->assertOk()
        ->assertSee('"quote":"40.25');
});

it('refuses to guess a missing exchange rate', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'with_quote' => true])
        ->assertHasErrors(['No exchange rate for UYU on 2026-08-14; pass `quote` explicitly.']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'with_quote' => true, 'quote' => '41'])
        ->assertOk()
        ->assertSee('"quote":"41"');
});

it('refuses a timezone that is not an IANA identifier', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => '2026-08-14', 'timezone' => 'Montevideo'])
        ->assertHasErrors(['The timezone must be an IANA identifier, such as "America/Montevideo".']);
});

it('refuses a date in another format', function (mixed $occurredAt): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => $occurredAt])
        ->assertHasErrors(['The date must be "YYYY-MM-DD" or "YYYY-MM-DD HH:MM".']);
})->with([
    'words' => 'yesterday',
    'day first' => '14/08/2026',
    'a timestamp' => 1786708800,
]);

it('refuses a date that is not on the calendar', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'occurred_at' => '2026-02-30'])
        ->assertHasErrors(['The date is not a valid calendar date.']);
});

it('refuses a currency that is not a code', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'currency' => 858])
        ->assertHasErrors(['The account "Santander" does not hold that currency. Its currencies are: UYU.']);
});

it('refuses tags that are not a list', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);
    Tag::factory()->active()->for($team)->create(['name' => 'Food']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'tags' => 'Food'])
        ->assertHasErrors(['The tags must be a list of tag ids or names.']);
});

it('refuses a tag reference that is neither an id nor a name', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'tags' => [42]])
        ->assertHasErrors(['Unknown tag "42".']);
});

it('takes a numeric quote as its decimal string', function (): void {
    [$user, $team, $account] = memberWithAccountIn(['UYU']);

    ProbeInputsServer::actingAs($user)
        ->tool(ProbeInputsTool::class, ['team' => $team->slug, 'account' => $account->id, 'with_quote' => true, 'quote' => 40.5])
        ->assertOk()
        ->assertSee('"quote":"40.5"');
});
