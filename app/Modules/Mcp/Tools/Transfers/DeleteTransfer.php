<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transfers;

use App\Modules\Core\Finance\Actions\Team\DeleteTransfer as DeleteTransferAction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\PresentsTransfers;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Deletes a transfer of the team with both of its legs and its
 * fees, reverting their effect on the account balances.
 */
#[Name('delete_transfer')]
#[Description('Delete a transfer of the team together with both legs and any fee, restoring both account balances. This cannot be undone; confirm with the user first.')]
#[IsDestructive]
class DeleteTransfer extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;
    use PresentsTransfers;

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
                ->description('The id of the transfer to delete.')
                ->required(),
            ...$this->timezoneSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, DeleteTransferAction $deleteTransfer): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $deleteTransfer): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // Only the team's own transfers are found.
            /** @var \App\Modules\Core\Finance\Models\Team\Transfer $transfer */
            $transfer = $this->findOrFail($team->transfers(), $request->get('transfer'), 'transfer');

            // Present it before it is gone.
            $presented = $this->presentTransfer($transfer, $timezone);

            // Delete the transfer and revert the balance effect of
            // every transaction it owns.
            $deleteTransfer
                ->for($team)
                ->using($transfer);

            return Response::structured(['deleted' => true, 'transfer' => $presented]);
        });
    }
}
