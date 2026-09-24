<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Accounts;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\DefinesAccountRules;
use App\Modules\Core\Identity\Models\Team;
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
 * Activates or deactivates an account of the team. Accounts are
 * never deleted; a deactivated one keeps its history.
 */
#[Name('set_account_active')]
#[Description("Activate (`active: true`) or deactivate (`active: false`) an account of the team. Accounts cannot be deleted; deactivating hides one while keeping its history. Activating counts toward the plan's account limit. Returns the account with its balances.")]
#[IsDestructive]
class SetAccountActive extends Tool
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
            'active' => $schema->boolean()
                ->description('Whether the account should be active.')
                ->required(),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            $request->validate(['active' => ['required', 'boolean']]);

            // Only an account of the team can be toggled.
            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = $this->findOrFail($team->accounts(), $request->get('account'), 'account');

            if ($request->boolean('active')) {
                // Activating is gated by the plan's account
                // limit, as on the web.
                $this->ensureFeature($team, Feature::AddAccounts);

                $account->activate();
            } else {
                // Deactivating is always allowed.
                $account->deactivate();
            }

            return Response::structured(['account' => $this->presentAccount($account->load('currencies'))]);
        });
    }
}
