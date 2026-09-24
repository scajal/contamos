<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Reports;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Reports what the team holds in each currency across its
 * active accounts, the same balances the dashboard shows.
 */
#[Name('get_balances')]
#[Description("Get the team's balance in each currency, summed across all its active accounts over every transaction ever recorded (like the dashboard). Amounts are decimal strings in the currency's major unit.")]
#[IsReadOnly]
class GetBalances extends Tool
{
    use InteractsWithTeams;
    use PresentsRecords;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team): ResponseFactory {
            // Balances sum every transaction the team recorded,
            // whatever its plan hides, as the dashboard does.
            $balances = $team->accounts()
                ->isActive()
                ->balancesByCurrency()
                ->get()
                ->map(fn (Account $row): array => [
                    'currency' => $row->getAttribute('code'),
                    'balance' => $this->amount($row->getAttribute('balance'), $this->decimals($row->getAttribute('decimal_places'))),
                ])
                ->values()
                ->all();

            return Response::structured(['balances' => $balances]);
        });
    }

    /**
     * Read the decimal places a driver hands back, which may
     * come as a string.
     */
    protected function decimals(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 2;
    }
}
