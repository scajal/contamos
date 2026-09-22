<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\CreateRecurringRule;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages\CreateRecurringRule.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\CreateRecurringRule,
 * then attaches the tags its occurrences will carry.
 */
it('opens a rule for the current tenant with its tags', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->for($team)->create();

    Livewire::test(CreateRecurringRule::class)
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
            'category' => Category::Food->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = RecurringRule::query()->where('description', 'Rent')->sole();

    expect($rule->team_id)->toBe($team->getKey())
        ->and($rule->frequency)->toBe(Frequency::Monthly)
        ->and($rule->tags->modelKeys())->toBe([$tag->getKey()]);
});
