<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Shapes what MCP tools return: amounts as exact decimal strings
 * with the currency's decimal places, instants in UTC plus the
 * user's local time when a timezone was given, and lists as
 * cursor pages, newest first.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait PresentsRecords
{
    /**
     * How many records a page of a list holds.
     */
    protected int $perPage = 50;

    /**
     * Get the schema of the `cursor` argument lists take.
     *
     * @return array{cursor: \Illuminate\JsonSchema\Types\Type}
     */
    protected function cursorSchema(JsonSchema $schema): array
    {
        return [
            'cursor' => $schema->string()
                ->description('The `next_cursor` of the previous page, to fetch the next one. Leave out for the first page.'),
        ];
    }

    /**
     * Present an amount as a decimal string trimmed to the given
     * decimal places, never as a float. Digits past them are cut,
     * not rounded, the way the web shows them.
     */
    protected function amount(mixed $amount, int $decimals): string
    {
        $amount = Str::trim((string) (is_scalar($amount) ? $amount : '0'));

        $negative = Str::startsWith($amount, '-');

        [$integer, $fraction] = array_pad(explode('.', Str::replaceStart('-', '', $amount)), 2, '');

        $integer = Str::ltrim($integer, '0') === '' ? '0' : Str::ltrim($integer, '0');

        $formatted = $decimals === 0
            ? $integer
            : $integer.'.'.Str::padRight(Str::substr($fraction, 0, $decimals), $decimals, '0');

        // A zero is never signed.
        return $negative && Str::trim($formatted, '0.') !== '' ? "-{$formatted}" : $formatted;
    }

    /**
     * Present an instant in UTC under the key, adding the user's
     * local time under `{key}_local` when a timezone was given.
     *
     * @return array<string, string|null>
     */
    protected function instant(string $key, ?CarbonImmutable $at, ?string $timezone): array
    {
        $presented = [$key => $at?->utc()->toIso8601String()];

        if (Instant::isZone($timezone)) {
            $presented["{$key}_local"] = $at?->setTimezone($timezone)->toIso8601String();
        }

        return $presented;
    }

    /**
     * Get one page of the query, which must already be ordered
     * newest first, with each record presented and the cursor of
     * the next page, or null on the last one.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>|\Illuminate\Database\Eloquent\Relations\Relation<TModel, *, *>  $query
     * @param  \Closure(TModel): array<string, mixed>  $present
     * @return array{items: array<int, array<string, mixed>>, next_cursor: string|null}
     */
    protected function page(Builder|Relation $query, mixed $cursor, Closure $present): array
    {
        // A relation pages through the query it wraps.
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        $page = $builder->cursorPaginate($this->perPage, ['*'], 'cursor', is_string($cursor) && $cursor !== '' ? $cursor : null);

        return [
            'items' => array_values(array_map($present, $page->items())),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }
}
