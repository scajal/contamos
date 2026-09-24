<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Finance\Actions\Team\UpdateTransaction as UpdateTransactionAction;
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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Changes a transaction of the team, keeping the account
 * balances correct for whatever changed.
 */
#[Name('update_transaction')]
#[Description('Change an income or expense of the team. Pass only what changes; every argument left out keeps its current value. Balances are adjusted. Pass `tags` to replace the tag set. The exchange rate is kept unless the currency or date changes, then it defaults to the one recorded for the new date. A transaction that is part of a transfer is changed through `update_transfer` instead.')]
#[IsDestructive]
class UpdateTransaction extends Tool
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
                ->description('The id of the transaction to change.')
                ->required(),
            ...$this->transactionArgumentsSchema($schema, required: false),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, UpdateTransactionAction $updateTransaction): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $updateTransaction): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // A row the plan hides reads as not found, and a
            // transfer's row is only changed with its transfer.
            $transaction = $this->writableTransaction($team, $request->get('transaction'));

            // Resolve and validate the arguments like the web form,
            // filling in the current value of each one left out.
            [$attributes, $tagIds] = $this->transactionAttributes($team, $request->all(), $transaction);

            // Update the transaction and its balance effect.
            $transaction = $updateTransaction
                ->for($team)
                ->using($transaction, $attributes, $tagIds);

            return Response::structured(['transaction' => $this->presentTransaction($transaction->load('account', 'currency', 'tags', 'user'), $timezone)]);
        });
    }
}
