<?php

declare(strict_types=1);

namespace App\Modules\Support\Helpers;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Support\ActivityLogger;

/**
 * The write side of a team's activity log for the few changes a
 * model event cannot see, and the batches that keep one thing
 * somebody did reading as one entry.
 */
class TeamActivity
{
    /**
     * How many batches are currently open. Only the outermost
     * one owns the identifier.
     */
    protected static int $depth = 0;

    /**
     * The identifier the entries written right now share.
     */
    protected static ?string $uuid = null;

    /**
     * Close the innermost open batch, dropping the shared
     * identifier once the outermost one closes.
     */
    public static function end(): void
    {
        self::$depth = max(0, self::$depth - 1);

        if (self::$depth === 0) {
            self::$uuid = null;
        }
    }

    /**
     * Record an entry about the subject under a stable event key.
     * The team is only needed for a subject that does not carry
     * one, such as the member a membership change is about.
     *
     * @param  array{old?: array<string, mixed>, attributes?: array<string, mixed>}  $changes
     */
    public static function record(Model $subject, string $event, array $changes = [], ?Team $team = null): void
    {
        activity()
            ->performedOn($subject)
            ->event($event)
            ->withChanges($changes)
            ->when($team instanceof Team, fn (ActivityLogger $logger): ActivityLogger => $logger->tap(
                fn (Activity $activity): mixed => $activity->setAttribute($activity->team()->getForeignKeyName(), $team?->getKey()),
            ))
            ->log($event);
    }

    /**
     * Open a batch, keeping the identifier of the one already
     * open so nested actions stay a single entry.
     */
    public static function start(): void
    {
        if (self::$depth === 0) {
            self::$uuid = (string) Str::uuid();
        }

        self::$depth++;
    }

    /**
     * Get the identifier the entries written right now share,
     * or null when no batch is open.
     */
    public static function uuid(): ?string
    {
        return self::$uuid;
    }

    /**
     * Run the callback with a batch open, closing it however
     * the callback ends.
     *
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function within(Closure $callback): mixed
    {
        self::start();

        try {
            return $callback();
        } finally {
            self::end();
        }
    }
}
