<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\PresentsTransactions;
use App\Modules\Mcp\Tools\Concerns\ResolvesTransactionArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Shows one transaction of the team, as long as the team's plan
 * still shows it.
 */
#[Name('get_transaction')]
#[Description('Get one transaction of the team by its id: its signed amount, currency, account, category, date, tags, and the transfer or recurring rule it belongs to, if any.')]
#[IsReadOnly]
class GetTransaction extends Tool
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
            'transaction' => $schema->string()
                ->description('The id of the transaction.')
                ->required(),
            ...$this->timezoneSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // Only the team's own transactions inside the plan's
            // history window are found.
            $transaction = $this->visibleTransaction($team, $request->get('transaction'));

            return Response::structured(['transaction' => $this->presentTransaction($transaction, $timezone)]);
        });
    }
}
