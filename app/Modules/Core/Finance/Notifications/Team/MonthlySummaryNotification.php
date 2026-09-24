<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Notifications\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Notifications\Messages\BrandedMailMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The email that sums up a month that ended for the Owner
 * and Admins: income, expenses and net per currency, the
 * categories that spent the most and how each budget
 * ended, with a link to the month's report.
 *
 * @see \App\Modules\Core\Finance\Services\MonthlySummary\Actions\SendMonthlySummary
 */
class MonthlySummaryNotification extends TeamNotification
{
    /**
     * Create a new notification instance.
     *
     * @param  string  $month  The month summarized, as "YYYY-MM".
     * @param  array<int, array{currency: array{code: string, decimal_places: int, symbol: string}, income: numeric-string, expenses: numeric-string, net: numeric-string, categories: array<int, array{category: string, expenses: numeric-string}>, budgets: array<int, array{category: string|null, tag: string|null, amount: numeric-string, spent: numeric-string, remaining: numeric-string}>}>  $currencies
     */
    public function __construct(
        Team $team,
        public string $month,
        public array $currencies,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the switch that decides whether a member receives
     * the email.
     */
    public function type(): NotificationType
    {
        return NotificationType::MonthlySummary;
    }

    /**
     * Write the subject and the body of the email onto the
     * given message, with the month, the categories and the
     * amounts written the way the recipient's locale reads
     * them.
     */
    protected function compose(BrandedMailMessage $message, User $notifiable): BrandedMailMessage
    {
        $month = CarbonImmutable::parse($this->month.'-01')->translatedFormat('F Y');

        return $message
            ->subject(trans('Your :month summary for :team', ['month' => $month, 'team' => $this->team->name]))
            ->line(trans('Here is how :month went for :team.', ['month' => $month, 'team' => $this->team->name]))
            ->action(trans('View the monthly report'), route('finance.teams.monthly.index', [
                'month' => $this->month,
                'team' => $this->team,
            ]))
            ->markdown('mail.finance.monthly-summary', [
                'budgets' => $this->budgetRows(),
                'categories' => $this->categorySections(),
                'totals' => $this->totalRows(),
            ]);
    }

    /**
     * One row per currency with the month's income, expenses
     * and net.
     *
     * @return array<int, array{currency: string, income: string, expenses: string, net: string}>
     */
    protected function totalRows(): array
    {
        return array_map(fn (array $group): array => [
            'currency' => $group['currency']['code'],
            'income' => $this->amount($group['income'], $group['currency']),
            'expenses' => $this->amount($group['expenses'], $group['currency']),
            'net' => $this->amount($group['net'], $group['currency']),
        ], $this->currencies);
    }

    /**
     * The categories that spent the most, one list per
     * currency that spent anything.
     *
     * @return array<int, array{currency: string, rows: array<int, array{category: string, spent: string}>}>
     */
    protected function categorySections(): array
    {
        $sections = [];

        foreach ($this->currencies as $group) {
            // A currency that only brought money in has no
            // spending to rank.
            if ($group['categories'] === []) {
                continue;
            }

            $sections[] = [
                'currency' => $group['currency']['code'],
                'rows' => array_map(fn (array $row): array => [
                    'category' => $this->categoryLabel($row['category']),
                    'spent' => $this->amount($row['expenses'], $group['currency']),
                ], $group['categories']),
            ];
        }

        return $sections;
    }

    /**
     * One row per budget with its amount, what was spent and
     * whether the month kept within it.
     *
     * @return array<int, array{name: string, amount: string, spent: string, outcome: string}>
     */
    protected function budgetRows(): array
    {
        $rows = [];

        foreach ($this->currencies as $group) {
            foreach ($group['budgets'] as $budget) {
                $rows[] = [
                    'name' => $budget['category'] !== null
                        ? $this->categoryLabel($budget['category'])
                        : $this->cell((string) $budget['tag']),
                    'amount' => $this->amount($budget['amount'], $group['currency']),
                    'spent' => $this->amount($budget['spent'], $group['currency']),
                    'outcome' => bccomp($budget['remaining'], '0', 18) < 0
                        ? trans('Over budget')
                        : trans('Within budget'),
                ];
            }
        }

        return $rows;
    }

    /**
     * Label a category in the locale the email is rendered
     * in, where a value this build does not know reads as
     * Other, the way the report files it.
     */
    protected function categoryLabel(string $category): string
    {
        return (Category::tryFrom($category) ?? Category::Other)->getLabel();
    }

    /**
     * Write an amount with the currency's symbol and decimal
     * places, in the locale the email is rendered in.
     *
     * @param  numeric-string  $amount
     * @param  array{code: string, decimal_places: int, symbol: string}  $currency
     */
    protected function amount(string $amount, array $currency): string
    {
        $model = new Currency()->forceFill([
            'decimal_places' => $currency['decimal_places'],
            'symbol' => $currency['symbol'],
        ]);

        $formatted = new Amount()->format($amount, $model);

        // The helper writes the amount without its sign, and a
        // month that spent more than it earned must say so.
        return Str::startsWith($amount, '-') ? '-'.$formatted : $formatted;
    }

    /**
     * Keep a name the team typed from breaking out of its
     * table cell.
     */
    protected function cell(string $value): string
    {
        return Str::replace('|', '\|', $value);
    }
}
