<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Customer;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction as BillingTransaction;
use App\Modules\Billing\Notifications\Team\ProEndedNotification;
use App\Modules\Core\Finance\Models\Team\Transaction as FinanceTransaction;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;

/**
 * Feature tests for \App\Modules\Billing\Http\Controllers\WebhookController.
 */

/**
 * Get the Paddle payload of the event that ends the given
 * subscription for good.
 *
 * @return array<string, mixed>
 */
function subscriptionCanceledPayload(string $paddleId): array
{
    return [
        'event_type' => 'subscription.canceled',
        'data' => [
            'id' => $paddleId,
            'status' => 'canceled',
            'canceled_at' => CarbonImmutable::now()->toIso8601String(),
        ],
    ];
}

/**
 * Get the paddle ID of the only subscription of the team.
 */
function proSubscriptionId(Team $team): string
{
    return $team->subscriptions()->sole()->paddle_id;
}

describe('subscription.canceled', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('emails the owner, and not the admins or members, once the subscription ends', function (): void {
        [$owner, $team] = memberWithTeam();
        $admin = memberOf($team);
        $member = memberOf($team, Role::Member);
        subscribeToPro($team);

        postJson(route('billing.webhook'), subscriptionCanceledPayload(proSubscriptionId($team)))->assertOk();

        expect($team->fresh()?->plan())->toBe(Plan::Free);
        Notification::assertSentTo($owner, ProEndedNotification::class, fn (ProEndedNotification $notification): bool => $notification->team->is($team));
        Notification::assertNotSentTo([$admin, $member], ProEndedNotification::class);
        Notification::assertCount(1);
    });

    it('emails the owner even with every notification switched off', function (): void {
        [$owner, $team] = memberWithTeam();
        $team->users()->updateExistingPivot($owner, [
            'notification_preferences' => array_fill_keys(array_map(fn (NotificationType $type): string => $type->value, NotificationType::cases()), false),
        ]);
        subscribeToPro($team);

        postJson(route('billing.webhook'), subscriptionCanceledPayload(proSubscriptionId($team)))->assertOk();

        Notification::assertSentTo($owner, ProEndedNotification::class);
    });

    it('emails the owner once when paddle retries the webhook', function (): void {
        [$owner, $team] = memberWithTeam();
        subscribeToPro($team);
        $payload = subscriptionCanceledPayload(proSubscriptionId($team));

        postJson(route('billing.webhook'), $payload)->assertOk();
        postJson(route('billing.webhook'), $payload)->assertOk();

        Notification::assertSentToTimes($owner, ProEndedNotification::class, 1);
    });

    it('emails the owner again when a later subscription ends too', function (): void {
        [$owner, $team] = memberWithTeam();
        subscribeToPro($team);
        $first = proSubscriptionId($team);
        postJson(route('billing.webhook'), subscriptionCanceledPayload($first))->assertOk();

        subscribeToPro($team);
        $second = $team->subscriptions()->where('paddle_id', '!=', $first)->sole()->paddle_id;
        postJson(route('billing.webhook'), subscriptionCanceledPayload($second))->assertOk();

        Notification::assertSentToTimes($owner, ProEndedNotification::class, 2);
    });

    it('sends nothing while a pro plan granted by hand still stands', function (): void {
        [, $team] = memberWithTeam();
        $team->update(['plan_override' => Plan::Pro, 'plan_override_expires_at' => CarbonImmutable::now()->addMonth()]);
        subscribeToPro($team);

        postJson(route('billing.webhook'), subscriptionCanceledPayload(proSubscriptionId($team)))->assertOk();

        expect($team->fresh()?->plan())->toBe(Plan::Pro);
        Notification::assertNothingSent();
    });

    it('sends nothing for a subscription that does not belong to a team', function (): void {
        $user = User::factory()->create();
        $subscription = Subscription::query()->create([
            'billable_type' => User::class,
            'billable_id' => $user->getKey(),
            'type' => 'default',
            'paddle_id' => 'sub_user',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        postJson(route('billing.webhook'), subscriptionCanceledPayload('sub_user'))->assertOk();

        expect($subscription->fresh()?->status)->toBe(Subscription::STATUS_CANCELED);
        Notification::assertNothingSent();
    });

    it('sends nothing for a subscription it does not know', function (): void {
        postJson(route('billing.webhook'), subscriptionCanceledPayload('sub_unknown'))->assertOk();

        Notification::assertNothingSent();
    });
});

describe('subscription.updated', function (): void {
    it('sends nothing when a cancellation is only scheduled', function (): void {
        Notification::fake();
        [, $team] = memberWithTeam();
        subscribeToPro($team);

        postJson(route('billing.webhook'), [
            'event_type' => 'subscription.updated',
            'data' => [
                'id' => proSubscriptionId($team),
                'status' => 'active',
                'scheduled_change' => [
                    'action' => 'cancel',
                    'effective_at' => CarbonImmutable::now()->addMonth()->toIso8601String(),
                ],
                'items' => [[
                    'status' => 'active',
                    'quantity' => 1,
                    'price' => [
                        'id' => Plan::Pro->priceId(BillingCycle::Monthly),
                        'product_id' => Plan::Pro->productId(),
                    ],
                ]],
            ],
        ])->assertOk();

        expect($team->fresh()?->subscription()?->onGracePeriod())->toBeTrue()
            ->and($team->fresh()?->plan())->toBe(Plan::Pro);
        Notification::assertNothingSent();
    });
});

describe('transaction.completed', function (): void {
    it('records the paddle transaction against the team billing history', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $team->customer()->create([
            'email' => $user->email,
            'name' => $team->name,
            'paddle_id' => 'ctm_test',
        ]);

        postJson(route('billing.webhook'), [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_test',
                'customer_id' => 'ctm_test',
                'subscription_id' => $team->subscription()?->paddle_id,
                'invoice_number' => '130674-10001',
                'status' => 'completed',
                'currency_code' => 'USD',
                'billed_at' => '2026-09-22T18:39:10Z',
                'details' => ['totals' => ['total' => '499', 'tax' => '90']],
            ],
        ])->assertOk();

        expect(BillingTransaction::query()->where('paddle_id', 'txn_test')->first())
            ->not->toBeNull()
            ->billable_id->toBe($team->getKey())
            ->invoice_number->toBe('130674-10001')
            ->total->toBe('499')
            ->and(FinanceTransaction::query()->count())->toBe(0);
    });

    it('ignores a transaction of a customer that is not a team', function (): void {
        $user = User::factory()->create();
        Customer::query()->create([
            'billable_type' => User::class,
            'billable_id' => $user->getKey(),
            'email' => $user->email,
            'name' => $user->name,
            'paddle_id' => 'ctm_user',
        ]);

        postJson(route('billing.webhook'), [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_user',
                'customer_id' => 'ctm_user',
                'subscription_id' => null,
                'invoice_number' => null,
                'status' => 'completed',
                'currency_code' => 'USD',
                'billed_at' => '2026-09-22T18:39:10Z',
                'details' => ['totals' => ['total' => '499', 'tax' => '90']],
            ],
        ])->assertOk();

        expect(BillingTransaction::query()->count())->toBe(0);
    });

    it('ignores a transaction it already recorded', function (): void {
        [$user, $team] = actingMember();
        $team->customer()->create([
            'email' => $user->email,
            'name' => $team->name,
            'paddle_id' => 'ctm_test',
        ]);

        $payload = [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_test',
                'customer_id' => 'ctm_test',
                'subscription_id' => null,
                'invoice_number' => null,
                'status' => 'completed',
                'currency_code' => 'USD',
                'billed_at' => '2026-09-22T18:39:10Z',
                'details' => ['totals' => ['total' => '499', 'tax' => '90']],
            ],
        ];

        postJson(route('billing.webhook'), $payload)->assertOk();
        postJson(route('billing.webhook'), $payload)->assertOk();

        expect(BillingTransaction::query()->count())->toBe(1);
    });
});
