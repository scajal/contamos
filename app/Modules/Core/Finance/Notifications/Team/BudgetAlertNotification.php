<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Notifications\Team;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Notifications\Messages\BrandedMailMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use NumberFormatter;

/**
 * The email telling the Owner and Admins that a month reached
 * a budget's warning threshold or went over its amount, with
 * the figures as they stood at that moment.
 */
class BudgetAlertNotification extends TeamNotification
{
    /**
     * Create a new notification instance.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $spent
     */
    public function __construct(
        Team $team,
        public BudgetPeriod $budget,
        public BudgetAlertKind $kind,
        public CarbonImmutable $month,
        public string $amount,
        public string $spent,
        public float $percentage,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the switch that decides whether a member receives
     * the email.
     */
    public function type(): NotificationType
    {
        return NotificationType::BudgetAlerts;
    }

    /**
     * Write the subject and the body of the email onto the
     * given message, with every figure written the way the
     * Budgets page writes it in the recipient's locale.
     */
    protected function compose(BrandedMailMessage $message, User $notifiable): BrandedMailMessage
    {
        $replacements = [
            'amount' => $this->money($this->amount),
            'month' => $this->month->translatedFormat('F Y'),
            'percentage' => $this->formattedPercentage(),
            'spent' => $this->money($this->spent),
            'target' => $this->targetName(),
            'team' => $this->team->name,
        ];

        // The warning says how far along the month is, and
        // going over says the pot is spent.
        [$subject, $sentence] = match ($this->kind) {
            BudgetAlertKind::Warning => [
                trans('The :target budget reached :percentage on :team', $replacements),
                trans(':target has used :percentage of its budget for :month on :team: :spent of :amount.', $replacements),
            ],
            BudgetAlertKind::Over => [
                trans('The :target budget went over on :team', $replacements),
                trans(':target went over its budget for :month on :team: :spent of :amount, :percentage.', $replacements),
            ],
        };

        return $message
            ->subject($subject)
            ->line($sentence)
            ->action(trans('View budgets'), route('finance.teams.budgets.index', ['team' => $this->team]));
    }

    /**
     * The name of what the budget follows: the category's
     * label in the recipient's locale, or the tag's name.
     */
    protected function targetName(): string
    {
        $target = $this->budget->target();

        return $target instanceof Category ? $target->getLabel() : (string) $target?->name;
    }

    /**
     * Write an amount in the budget's currency.
     */
    protected function money(string $amount): string
    {
        return new Amount()->format($amount, $this->budget->currency);
    }

    /**
     * Write the share of the pot the month used, with at most
     * one decimal and rounded half up, as the page does.
     */
    protected function formattedPercentage(): string
    {
        $formatter = new NumberFormatter(App::getLocale(), NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);

        return (string) $formatter->format($this->percentage / 100);
    }
}
