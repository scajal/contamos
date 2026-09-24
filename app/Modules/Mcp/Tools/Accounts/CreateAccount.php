<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Accounts;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateAccount as CreateAccountAction;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\DefinesAccountRules;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Tools\Concerns\InteractsWithAccounts;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Creates an account on the team, with its currencies and an
 * optional opening balance in each, within the plan's account
 * limit.
 */
#[Name('create_account')]
#[Description('Create an account on the team: a name, a type (bank, cash or crypto), the ISO codes of the currencies it holds and, optionally, an opening balance per currency. Crypto accounts hold only cryptocurrencies; bank and cash accounts only fiat ones. An opening balance is recorded with the exchange rate of `occurred_at` (today by default) unless `quote` is given. Returns the account with its balances.')]
class CreateAccount extends Tool
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
            ...$this->accountSchema($schema, required: true),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, CreateAccountAction $createAccount): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createAccount): ResponseFactory {
            // The plan's account limit, as on the web.
            $this->ensureFeature($team, Feature::AddAccounts);

            // Validate with the web's account rules.
            $validated = $this->validatedAccount($request);

            // Create the account through the domain Action.
            $account = $createAccount
                ->for($team)
                ->by($user)
                ->using($validated['attributes'], $validated['currency_ids'], $validated['opening_balances']);

            // Reload it, so the columns the database defaults,
            // such as whether it is active, are filled in.
            return Response::structured(['account' => $this->presentAccount($account->refresh()->load('currencies'))]);
        });
    }
}
