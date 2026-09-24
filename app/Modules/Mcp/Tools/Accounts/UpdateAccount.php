<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Accounts;

use App\Modules\Core\Finance\Actions\Team\UpdateAccount as UpdateAccountAction;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\DefinesAccountRules;
use App\Modules\Core\Finance\Models\Team\Account;
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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Updates an account of the team: its name, type, and
 * currencies, with an optional opening balance for each
 * currency it newly holds.
 */
#[Name('update_account')]
#[Description('Update an account of the team. Any of `name`, `type` and `currencies` left out keeps its current value. `currencies` replaces the whole list, so a currency left out is removed from the account. Opening balances apply only to currencies the update adds. Returns the account with its balances.')]
#[IsDestructive]
class UpdateAccount extends Tool
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
            'account' => $schema->string()
                ->description('The id of the account, as listed by `list_accounts`.')
                ->required(),
            ...$this->accountSchema($schema, required: false),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, UpdateAccountAction $updateAccount): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $updateAccount): ResponseFactory {
            // Only an account of the team can be updated.
            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = $this->findOrFail($team->accounts()->with('currencies'), $request->get('account'), 'account');

            // Validate with the web's account rules.
            $validated = $this->validatedAccount($request, $account);

            // Update the account through the domain Action.
            $account = $updateAccount
                ->for($account)
                ->by($user)
                ->using($validated['attributes'], $validated['currency_ids'], $validated['opening_balances']);

            return Response::structured(['account' => $this->presentAccount($account->load('currencies'))]);
        });
    }
}
