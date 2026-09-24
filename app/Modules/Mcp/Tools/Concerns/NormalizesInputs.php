<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns what an assistant sends into what the domain Actions and
 * the web validation rules expect: local dates and times into UTC,
 * currency codes into the account's currency, tag names into ids,
 * and a missing exchange rate into the one recorded for the date.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait NormalizesInputs
{
    /**
     * Get the schema of the `timezone` argument.
     *
     * @return array{timezone: \Illuminate\JsonSchema\Types\Type}
     */
    protected function timezoneSchema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('The IANA timezone the user is in, such as "America/Montevideo". Required whenever a time is given; also adds local times to the result.'),
        ];
    }

    /**
     * Get the schema of an `occurred_at` argument.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function occurredAtSchema(JsonSchema $schema, string $attribute = 'occurred_at'): array
    {
        return [
            $attribute => $schema->string()
                ->description('When it happened: a date ("2026-08-14") or a local date and time ("2026-08-14 18:30"). A time requires `timezone`; a date alone is recorded at noon.'),
        ];
    }

    /**
     * Get the validated timezone of the call, or null when none
     * was given.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function timezone(mixed $timezone, string $attribute = 'timezone'): ?string
    {
        // A timezone is optional.
        if ($timezone === null || $timezone === '') {
            return null;
        }

        if (! Instant::isZone($timezone)) {
            throw ValidationException::withMessages([$attribute => 'The timezone must be an IANA identifier, such as "America/Montevideo".']);
        }

        return $timezone;
    }

    /**
     * Turn a local date or date and time into the UTC instant it
     * is stored at. A time needs the timezone it was read in; a
     * date alone lands at noon there, or at noon UTC, so it stays
     * on the same calendar day wherever the user is.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function occurredAt(mixed $value, mixed $timezone, string $attribute = 'occurred_at'): string
    {
        $zone = $this->timezone($timezone);

        // Only a date, or a date and a time, is accepted.
        if (! is_string($value) || preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}(?::\d{2})?))?$/', Str::trim($value), $matches) !== 1) {
            throw ValidationException::withMessages([$attribute => 'The date must be "YYYY-MM-DD" or "YYYY-MM-DD HH:MM".']);
        }

        // A time is only meaningful in the timezone it was read in.
        if (isset($matches[2]) && $zone === null) {
            throw ValidationException::withMessages([$attribute => 'A time was given without a timezone. Pass `timezone` too, or send the date alone.']);
        }

        // Parse the local value, then store it as UTC.
        $local = isset($matches[2]) ? "{$matches[1]} {$matches[2]}" : "{$matches[1]} 12:00:00";

        if (($parsed = rescue(fn (): CarbonImmutable => CarbonImmutable::parse($local, $zone ?? 'UTC'), report: false)) === null
            || $parsed->toDateString() !== $matches[1]) {
            throw ValidationException::withMessages([$attribute => 'The date is not a valid calendar date.']);
        }

        return $parsed->utc()->toIso8601String();
    }

    /**
     * Resolve a currency of the account from its ISO code. With no
     * code the account's only currency is used; an account holding
     * several needs the code, and the error lists the choices.
     */
    protected function accountCurrency(Account $account, mixed $code, string $attribute = 'currency'): Currency
    {
        $currencies = $account->currencies()->orderBy('code')->get();

        $codes = $currencies->pluck('code')->implode(', ');

        // Without a code, only an account with one currency
        // leaves no doubt.
        if ($code === null || $code === '') {
            if ($currencies->count() === 1 && ($currency = $currencies->first()) instanceof Currency) {
                return $currency;
            }

            throw new ToolError("The account \"{$account->name}\" holds several currencies ({$codes}). Ask the user which one and pass `{$attribute}`.");
        }

        // A code must name one of the account's currencies.
        $currency = is_string($code)
            ? $currencies->first(fn (Currency $currency): bool => Str::upper($currency->code) === Str::upper(Str::trim($code)))
            : null;

        if (! $currency instanceof Currency) {
            throw new ToolError("The account \"{$account->name}\" does not hold ".(is_string($code) ? $code : 'that currency').". Its currencies are: {$codes}.");
        }

        return $currency;
    }

    /**
     * Resolve tags given by id or by exact name, ignoring case, to
     * the ids of the team's active tags. A tag that is unknown or
     * inactive is refused by name; tags are never created. Tags
     * already on the record being edited stay allowed even if they
     * have since been deactivated.
     *
     * @param  array<int, string>  $attachedTagIds
     * @return array<int, string>
     */
    protected function tagIds(Team $team, mixed $references, array $attachedTagIds = []): array
    {
        // No tags is a valid answer.
        if ($references === null) {
            return [];
        }

        if (! is_array($references)) {
            throw ValidationException::withMessages(['tags' => 'The tags must be a list of tag ids or names.']);
        }

        $tags = $team->tags()->get();

        $ids = [];

        foreach ($references as $reference) {
            // Match by id first, then by name, ignoring case.
            $tag = is_string($reference)
                ? ($tags->firstWhere(new Tag()->getKeyName(), $reference)
                    ?? $tags->first(fn (Tag $tag): bool => Str::lower($tag->name) === Str::lower(Str::trim($reference))))
                : null;

            if (! $tag instanceof Tag) {
                throw new ToolError('Unknown tag "'.(is_scalar($reference) ? $reference : '?').'". Call `list_tags` to see the team\'s tags; tags are never created automatically.');
            }

            if (! $tag->is_active && ! in_array($tag->getKey(), $attachedTagIds, true)) {
                throw new ToolError("The tag \"{$tag->name}\" is inactive. Reactivate it first or leave it out.");
            }

            $ids[] = $tag->getKey();
        }

        return array_values(array_unique(array_filter($ids, is_string(...))));
    }

    /**
     * Resolve the exchange rate the movement is recorded with: the
     * one given, or the rate recorded for the currency as of the
     * moment it happened. There is never a guess.
     */
    protected function quote(Currency $currency, string $occurredAt, mixed $quote): string
    {
        // A rate given explicitly wins.
        if (is_string($quote) && Str::trim($quote) !== '') {
            return Str::trim($quote);
        }

        if (is_int($quote) || is_float($quote)) {
            return (string) $quote;
        }

        // Otherwise, the rate as of when it happened.
        if (($rate = Instant::resolveQuote($currency, $occurredAt)?->rate) === null) {
            throw new ToolError("No exchange rate for {$currency->code} on ".CarbonImmutable::parse($occurredAt)->toDateString().'; pass `quote` explicitly.');
        }

        return (string) $rate;
    }
}
