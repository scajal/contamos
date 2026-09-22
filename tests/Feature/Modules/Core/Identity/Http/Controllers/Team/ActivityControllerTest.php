<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\Team\ActivityController.
 */

/**
 * Record an entry of a \App\Modules\Core\Identity\Models\Team activity
 * log without going through the write path that would normally
 * produce it.
 *
 * @param  array<string, mixed>  $attributes
 */
function recordActivity(Team $team, array $attributes = []): Activity
{
    /** @var \App\Modules\Core\Identity\Models\Team\Activity $activity */
    $activity = Activity::query()->create([
        'log_name' => 'default',
        'description' => 'updated',
        'event' => 'updated',
        'team_id' => $team->getKey(),
        'attribute_changes' => ['old' => [], 'attributes' => []],
        ...$attributes,
    ]);

    return $activity;
}

/**
 * Visit the activity page of a \App\Modules\Core\Identity\Models\Team.
 *
 * @param  array<string, mixed>  $query
 */
function visitActivity(Team $team, array $query = []): Illuminate\Testing\TestResponse
{
    return test()->get(route('identity.teams.activity.index', ['team' => $team->getRouteKey(), ...$query]));
}

/**
 * Open the detail dialog of one entry of a
 * \App\Modules\Core\Identity\Models\Team activity log.
 */
function openActivity(Team $team, Activity $activity): Illuminate\Testing\TestResponse
{
    return test()
        ->withHeaders(inertiaModalHeaders())
        ->get(route('identity.teams.activity.show', ['team' => $team->getRouteKey(), 'activity' => $activity->getKey()]));
}

describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        visitActivity($team)->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        visitActivity($other)->assertForbidden();
    });

    it('forbids a member holding the member role on a pro team', function (): void {
        [$user, $team] = actingMember(Role::Member);
        subscribeToPro($team);

        visitActivity($team)->assertForbidden();
    });

    it('redirects the owner and an admin of a free team to the upgrade modal', function (Role $role): void {
        [$user, $team] = actingMember($role);

        visitActivity($team)->assertRedirectToRoute('billing.teams.subscriptions.upgrade', [
            'team' => $team->getRouteKey(),
            'feature' => Feature::ViewActivity->value,
        ]);
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
    ]);

    it('renders the timeline for the owner and an admin of a pro team', function (Role $role): void {
        [$user, $team] = actingMember($role);
        subscribeToPro($team);
        clearActivities();
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);
        recordActivity(Team::factory()->create(), ['event' => 'created']);

        visitActivity($team)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/activity/index')
                ->where('filters', [])
                ->has('members')
                ->where('subjects', ActivitySubject::options())
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('activities.data', 1)
                    ->where('activities.data.0.event', 'created')
                    ->where('activities.data.0.subject_type', ActivitySubject::Tag->value)
                    ->where('activities.data.0.subject_id', $tag->getKey())
                    ->where('activities.data.0.subject_label', 'Comida')
                    ->where('activities.data.0.causer.kind', 'member')
                    ->where('activities.data.0.causer.name', $user->name)));
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
    ]);

    it('lists the newest entry first', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        clearActivities();
        recordActivity($team, ['event' => 'older', 'created_at' => CarbonImmutable::now()->subDay()]);
        recordActivity($team, ['event' => 'newer', 'created_at' => CarbonImmutable::now()]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.event', 'newer')
                    ->where('activities.data.1.event', 'older')));
    });

    it('filters the timeline by record type, member and date range', function (array $query, string $kept): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $other = memberOf($team, Role::Admin);
        clearActivities();
        recordActivity($team, [
            'event' => 'kept',
            'causer_type' => User::class,
            'causer_id' => $user->getKey(),
            'subject_type' => Tag::class,
            'created_at' => CarbonImmutable::parse('2026-06-15 12:00:00'),
        ]);
        recordActivity($team, [
            'event' => 'dropped',
            'causer_type' => User::class,
            'causer_id' => $other->getKey(),
            'subject_type' => Account::class,
            'created_at' => CarbonImmutable::parse('2026-01-05 12:00:00'),
        ]);

        visitActivity($team, ['filter' => $query])
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters', $query)
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('activities.data', 1)
                    ->where('activities.data.0.event', $kept)));
    })->with(fn (): array => [
        'subject' => [['subject' => 'tag'], 'kept'],
        'from' => [['from' => '2026-06-01'], 'kept'],
        'to' => [['to' => '2026-02-01'], 'dropped'],
    ]);

    it('filters the timeline by the member who acted', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $other = memberOf($team, Role::Admin);
        clearActivities();
        recordActivity($team, ['event' => 'mine', 'causer_type' => User::class, 'causer_id' => $user->getKey()]);
        recordActivity($team, ['event' => 'theirs', 'causer_type' => User::class, 'causer_id' => $other->getKey()]);

        visitActivity($team, ['filter' => ['causer' => $user->getKey()]])
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->has('activities.data', 1)
                    ->where('activities.data.0.event', 'mine')));
    });

    it('rejects a filter value the timeline cannot answer', function (array $query, string $invalid): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);

        visitActivity($team, ['filter' => $query])->assertInvalid([$invalid]);
    })->with([
        'unknown subject' => [['subject' => 'invoice'], 'filter.subject'],
        'malformed date' => [['from' => '15-06-2026'], 'filter.from'],
        'to before from' => [['from' => '2026-06-15', 'to' => '2026-06-01'], 'filter.to'],
    ]);

    it('rejects a causer who does not belong to the team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $stranger = User::factory()->create();

        visitActivity($team, ['filter' => ['causer' => $stranger->getKey()]])->assertInvalid(['filter.causer']);
    });

    it('names who an entry came from', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $support = User::factory()->create(['email' => 'operator@example.com']);
        $ghost = User::factory()->create();
        clearActivities();
        recordActivity($team, ['event' => 'system', 'created_at' => CarbonImmutable::now()->subDays(4)]);
        recordActivity($team, ['event' => 'former', 'causer_type' => User::class, 'causer_id' => $ghost->getKey(), 'created_at' => CarbonImmutable::now()->subDays(3)]);
        recordActivity($team, ['event' => 'support', 'causer_type' => User::class, 'causer_id' => $support->getKey(), 'created_at' => CarbonImmutable::now()->subDays(2)]);
        recordActivity($team, ['event' => 'member', 'causer_type' => User::class, 'causer_id' => $user->getKey(), 'created_at' => CarbonImmutable::now()->subDay()]);
        $ghost->delete();

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.causer', ['kind' => 'member', 'name' => $user->name])
                    ->where('activities.data.1.causer', ['kind' => 'support', 'name' => null])
                    ->where('activities.data.2.causer', ['kind' => 'former', 'name' => null])
                    ->where('activities.data.3.causer', ['kind' => 'system', 'name' => null])));
    });

    it('names a deleted transaction by the values it was deleted with', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $currency = Currency::factory()->active()->fiat()->create(['code' => 'UYU']);
        clearActivities();
        recordActivity($team, [
            'event' => 'deleted',
            'subject_type' => Transaction::class,
            'subject_id' => 'gone',
            'attribute_changes' => ['old' => [
                'amount' => '25.500000000000000000',
                'currency_id' => $currency->getKey(),
                'description' => null,
            ]],
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', '25.5 UYU')));
    });

    it('names a budget by the tag it belongs to', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);
        $period = BudgetPeriod::factory()->for($tag)->create();
        clearActivities();
        recordActivity($team, [
            'event' => 'created',
            'subject_type' => BudgetPeriod::class,
            'subject_id' => $period->getKey(),
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', 'Comida')));
    });

    it('names a deleted budget by the amount it carried', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $currency = Currency::factory()->active()->fiat()->create(['code' => 'UYU']);
        clearActivities();
        recordActivity($team, [
            'event' => 'deleted',
            'subject_type' => BudgetPeriod::class,
            'subject_id' => 'gone',
            'attribute_changes' => ['old' => [
                'amount' => '50.000000000000000000',
                'currency_id' => $currency->getKey(),
            ]],
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', '50 UYU')));
    });

    it('names a team by its own name', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $team->update(['name' => 'Los Pinos']);
        clearActivities();
        recordActivity($team, [
            'event' => 'updated',
            'subject_type' => Team::class,
            'subject_id' => $team->getKey(),
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', 'Los Pinos')));
    });

    it('names an account by its own name', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $account = Account::factory()->bank()->for($team)->create(['name' => 'Caja']);
        clearActivities();
        recordActivity($team, [
            'event' => 'created',
            'subject_type' => Account::class,
            'subject_id' => $account->getKey(),
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', 'Caja')));
    });

    it('names an invitation by its email', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $invitation = TeamInvitation::factory()->for($team)->create([
            'email' => 'ada@example.com',
        ]);
        clearActivities();
        recordActivity($team, [
            'event' => 'created',
            'subject_type' => TeamInvitation::class,
            'subject_id' => $invitation->getKey(),
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', 'ada@example.com')));
    });

    it('names a member by their own name', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        clearActivities();
        recordActivity($team, [
            'event' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $user->getKey(),
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', $user->name)));
    });

    it('names a subscription by the subject label', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $subscription = $team->subscriptions()->sole();
        clearActivities();
        recordActivity($team, [
            'event' => 'created',
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->getKey(),
        ]);

        visitActivity($team)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('activities.data.0.subject_label', trans('Subscription'))));
    });
});

describe('show', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();
        $activity = recordActivity($team);

        openActivity($team, $activity)->assertRedirectToRoute('login');
    });

    it('forbids a member holding the member role on a pro team', function (): void {
        [$user, $team] = actingMember(Role::Member);
        subscribeToPro($team);
        $activity = recordActivity($team);

        openActivity($team, $activity)->assertForbidden();
    });

    it('redirects the owner of a free team to the upgrade modal', function (): void {
        [$user, $team] = actingMember();
        $activity = recordActivity($team);

        openActivity($team, $activity)->assertRedirectToRoute('billing.teams.subscriptions.upgrade', [
            'team' => $team->getRouteKey(),
            'feature' => Feature::ViewActivity->value,
        ]);
    });

    it('does not find an entry of another team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $foreign = recordActivity(Team::factory()->create());

        openActivity($team, $foreign)->assertNotFound();
    });

    it('opens every row the same action wrote, in the order it wrote them', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);
        clearActivities();

        $this->post(route('finance.teams.transactions.store', ['team' => $team->getRouteKey()]), [
            'amount' => '10.50',
            'category' => Category::Food->value,
            'description' => 'Lunch',
            'occurred_at' => '2024-06-01 12:00:00',
            'quote' => '1',
            'type' => 'expense',
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
            'tag_ids' => [$tag->getKey()],
        ])->assertRedirect();

        openActivity($team, activitiesOf($team)->first())
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/activity/show')
                ->has('entries', 2)
                ->where('entries.0.event', 'created')
                ->where('entries.0.causer.name', $user->name)
                ->where('entries.1.event', 'tags_synced'));
    });

    it('opens an entry written outside a batch on its own', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        clearActivities();
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);

        openActivity($team, activitiesOf($team)->sole())
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('entries', 1)
                ->where('entries.0.subject_label', 'Comida'));
    });

    it('names the records a change refers to and keeps the raw key when one is gone', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $account = Account::factory()->bank()->for($team)->create(['name' => 'Checking']);
        $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);
        clearActivities();
        recordActivity($team, [
            'subject_type' => Transaction::class,
            'attribute_changes' => ['old' => [], 'attributes' => [
                'account_id' => $account->getKey(),
                'tags' => [$tag->getKey(), 'missing-tag'],
            ]],
        ]);

        openActivity($team, activitiesOf($team)->sole())
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entries.0.changes.0.field', 'account_id')
                ->where('entries.0.changes.0.new', $account->getKey())
                ->where('entries.0.changes.0.new_label', 'Checking')
                ->where('entries.0.changes.1.field', 'tags')
                ->where('entries.0.changes.1.new_label', ['Comida', 'missing-tag']));
    });

    it('names a role through the enum', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        clearActivities();
        recordActivity($team, [
            'attribute_changes' => [
                'old' => ['role' => Role::Member->value],
                'attributes' => ['role' => Role::Admin->value],
            ],
        ]);

        openActivity($team, activitiesOf($team)->sole())
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entries.0.changes.0.field', 'role')
                ->where('entries.0.changes.0.old_label', trans('Member'))
                ->where('entries.0.changes.0.new_label', trans('Admin')));
    });

    it('leaves a role unlabeled when the value is not a string', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        clearActivities();
        recordActivity($team, [
            'attribute_changes' => [
                'old' => [],
                'attributes' => ['role' => ['admin']],
            ],
        ]);

        openActivity($team, activitiesOf($team)->sole())
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entries.0.changes.0.field', 'role')
                ->where('entries.0.changes.0.new_label', null));
    });

    it('lists a change with only the values it recorded', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $tag = Tag::factory()->for($team)->create(['name' => 'Old']);
        clearActivities();

        $this->patch(route('finance.teams.tags.update', [
            'team' => $team->getRouteKey(),
            'tag' => $tag->getKey(),
        ]), ['name' => 'New'])->assertRedirect();

        openActivity($team, activitiesOf($team)->sole())
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entries.0.changes.0.field', 'name')
                ->where('entries.0.changes.0.old', 'Old')
                ->where('entries.0.changes.0.new', 'New'));
    });
});
