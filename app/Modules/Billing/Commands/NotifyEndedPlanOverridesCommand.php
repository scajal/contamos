<?php

declare(strict_types=1);

namespace App\Modules\Billing\Commands;

use App\Modules\Billing\Actions\Team\NotifyProEnded;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:notify-ended-plan-overrides')]
#[Description('Email the Owner of every team whose Pro plan granted by hand has ended')]
class NotifyEndedPlanOverridesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotifyProEnded $action): int
    {
        $now = CarbonImmutable::now();
        $notified = 0;

        // A grant only lapses when somebody reads the plan, so
        // look for the Pro grants past their expiry whose end
        // nobody dealt with yet. Walking by key keeps the rows
        // this run claims from shifting the pages it reads.
        Team::query()
            ->where('plan_override', Plan::Pro)
            ->where('plan_override_expires_at', '<=', $now)
            ->whereNull('plan_override_end_handled_at')
            ->eachById(function (Team $team) use ($action, $now, &$notified): void {
                // Claim the end in one conditional write, so an
                // overlapping run never emails the Owner twice.
                $claimed = Team::query()
                    ->whereKey($team->getKey())
                    ->whereNull('plan_override_end_handled_at')
                    ->update(['plan_override_end_handled_at' => $now]);

                // The end is handled either way: a team still on
                // Pro through Paddle hears about the drop from
                // the webhook when that subscription ends.
                if ($claimed === 1 && $action->using($team)) {
                    $notified++;
                }
            });

        $this->info("Notified {$notified} team owners.");

        return self::SUCCESS;
    }
}
