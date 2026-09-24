<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Accounts;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\DefinesAccountRules;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithAccounts;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists the team's accounts with the balance each holds per
 * currency, so the assistant can pick the account and currency
 * a movement belongs to.
 */
#[Name('list_accounts')]
#[Description("List the team's accounts, newest first, 50 per page. Each account has its id, name, type (bank, cash or crypto), whether it is active, and every currency it holds with its current balance as a decimal string. Use it to choose the `account` and `currency` of other tools.")]
#[IsReadOnly]
class ListAccounts extends Tool
{
    use DefinesAccountRules;
    use InteractsWithAccounts;
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;

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
                ->enum(array_column(AccountType::cases(), 'value'))
                ->description('Only accounts of this type.'),
            'currency' => $schema->string()
                ->description('Only accounts holding this currency, by ISO code, such as "USD".'),
            'search' => $schema->string()
                ->description('Only accounts whose name contains this text.'),
            'is_active' => $schema->boolean()
                ->description('Only active (true) or only deactivated (false) accounts. Leave out for both.'),
            ...$this->cursorSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            // Currency codes are matched in upper case.
            if (is_string($code = $request->get('currency'))) {
                $request->merge(['currency' => Str::upper(Str::trim($code))]);
            }

            // Validate the filters the way the web listing does.
            $filters = $request->validate([
                'currency' => ['sometimes', 'nullable', 'string', Rule::exists(Currency::class, 'code')],
                'is_active' => ['sometimes', 'nullable', 'boolean'],
                'search' => ['sometimes', 'nullable', 'string', 'max:100'],
                'type' => ['sometimes', 'nullable', 'string', Rule::enum(AccountType::class)],
            ]);

            $type = is_string($filters['type'] ?? null) ? $filters['type'] : null;
            $search = is_string($filters['search'] ?? null) ? Str::trim($filters['search']) : '';
            $currency = is_string($filters['currency'] ?? null) ? $filters['currency'] : null;
            $isActive = isset($filters['is_active']) ? filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN) : null;

            // Apply each filter given, newest accounts first.
            $query = $team->accounts()
                ->with('currencies')
                ->when($type !== null, fn (Builder $query): Builder => $query->where('type', $type))
                ->when($search !== '', fn (Builder $query): Builder => $query->whereLike('name', "%{$search}%"))
                ->when($isActive !== null, fn (Builder $query): Builder => $query->isActive((bool) $isActive))
                ->when($currency !== null, fn (Builder $query): Builder => $query->whereHas('currencies', fn (Builder $currencies): Builder => $currencies->where('code', $currency)))
                ->orderByDesc(new Account()->qualifyColumn('created_at'))
                ->orderByDesc(new Account()->getQualifiedKeyName());

            return Response::structured(
                $this->page($query, $request->get('cursor'), fn (Account $account): array => $this->presentAccount($account)),
            );
        });
    }
}
