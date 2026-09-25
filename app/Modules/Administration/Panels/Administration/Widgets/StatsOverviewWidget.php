<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Widgets;

use App\Modules\Administration\Panels\Administration\Resources\Teams\TeamResource;
use App\Modules\Administration\Panels\Administration\Resources\Users\UserResource;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction as BillingTransaction;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Laravel\Paddle\Subscription as PaddleSubscription;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;

class StatsOverviewWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * How many days the activity charts look back.
     */
    protected const int CHART_DAYS = 30;

    /**
     * Get the heading of the widget.
     */
    #[\Override]
    protected function getHeading(): ?string
    {
        return trans('Application');
    }

    /**
     * Get the stats for the widget: how the user base grows,
     * how many teams pay, what came in this month, whether
     * people are using the app, and which subscriptions need
     * a look.
     *
     * @return array<\Filament\Widgets\StatsOverviewWidget\Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        return [
            $this->users(),
            $this->teams(),
            $this->proTeams(),
            $this->revenue(),
            $this->activity(),
            $this->attention(),
        ];
    }

    /**
     * How many people signed up, with this month's signups
     * and a daily chart of the last month.
     */
    protected function users(): Stat
    {
        return Stat::make(trans('Users'), Number::format(User::query()->count()))
            ->description(trans(':count new this month', ['count' => $this->sinceMonthStart(new User)]))
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
            ->chart($this->dailyCounts(new User))
            ->url(UserResource::getUrl())
            ->icon(Heroicon::OutlinedUserGroup)
            ->color(Color::Blue);
    }

    /**
     * How many teams exist, with this month's new ones and a
     * daily chart of the last month.
     */
    protected function teams(): Stat
    {
        return Stat::make(trans('Teams'), Number::format(Team::query()->count()))
            ->description(trans(':count new this month', ['count' => $this->sinceMonthStart(new Team)]))
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
            ->chart($this->dailyCounts(new Team))
            ->url(TeamResource::getUrl())
            ->icon(Heroicon::OutlinedUsers)
            ->color(Color::Lime);
    }

    /**
     * How many teams are on Pro, whether by an active
     * subscription or by a plan granted by hand.
     */
    protected function proTeams(): Stat
    {
        // A plan granted by hand wins over the subscription, so a
        // subscribed team only counts when nothing overrides it.
        $subscribed = Team::query()
            ->whereHas('subscriptions', fn (Builder $query): Builder => $query->where('status', PaddleSubscription::STATUS_ACTIVE))
            ->whereNot($this->grantedPlan(...))
            ->count();

        $granted = Team::query()->where($this->grantedPlan(...))->where('plan_override', Plan::Pro)->count();

        return Stat::make(trans('Pro teams'), Number::format($subscribed + $granted))
            ->description(trans(':count granted by hand', ['count' => $granted]))
            ->descriptionIcon(Heroicon::OutlinedGift)
            ->url(TeamResource::getUrl())
            ->icon(Heroicon::OutlinedSparkles)
            ->color(Color::Amber);
    }

    /**
     * What Paddle collected this month, in the currency that
     * brought in the most, against last month.
     */
    protected function revenue(): Stat
    {
        $now = CarbonImmutable::now();
        $current = $this->collected($now->startOfMonth(), $now);
        $previous = $this->collected($now->subMonth()->startOfMonth(), $now->startOfMonth());

        // Six months of the leading currency give the chart its
        // shape; a currency that only shows up once is noise.
        $chart = [];

        foreach (range(5, 0) as $monthsAgo) {
            $start = $now->subMonths($monthsAgo)->startOfMonth();
            $chart[] = (float) $this->majorUnits($current['currency'], $this->collected($start, $start->addMonth())['totals'][$current['currency']] ?? '0');
        }

        return Stat::make(trans('Revenue this month'), $this->money($current['currency'], $current['total']))
            ->description(trans(':amount last month', ['amount' => $this->money($current['currency'], $previous['totals'][$current['currency']] ?? '0')]))
            ->descriptionIcon(Heroicon::OutlinedCalendar)
            ->chart($chart)
            ->icon(Heroicon::OutlinedBanknotes)
            ->color(Color::Emerald);
    }

    /**
     * How much the app is being used: transactions people
     * recorded in the last month, with the last week called
     * out and a daily chart.
     */
    protected function activity(): Stat
    {
        $daily = $this->dailyCounts(new Transaction);

        return Stat::make(trans('Transactions recorded'), Number::format(array_sum($daily)))
            ->description(trans(':count in the last 7 days', ['count' => array_sum(array_slice($daily, -7))]))
            ->descriptionIcon(Heroicon::OutlinedClock)
            ->chart($daily)
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color(Color::Violet);
    }

    /**
     * Subscriptions that need a look: payments that failed and
     * cancellations still running out their paid period.
     */
    protected function attention(): Stat
    {
        $pastDue = Subscription::query()->where('status', PaddleSubscription::STATUS_PAST_DUE)->count();
        $cancelling = Subscription::query()
            ->where('status', PaddleSubscription::STATUS_CANCELED)
            ->where('ends_at', '>', CarbonImmutable::now())
            ->count();

        return Stat::make(trans('Needs attention'), Number::format($pastDue + $cancelling))
            ->description(trans(':past_due past due, :cancelling cancelling', ['past_due' => $pastDue, 'cancelling' => $cancelling]))
            ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
            ->icon(Heroicon::OutlinedBellAlert)
            ->color($pastDue + $cancelling > 0 ? Color::Red : Color::Zinc);
    }

    /**
     * Constrain a team query to the ones with a plan granted
     * by hand that has not lapsed.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Identity\Models\Team>  $query
     */
    protected function grantedPlan(Builder $query): void
    {
        $query
            ->whereNotNull('plan_override')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('plan_override_expires_at')
                ->orWhere('plan_override_expires_at', '>', CarbonImmutable::now()));
    }

    /**
     * Count the rows of the model created since the start of
     * this month.
     */
    protected function sinceMonthStart(Model $model): int
    {
        return $model->newQuery()->where($this->createdAtColumn($model), '>=', CarbonImmutable::now()->startOfMonth())->count();
    }

    /**
     * Count the rows created on each of the last days, oldest
     * first, so a chart can show the trend. Grouping happens
     * here rather than in SQL so it reads the same on SQLite
     * and MySQL.
     *
     * @return array<int, int>
     */
    protected function dailyCounts(Model $model): array
    {
        $start = CarbonImmutable::today()->subDays(self::CHART_DAYS - 1);
        $column = $this->createdAtColumn($model);

        /** @var array<string, int> $perDay */
        $perDay = $model->newQuery()
            ->where($column, '>=', $start)
            ->pluck($column)
            ->map(fn (mixed $date): string => $date instanceof \DateTimeInterface ? CarbonImmutable::instance($date)->toDateString() : CarbonImmutable::parse(is_string($date) ? $date : '')->toDateString())
            ->countBy()
            ->all();

        // Every day gets a point, even the quiet ones.
        $counts = [];

        foreach (range(0, self::CHART_DAYS - 1) as $offset) {
            $counts[] = $perDay[$start->addDays($offset)->toDateString()] ?? 0;
        }

        return $counts;
    }

    /**
     * The column that records when a row of the model was
     * created. The models charted here all keep timestamps.
     */
    protected function createdAtColumn(Model $model): string
    {
        $column = $model->getCreatedAtColumn();

        // A model without timestamps has nothing to chart.
        throw_unless(is_string($column), \LogicException::class, $model::class.' does not record when its rows were created.');

        return $column;
    }

    /**
     * Sum what Paddle collected in the period per currency, with
     * the leading currency and its total pulled out for display.
     *
     * @return array{currency: string, total: string, totals: array<string, string>}
     */
    protected function collected(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var array<string, string> $totals */
        $totals = BillingTransaction::query()
            ->whereIn('status', ['paid', 'completed'])
            ->where('billed_at', '>=', $from)
            ->where('billed_at', '<', $to)
            ->selectRaw('currency, SUM(total) AS total')
            ->groupBy('currency')
            ->orderByDesc('total')
            ->pluck('total', 'currency')
            ->map(fn (mixed $total): string => is_numeric($total) ? (string) $total : '0')
            ->all();

        // The leading currency is the one that brought in most.
        $currency = array_key_first($totals) ?? 'USD';

        return ['currency' => $currency, 'total' => $totals[$currency] ?? '0', 'totals' => $totals];
    }

    /**
     * Format a Paddle total with its currency code. The column
     * is minor units, so the figure shown is the major-unit
     * price in the current locale.
     */
    protected function money(string $currency, string $amount): string
    {
        // 499 USD is four dollars and ninety-nine cents, written
        // with the locale's own decimal mark.
        $major = $this->majorUnits($currency, $amount);
        $decimals = str_contains($major, '.') ? strlen(substr($major, strpos($major, '.') + 1)) : 0;
        $formatted = Number::format((float) $major, $decimals, locale: App::getLocale());

        return $currency.' '.(is_string($formatted) ? $formatted : $major);
    }

    /**
     * Turn a minor-unit total into a major-unit decimal
     * string, such as "499" USD into "4.99".
     */
    protected function majorUnits(string $currency, string $amount): string
    {
        return (new DecimalMoneyFormatter(new ISOCurrencies))->format(new Money($this->wholeMinorUnits($amount), new Currency(strtoupper($currency))));
    }

    /**
     * The minor-unit total as a whole number. A summed column
     * can come back with a trailing fraction of zeros.
     */
    protected function wholeMinorUnits(string $amount): string
    {
        if (! is_numeric($amount)) {
            return '0';
        }

        if (! str_contains($amount, '.')) {
            return $amount;
        }

        [$whole, $fraction] = explode('.', $amount, 2);

        // Only a fraction of zeros is safe to drop. Anything else
        // is not a minor-unit total.
        if (rtrim($fraction, '0') !== '') {
            return '0';
        }

        return $whole === '' || $whole === '-' ? '0' : $whole;
    }
}
