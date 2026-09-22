<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\EditRecurringRule;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\EditRecurringRule.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\UpdateRecurringRule
 * and \App\Modules\Core\Finance\Actions\Team\DeleteRecurringRule.
 */
it('updates the rule and the tags it carries', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->for($team)->create();
    $rule = RecurringRule::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.00',
        'currency_id' => $currency->getKey(),
        'description' => 'Old',
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => CarbonImmutable::now()->addDay()->toDateString(),
        'team_id' => $team->getKey(),
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
    ]);

    Livewire::test(EditRecurringRule::class, ['record' => $rule->getKey()])
        ->fillForm([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'description' => 'Rent',
            'frequency' => Frequency::Monthly->value,
            'interval' => 1,
            'starts_on' => CarbonImmutable::now()->addDay()->toDateString(),
            'tag_ids' => [$tag->getKey()],
            'time_of_day' => '09:00',
            'timezone' => 'UTC',
            'type' => TransactionType::Expense->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($rule->fresh()?->description)->toBe('Rent')
        ->and((float) $rule->fresh()?->amount)->toBe(25.0)
        ->and($rule->fresh()?->tags->modelKeys())->toBe([$tag->getKey()]);
});

it('deletes the rule', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $rule = RecurringRule::factory()->for(Account::factory()->for($team))->create();

    Livewire::test(EditRecurringRule::class, ['record' => $rule->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    expect(RecurringRule::query()->find($rule->getKey()))->toBeNull();
});
