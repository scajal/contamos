<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Objects;

use App\Modules\Marketing\Enums\Launch\Phase;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The Product Hunt launch, as the homepage announces it:
 * ahead of the day, on the day, and while its discount
 * code lasts. Once the code expires there is nothing left
 * to announce.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, string|null>
 */
class Launch implements Arrayable
{
    /**
     * The launch's page on Product Hunt.
     */
    public const string URL = 'https://www.producthunt.com/products/contamos';

    /**
     * The discount code the launch offers at checkout.
     */
    public const string CODE = 'PRODUCTHUNT';

    /**
     * When the launch goes live. Product Hunt's day starts
     * at 12:01 AM Pacific.
     */
    public const string STARTS_AT = '2026-10-24 00:01:00 America/Los_Angeles';

    /**
     * When Product Hunt's launch day ends.
     */
    public const string LIVE_UNTIL = '2026-10-25 00:00:00 America/Los_Angeles';

    /**
     * When the discount code stops working. Read in UTC, the
     * earliest reading of "the 31st at 11:59 PM", so the page
     * never offers a code Paddle already refuses.
     */
    public const string OFFER_UNTIL = '2026-10-31 23:59:00 UTC';

    /**
     * Create a new instance of the class.
     */
    public function __construct(
        public Phase $phase,
    ) {}

    /**
     * Get the launch as it stands at the given moment, or
     * null once there is nothing left to announce.
     */
    public static function at(CarbonImmutable $now): ?self
    {
        return match (true) {
            $now->lt(CarbonImmutable::parse(self::STARTS_AT)) => new self(Phase::Upcoming),
            $now->lt(CarbonImmutable::parse(self::LIVE_UNTIL)) => new self(Phase::Live),
            $now->lt(CarbonImmutable::parse(self::OFFER_UNTIL)) => new self(Phase::Offer),
            default => null,
        };
    }

    /**
     * Get the instance as an array. The code is left out
     * until the launch is live, so nobody uses it early.
     *
     * @return array{phase: string, url: string, code: string|null}
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase->value,
            'url' => self::URL,
            'code' => $this->phase === Phase::Upcoming ? null : self::CODE,
        ];
    }
}
