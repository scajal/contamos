<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transfers;

use App\Modules\Core\Finance\Models\Team\Transfer;
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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Shows one transfer of the team with the leg leaving the
 * origin account, the leg arriving at the destination account,
 * and the fee charged on each side.
 */
#[Name('get_transfer')]
#[Description("Get one transfer between two of the team's accounts: its date, description and tags, and for each side (origin and destination) the account, currency, amount, exchange rate and fee.")]
#[IsReadOnly]
class GetTransfer extends Tool
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
                ->description('The id of the transfer.')
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

            // Only the team's own transfers are found.
            /** @var \App\Modules\Core\Finance\Models\Team\Transfer $transfer */
            $transfer = $this->findOrFail($team->transfers(), $request->get('transfer'), 'transfer');

            return Response::structured(['transfer' => $this->presentTransfer($transfer, $timezone)]);
        });
    }
}
