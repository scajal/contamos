<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\IndexRequest;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\PresentsTransactions;
use App\Modules\Mcp\Tools\Concerns\ResolvesTransactionArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Lists the team's transactions newest first, filtered the way
 * the web transactions list filters them and cut at the plan's
 * history window.
 */
#[Name('list_transactions')]
#[Description("List the team's transactions, newest first, 50 per page, including transfer legs and opening balances. Filter by type, account, category, currency, tags, date range or a search on the description or id. Amounts are signed the way they move the balance. On a limited plan only the months the plan keeps are listed. Pass `next_cursor` back as `cursor` for the next page.")]
#[IsReadOnly]
class ListTransactions extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;
    use PresentsTransactions;
    use ResolvesTransactionArguments;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'type' => $schema->string()
                ->enum(Transaction::typeFilters())
                ->description('Only this type; "transfer" matches both legs of a transfer.'),
            'account' => $schema->string()
                ->description('Only this account, by id.'),
            'category' => $schema->string()
                ->enum(array_column(Category::cases(), 'value'))
                ->description('Only this category.'),
            'currency' => $schema->string()
                ->description('Only this currency, by ISO code such as "USD".'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Only transactions carrying any of these tags, by id or exact name.'),
            'from' => $schema->string()
                ->description('Only on or after this date, "YYYY-MM-DD".'),
            'to' => $schema->string()
                ->description('Only on or before this date, "YYYY-MM-DD".'),
            'search' => $schema->string()
                ->description('Text found in the description, or an exact transaction id.'),
            ...$this->timezoneSchema($schema),
            ...$this->cursorSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // Build the web listing's request from the arguments, so
            // the same rules and filters apply.
            $filters = $this->listFilters($team, $request);

            $index = IndexRequest::create('/', 'GET', ['filter' => $filters]);

            Validator::make(['filter' => [
                ...$filters,
                ...(isset($filters['tags']) ? ['tags' => explode(',', $filters['tags'])] : []),
            ]], $index->rules($team), [], [
                'filter.account' => 'account',
                'filter.category' => 'category',
                'filter.from' => 'from',
                'filter.search' => 'search',
                'filter.tags.*' => 'tag',
                'filter.to' => 'to',
                'filter.type' => 'type',
            ])->validate();

            // The plan's history window is applied before any
            // filter, so no filter can widen it.
            $transactions = QueryBuilder::for($team->transactions()->withinHistoryWindow($this->historyWindowStart($team)), $index)
                ->allowedFilters(...$index->filters())
                ->getEloquentBuilder()
                ->with('account', 'currency', 'tags', 'user')
                ->orderBy('occurred_at', 'desc')
                ->orderBy(new Transaction()->getKeyName(), 'desc');

            return Response::structured($this->page(
                $transactions,
                $request->get('cursor'),
                fn (Transaction $transaction): array => $this->presentTransaction($transaction, $timezone),
            ));
        });
    }

    /**
     * Turn the arguments into the `filter[...]` values the web
     * listing takes: a currency code into its id and tags by name
     * into a comma-separated list of ids.
     *
     * @return array<string, string>
     */
    protected function listFilters(Team $team, Request $request): array
    {
        $filters = [];

        foreach (['type', 'account', 'category', 'from', 'to', 'search'] as $key) {
            if (is_string($value = $request->get($key)) && Str::trim($value) !== '') {
                $filters[$key] = Str::trim($value);
            }
        }

        // A currency is named by its code.
        if (is_string($code = $request->get('currency')) && Str::trim($code) !== '') {
            $currency = Currency::query()->where('code', Str::upper(Str::trim($code)))->first();

            if (! $currency instanceof Currency) {
                throw new ToolError("Unknown currency \"{$code}\".");
            }

            $filters['currency'] = (string) $currency->getKey();
        }

        // Tags are named by id or name; inactive ones still filter.
        if (is_array($tags = $request->get('tags')) && $tags !== []) {
            $allTagIds = $team->tags()->pluck(new Tag()->getKeyName())->filter(fn (mixed $value): bool => is_string($value))->values()->all();

            $filters['tags'] = implode(',', $this->tagIds($team, $tags, $allTagIds));
        }

        return $filters;
    }
}
