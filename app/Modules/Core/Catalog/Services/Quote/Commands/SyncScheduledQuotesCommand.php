<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Services\Quote\Commands;

use App\Modules\Core\Catalog\Services\Quote\Actions\Currency\CreateScheduledQuotes;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

#[Signature('catalog:sync-scheduled-quotes {--date= : Quote date in Y-m-d}')]
#[Description('Sync scheduled currency quotes from registered quote providers')]
class SyncScheduledQuotesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateScheduledQuotes $action): int
    {
        try {
            // Resolve the quote date.
            $date = $this->quoteDate();

            // Create the scheduled quotes.
            $created = $action->using($date);
        } catch (Throwable $throwable) {
            // Report the error.
            report($throwable);

            // Report the error to the user.
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        // Report the success to the user.
        $this->info("Created {$created} scheduled quotes for {$date->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * Resolve the quote date.
     */
    private function quoteDate(): CarbonImmutable
    {
        if (! ($value = $this->option('date')) || $value === '') {
            return CarbonImmutable::now();
        }

        // Parse the date and return it.
        try {
            return CarbonImmutable::parse($value);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException("The date [{$value}] is invalid.", $invalidArgumentException->getCode(), previous: $invalidArgumentException);
        }
    }
}
