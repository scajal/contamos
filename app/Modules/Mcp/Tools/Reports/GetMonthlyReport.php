<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Reports;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Reports\Team\MonthlyReport;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Reports a month the way the monthly page does: totals per
 * currency against the month before, transfers, the trend and
 * the breakdowns by category and by tag with their budgets.
 */
#[Name('get_monthly_report')]
#[Description("Get the monthly report of the team, as the Monthly page shows it. For each currency: income, expenses and net with the change against the previous month (delta is null when that month is outside the plan's history; percentage is null when the previous month was zero), transfers in/out between the team's own accounts (null when none), the trend of up to 6 months ending on this one, and breakdowns by category and by tag (the last tag row, with null id and name, is untagged money). A transaction counts in full under every tag it carries, so tag rows may add up to more than the totals. Each row carries its budget progress (amount, income, spent, remaining, percentage) when the team has a budget for it and budgets are included in its plan, else null. Only active accounts count. A month before the plan's history window comes back with `is_locked: true` and no figures.")]
#[IsReadOnly]
class GetMonthlyReport extends Tool
{
    use InteractsWithTeams;
    use PresentsRecords;

    /**
     * The keys of the figures that hold amounts of money.
     *
     * @var array<int, string>
     */
    protected const array AMOUNT_KEYS = ['amount', 'income', 'expenses', 'net', 'moved_in', 'moved_out', 'spent', 'remaining'];

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'month' => $schema->string()
                ->description('The month to report, as "YYYY-MM". Defaults to the current month.'),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, MonthlyReport $report): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $report): ResponseFactory {
            $request->validate([
                'month' => ['sometimes', 'nullable', 'string', 'date_format:Y-m'],
            ]);

            $latest = $report->latestMonth();

            $month = is_string($value = $request->get('month')) && $value !== ''
                ? CarbonImmutable::createFromFormat('!'.MonthlyReport::MONTH_FORMAT, $value)?->startOfMonth() ?? $latest
                : $latest;

            // A month that has not happened yet has nothing
            // to report.
            if ($month->greaterThan($latest)) {
                throw new ToolError("{$month->format(MonthlyReport::MONTH_FORMAT)} has not happened yet. The latest month there is a report for is {$latest->format(MonthlyReport::MONTH_FORMAT)}.");
            }

            $earliest = $report->earliestMonth($team, $latest);

            $isLocked = $report->isLocked($month, $earliest);

            // A month before the plan's window stays reachable
            // but carries no figures, as on the web.
            return Response::structured([
                'month' => $month->format(MonthlyReport::MONTH_FORMAT),
                'earliest_month' => $earliest->format(MonthlyReport::MONTH_FORMAT),
                'latest_month' => $latest->format(MonthlyReport::MONTH_FORMAT),
                'is_locked' => $isLocked,
                'upgrade_url' => $isLocked ? $this->upgradeUrl($team, Feature::ViewHistory) : null,
                'budgets_included' => Feature::ManageBudgets->enabled($team),
                'currencies' => $isLocked ? [] : array_map(
                    $this->presentFigures(...),
                    $report->figures($team, $month, $earliest),
                ),
            ]);
        });
    }

    /**
     * Present one currency's figures with every amount as a
     * decimal string of the currency's decimal places.
     *
     * @param  array<string, mixed>  $figures
     * @return array<string, mixed>
     */
    protected function presentFigures(array $figures): array
    {
        /** @var array<string, mixed> $currency */
        $currency = is_array($figures['currency'] ?? null) ? $figures['currency'] : [];

        $decimals = is_numeric($currency['decimal_places'] ?? null) ? (int) $currency['decimal_places'] : 2;

        $presented = $this->amounts($figures, $decimals);

        // The currency is named by its code, the way every
        // other tool names it.
        $presented['currency'] = [
            'code' => $currency['code'] ?? null,
            'decimal_places' => $decimals,
        ];

        return $presented;
    }

    /**
     * Format every amount found in the figures, at any depth,
     * leaving percentages and labels as they are.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $values
     * @return array<TKey, mixed>
     */
    protected function amounts(array $values, int $decimals): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->amounts($value, $decimals);
            } elseif (is_string($key) && in_array($key, self::AMOUNT_KEYS, true) && is_numeric($value)) {
                $values[$key] = $this->amount($value, $decimals);
            }
        }

        return $values;
    }
}
