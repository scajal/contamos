<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\RecurringRule\Status;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use Carbon\CarbonImmutable;

/**
 * Unit tests for \App\Modules\Core\Finance\Models\Team\RecurringRule.
 */

/**
 * Build an unsaved rule with the given schedule, so the computation
 * is exercised without touching the database.
 *
 * @param  array<string, mixed>  $attributes
 */
function rule(array $attributes = []): RecurringRule
{
    return RecurringRule::factory()->makeOne([
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        ...$attributes,
    ]);
}

it('returns the start date as the first due date', function (): void {
    $rule = rule(['starts_on' => '2026-03-10']);

    expect($rule->dueDateOnOrAfter(CarbonImmutable::parse('2026-01-01'))->toDateString())->toBe('2026-03-10');
});

it('walks daily schedules by their interval', function (): void {
    $rule = rule(['frequency' => Frequency::Daily, 'interval' => 3, 'starts_on' => '2026-01-01']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2026-01-01'))->toDateString())->toBe('2026-01-04')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2026-01-04'))->toDateString())->toBe('2026-01-07')
        ->and($rule->dueDateOnOrAfter(CarbonImmutable::parse('2026-01-05'))->toDateString())->toBe('2026-01-07');
});

it('walks weekly schedules by their interval', function (): void {
    $rule = rule(['frequency' => Frequency::Weekly, 'interval' => 2, 'starts_on' => '2026-01-05']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2026-01-05'))->toDateString())->toBe('2026-01-19')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2026-01-19'))->toDateString())->toBe('2026-02-02');
});

it('walks monthly schedules by their interval', function (): void {
    $rule = rule(['frequency' => Frequency::Monthly, 'interval' => 3, 'starts_on' => '2026-01-10']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2026-01-10'))->toDateString())->toBe('2026-04-10')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2026-04-10'))->toDateString())->toBe('2026-07-10');
});

it('walks yearly schedules by their interval', function (): void {
    $rule = rule(['frequency' => Frequency::Yearly, 'interval' => 2, 'starts_on' => '2026-06-01']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2026-06-01'))->toDateString())->toBe('2028-06-01');
});

it('clamps a monthly rule anchored on the 31st to shorter months', function (): void {
    $rule = rule(['frequency' => Frequency::Monthly, 'starts_on' => '2026-01-31']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2026-01-31'))->toDateString())->toBe('2026-02-28')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2026-02-28'))->toDateString())->toBe('2026-03-31')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2026-03-31'))->toDateString())->toBe('2026-04-30');
});

it('clamps a yearly rule anchored on 29 February to the 28th in common years', function (): void {
    $rule = rule(['frequency' => Frequency::Yearly, 'starts_on' => '2024-02-29']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2024-02-29'))->toDateString())->toBe('2025-02-28')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2027-02-28'))->toDateString())->toBe('2028-02-29');
});

it('stops at an inclusive end date', function (): void {
    $rule = rule(['frequency' => Frequency::Monthly, 'starts_on' => '2026-01-15', 'ends_on' => '2026-03-15']);

    expect($rule->dueDateAfter(CarbonImmutable::parse('2026-02-15'))->toDateString())->toBe('2026-03-15')
        ->and($rule->dueDateAfter(CarbonImmutable::parse('2026-03-15')))->toBeNull();
});

it('converts a due date to an instant at the local time of day', function (): void {
    $rule = rule(['time_of_day' => '09:00:00', 'timezone' => 'America/Argentina/Buenos_Aires']);

    expect($rule->dueInstantFor(CarbonImmutable::parse('2026-03-10'))->toIso8601String())
        ->toBe('2026-03-10T12:00:00+00:00');
});

it('takes the next due date from the last one generated', function (): void {
    $rule = rule([
        'frequency' => Frequency::Monthly,
        'starts_on' => '2026-01-15',
        'last_generated_due_date' => '2026-02-15',
    ]);

    expect($rule->next_due_date->toDateString())->toBe('2026-03-15');
});

it('takes the next due date from the start date when nothing was generated', function (): void {
    $rule = rule(['frequency' => Frequency::Monthly, 'starts_on' => '2026-01-15']);

    expect($rule->next_due_date->toDateString())->toBe('2026-01-15');
});

it('recomputes the next due date after a schedule edit', function (): void {
    $rule = rule([
        'frequency' => Frequency::Monthly,
        'starts_on' => '2026-01-15',
        'last_generated_due_date' => '2026-02-15',
    ]);

    $rule->forceFill(['frequency' => Frequency::Weekly, 'interval' => 2, 'starts_on' => '2026-01-15']);

    expect($rule->next_due_date->toDateString())->toBe('2026-02-26');
});

it('has no next due date once the schedule has ended', function (): void {
    $rule = rule(['starts_on' => '2026-01-15', 'ends_on' => '2026-02-15', 'last_generated_due_date' => '2026-02-15']);

    expect($rule->next_due_date)->toBeNull();
});

it('reports the due date it skipped while it was not generating', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-20 10:00:00'));

    $rule = rule([
        'frequency' => Frequency::Monthly,
        'starts_on' => '2026-01-15',
        'last_generated_due_date' => '2026-02-15',
    ]);

    expect($rule->skipped_due_date->toDateString())->toBe('2026-05-15');
});

it('reports the rule status from its flag and failure', function (): void {
    expect(rule()->status)->toBe(Status::Active)
        ->and(rule(['is_active' => false])->status)->toBe(Status::Paused)
        ->and(rule(['failure_reason' => 'No rate'])->status)->toBe(Status::Failed)
        ->and(rule(['is_active' => false, 'failure_reason' => 'No rate'])->status)->toBe(Status::Paused);
});
