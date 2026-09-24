<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Services\MonthlySummary\Actions;

use App\Modules\Core\Finance\Notifications\Team\MonthlySummaryNotification;
use App\Modules\Core\Finance\Reports\Team\MonthlyReport;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Notification;

/**
 * Emails a team's Owner and Admins the summary of a month
 * that ended, read from the same report the monthly page
 * shows, so the numbers in the inbox and on the page are
 * always the same.
 */
class SendMonthlySummary
{
    /**
     * How many categories the summary lists per currency.
     */
    public const int TOP_CATEGORIES = 5;

    /**
     * The scale the money strings are compared with,
     * matching the decimal cast on the amount columns.
     */
    protected const int SCALE = 18;

    /**
     * The team whose month is summarized.
     */
    public ?Team $team = null;

    /**
     * Create a new action instance.
     */
    public function __construct(protected MonthlyReport $report) {}

    /**
     * Set the team whose month is summarized.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Send the summary of the given month, and tell whether
     * there was anything to send. A month the report has no
     * income, expenses or budgets for sends nothing.
     */
    public function using(CarbonImmutable $month): bool
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A monthly summary cannot be sent without a team.');
        }

        $month = $month->startOfMonth();

        // The window is the one the monthly page opens with, so
        // the figures are the ones the page shows. A month that
        // just ended is always inside it, since every plan opens
        // more than the current month.
        $earliest = $this->report->earliestMonth($this->team, $this->report->latestMonth());

        $currencies = $this->summarize($this->report->figures($this->team, $month, $earliest));

        // Nothing moved, so there is nothing worth an email.
        if ($currencies === []) {
            return false;
        }

        // The figures are worked out once and travel with the
        // email, so every recipient reads the same numbers
        // however long the queue takes to reach them.
        Notification::send(
            $this->team->notificationRecipients(NotificationType::MonthlySummary),
            new MonthlySummaryNotification(
                $this->team,
                $month->format(MonthlyReport::MONTH_FORMAT),
                $currencies,
            ),
        );

        return true;
    }

    /**
     * Keep, of each currency's figures, what the summary
     * shows: the totals, the categories that spent the most
     * and every budget. Amounts in different currencies are
     * never comparable, so each currency ranks its own
     * categories. A currency the month did not touch is left
     * out.
     *
     * @param  array<int, array<string, mixed>>  $figures
     * @return array<int, array{currency: array{code: string, decimal_places: int, symbol: string}, income: numeric-string, expenses: numeric-string, net: numeric-string, categories: array<int, array{category: string, expenses: numeric-string}>, budgets: array<int, array{category: string|null, tag: string|null, amount: numeric-string, spent: numeric-string, remaining: numeric-string}>}>
     */
    protected function summarize(array $figures): array
    {
        $summaries = [];

        foreach ($figures as $group) {
            $currency = $this->field($group, 'currency');

            $summary = [
                'currency' => [
                    'code' => $this->text($this->field($currency, 'code')),
                    'decimal_places' => (int) $this->decimal($this->field($currency, 'decimal_places')),
                    'symbol' => $this->text($this->field($currency, 'symbol')),
                ],
                'income' => $this->decimal($this->field($this->field($group, 'income'), 'amount')),
                'expenses' => $this->decimal($this->field($this->field($group, 'expenses'), 'amount')),
                'net' => $this->decimal($this->field($this->field($group, 'net'), 'amount')),
                'categories' => $this->topCategories($this->rows($this->field($group, 'categories'))),
                'budgets' => [
                    ...$this->budgets($this->rows($this->field($group, 'categories')), 'category'),
                    ...$this->budgets($this->rows($this->field($group, 'tags')), 'name'),
                ],
            ];

            // A currency the month neither earned, spent nor
            // budgeted in has nothing to report.
            $isQuiet = $summary['budgets'] === []
                && bccomp($summary['income'], '0', self::SCALE) === 0
                && bccomp($summary['expenses'], '0', self::SCALE) === 0;

            if (! $isQuiet) {
                $summaries[] = $summary;
            }
        }

        return $summaries;
    }

    /**
     * The categories that spent the most in the month,
     * biggest first, where a tie keeps the order the
     * report lists them in.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array{category: string, expenses: numeric-string}>
     */
    protected function topCategories(array $rows): array
    {
        $categories = array_map(fn (mixed $row): array => [
            'category' => $this->text($this->field($row, 'category')),
            'expenses' => $this->decimal($this->field($row, 'expenses')),
        ], $rows);

        // Only what actually spent is ranked.
        $spending = array_filter(
            $categories,
            fn (array $row): bool => bccomp($row['expenses'], '0', self::SCALE) > 0,
        );

        usort($spending, fn (array $first, array $second): int => bccomp($second['expenses'], $first['expenses'], self::SCALE));

        return array_slice($spending, 0, self::TOP_CATEGORIES);
    }

    /**
     * The budgets of the given breakdown rows with what the
     * month made of them, named by the category or by the
     * tag they follow.
     *
     * @param  array<int, mixed>  $rows
     * @param  'category'|'name'  $target
     * @return array<int, array{category: string|null, tag: string|null, amount: numeric-string, spent: numeric-string, remaining: numeric-string}>
     */
    protected function budgets(array $rows, string $target): array
    {
        $budgets = [];

        foreach ($rows as $row) {
            $budget = $this->field($row, 'budget');

            // A row with no budget behind it has nothing to
            // report here.
            if (! is_array($budget)) {
                continue;
            }

            $budgets[] = [
                'category' => $target === 'category' ? $this->text($this->field($row, 'category')) : null,
                'tag' => $target === 'name' ? $this->text($this->field($row, 'name')) : null,
                'amount' => $this->decimal($this->field($budget, 'amount')),
                'spent' => $this->decimal($this->field($budget, 'spent')),
                'remaining' => $this->decimal($this->field($budget, 'remaining')),
            ];
        }

        return $budgets;
    }

    /**
     * Read one entry of a figure of the report, or null when
     * the figure does not hold it.
     */
    protected function field(mixed $values, string $key): mixed
    {
        return is_array($values) ? $values[$key] ?? null : null;
    }

    /**
     * Read a breakdown of the report as a list of rows.
     *
     * @return array<int, mixed>
     */
    protected function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Read a figure of the report as a money string.
     *
     * @return numeric-string
     */
    protected function decimal(mixed $amount): string
    {
        $value = $this->text($amount);

        return is_numeric($value) ? $value : '0';
    }

    /**
     * Read a label of the report as a string.
     */
    protected function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
