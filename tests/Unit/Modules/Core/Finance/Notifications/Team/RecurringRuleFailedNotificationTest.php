<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\RecurringRule\FailureReason;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Notifications\Team\RecurringRuleFailedNotification;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Notifications\Team\RecurringRuleFailedNotification.
 */

/**
 * A rule on a team named Casa, with the given description.
 */
function failedRule(?string $description): RecurringRule
{
    $team = Team::factory()->create(['name' => 'Casa']);

    return RecurringRule::factory()->create([
        'account_id' => Account::factory()->for($team)->create()->getKey(),
        'description' => $description,
        'type' => TransactionType::Expense,
    ]);
}

it('is switched by the recurring failures setting', function (): void {
    $rule = failedRule('Rent');

    expect(new RecurringRuleFailedNotification($rule->team, $rule, FailureReason::MissingRate)->type())
        ->toBe(NotificationType::RecurringFailures);
});

it('names the rule and the reason and links to the rule', function (string $locale, FailureReason $reason, string $subject, array $lines, string $action): void {
    $rule = failedRule('Rent');
    $notification = new RecurringRuleFailedNotification($rule->team, $rule, $reason);
    App::setLocale($locale);

    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe($lines)
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe(route('finance.teams.recurring-rules.show', ['team' => $rule->team, 'recurringRule' => $rule]))
        ->and((string) $mail->render())->toContain($lines[1], $action.'</a>');
})->with([
    'English' => ['en', FailureReason::InactiveAccount, 'A recurring transaction failed on Casa', [
        'The recurring transaction "Rent" on Casa could not be recorded.',
        'The account is inactive.',
        'It is tried again every hour until the cause is fixed.',
    ], 'View recurring transaction'],
    'Spanish' => ['es', FailureReason::MissingRate, 'Falló un movimiento recurrente en Casa', [
        'No se pudo registrar el movimiento recurrente "Rent" en Casa.',
        'No hay cotización para la fecha.',
        'Se vuelve a intentar cada hora hasta que se resuelva la causa.',
    ], 'Ver movimiento recurrente'],
]);

it('names a rule without a description by its type', function (): void {
    $rule = failedRule(null);

    $mail = new RecurringRuleFailedNotification($rule->team, $rule, FailureReason::MissingCurrency)
        ->toMail(User::factory()->create());

    expect($mail->introLines)->toBe([
        'The recurring transaction "Expense" on Casa could not be recorded.',
        'The currency is no longer on the account.',
        'It is tried again every hour until the cause is fixed.',
    ]);
});
