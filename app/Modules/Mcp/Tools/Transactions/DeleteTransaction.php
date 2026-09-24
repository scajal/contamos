<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Finance\Actions\Team\DeleteTransaction as DeleteTransactionAction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\ResolvesTransactionArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Deletes a transaction of the team and reverts its effect on
 * the account balance.
 */
#[Name('delete_transaction')]
#[Description('Delete a transaction of the team and revert its effect on the account balance. A transaction that is part of a transfer cannot be deleted here; delete the transfer instead.')]
#[IsDestructive]
class DeleteTransaction extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;
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
                ->description('The id of the transaction to delete.')
                ->required(),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, DeleteTransactionAction $deleteTransaction): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $deleteTransaction): ResponseFactory {
            // A row the plan hides reads as not found, and a
            // transfer's row is only deleted with its transfer.
            $transaction = $this->writableTransaction($team, $request->get('transaction'));

            // Delete the transaction and revert its balance effect.
            $deleteTransaction
                ->for($team)
                ->using($transaction);

            return Response::structured(['deleted' => true, 'id' => $transaction->getKey()]);
        });
    }
}
