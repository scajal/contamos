<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transfers;

use App\Modules\Core\Finance\Actions\Team\UpdateTransfer as UpdateTransferAction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\PresentsTransfers;
use App\Modules\Mcp\Tools\Concerns\ResolvesTransferArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Changes a transfer of the team and both of its legs, keeping
 * the account balances correct for whatever changed.
 */
#[Name('update_transfer')]
#[Description('Change a transfer of the team. Pass only what changes; every argument left out keeps its current value. Both legs, their fees and both account balances are adjusted. Pass a fee as null to stop charging it. Exchange rates are kept unless the currency or date changes, then they default to the ones recorded for the new date.')]
#[IsDestructive]
class UpdateTransfer extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;
    use PresentsTransfers;
    use ResolvesTransferArguments;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'transfer' => $schema->string()
                ->description('The id of the transfer to change.')
                ->required(),
            ...$this->transferArgumentsSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, UpdateTransferAction $updateTransfer): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $updateTransfer): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // Only the team's own transfers are found.
            /** @var \App\Modules\Core\Finance\Models\Team\Transfer $transfer */
            $transfer = $this->findOrFail($team->transfers(), $request->get('transfer'), 'transfer');

            // Resolve and validate the arguments like the web form,
            // filling in the current value of each one left out.
            [$attributes, $tagIds] = $this->transferAttributes($request, $team, $transfer);

            // Update the transfer along with the transactions it owns.
            $transfer = $updateTransfer
                ->for($team)
                ->using($transfer, $attributes, $tagIds);

            return Response::structured(['transfer' => $this->presentTransfer($transfer->load('tags', 'user', 'transactions.account', 'transactions.currency'), $timezone)]);
        });
    }
}
