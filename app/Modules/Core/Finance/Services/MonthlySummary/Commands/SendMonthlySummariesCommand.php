<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Services\MonthlySummary\Commands;

use App\Modules\Core\Finance\Reports\Team\MonthlyReport;
use App\Modules\Core\Finance\Services\MonthlySummary\Actions\SendMonthlySummary;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Pennant\Feature as Pennant;

#[Signature('finance:send-monthly-summaries')]
#[Description('Email every team with activity last month the summary of that month')]
class SendMonthlySummariesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SendMonthlySummary $action): int
    {
        // Plan state is resolved fresh for this run, so a team
        // that changed plans since the last one shows the
        // budgets its plan includes now.
        Pennant::flushCache();

        $month = CarbonImmutable::now()->startOfMonth()->subMonth();
        $sent = 0;

        // Only a team that recorded something in the month has
        // anything to summarize.
        Team::query()
            ->whereHas('transactions', fn (Builder $query): Builder => $query
                ->occurredFrom($month->toDateString())
                ->occurredUntil($month->endOfMonth()->toDateString()))
            ->each(function (Team $team) use ($action, $month, &$sent): void {
                $sent += (int) $action->for($team)->using($month);
            });

        $this->info("Sent the summary of {$month->format(MonthlyReport::MONTH_FORMAT)} to {$sent} teams.");

        return self::SUCCESS;
    }
}
