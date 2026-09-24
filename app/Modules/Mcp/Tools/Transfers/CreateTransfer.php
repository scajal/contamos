<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transfers;

use App\Modules\Core\Finance\Actions\Team\CreateTransfer as CreateTransferAction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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

/**
 * Records a transfer between two of the team's accounts, with
 * the leg leaving the origin, the leg arriving at the
 * destination, and any fee, the way the web form does.
 */
#[Name('create_transfer')]
#[Description('Record a transfer of money between two different accounts of the team, updating both balances. Give the amount sent (and, across currencies, the amount received); currencies are inferred when an account holds one; exchange rates default to the ones recorded for the date. An optional fee on either side is recorded as an expense on that account.')]
class CreateTransfer extends Tool
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
        $arguments = $this->transferArgumentsSchema($schema);

        // The sides, the amount sent and the date are required.
        foreach (['origin_account', 'destination_account', 'amount_sent', 'occurred_at'] as $key) {
            $arguments[$key]->required();
        }

        return [
            ...$this->teamSchema($schema),
            ...$arguments,
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, CreateTransferAction $createTransfer): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createTransfer): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // Resolve and validate the arguments like the web form.
            [$attributes, $tagIds] = $this->transferAttributes($request, $team);

            // Create the transfer along with the transactions it owns.
            $transfer = $createTransfer
                ->for($team)
                ->by($user)
                ->using($attributes, $tagIds);

            return Response::structured(['transfer' => $this->presentTransfer($transfer, $timezone)]);
        });
    }
}
