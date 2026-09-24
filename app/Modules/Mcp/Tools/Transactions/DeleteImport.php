<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Finance\Actions\Team\DeleteTransaction;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Support\Helpers\TeamActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Undoes an import: deletes exactly the transactions one
 * `import_transactions` call created, reverting their balances,
 * unless somebody edited one of them since.
 */
#[Name('delete_import')]
#[Description('Undo an import made with `import_transactions`: delete exactly the transactions it created and revert their balances, all at once. Refused when any of them was edited since the import, so corrections a person made are never wiped; the error lists them. Confirm with the user before calling.')]
#[IsDestructive]
class DeleteImport extends Tool
{
    use InteractsWithTeams;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'import_id' => $schema->string()
                ->description('The `import_id` returned by `import_transactions`.')
                ->required(),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, DeleteTransaction $deleteTransaction): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $deleteTransaction): ResponseFactory {
            $importId = $request->get('import_id');

            // The entries the import wrote when it created its
            // transactions, within this team only.
            $created = is_string($importId) && $importId !== ''
                ? $team->activities()
                    ->where('batch_uuid', $importId)
                    ->where('subject_type', new Transaction()->getMorphClass())
                    ->where('event', 'created')
                    ->get()
                : collect();

            if ($created->isEmpty()) {
                throw new ToolError('Import not found.');
            }

            $ids = $created->pluck('subject_id')->filter(fn (mixed $value): bool => is_string($value))->values()->all();

            // A transaction changed after the import was edited by
            // somebody, and deleting it would wipe that work.
            $edited = $team->activities()
                ->where('subject_type', new Transaction()->getMorphClass())
                ->whereIn('subject_id', $ids)
                ->where('event', 'updated')
                ->where(new Activity()->getKeyName(), '>', $created->max(new Activity()->getKeyName()))
                ->pluck('subject_id')
                ->filter(fn (mixed $value): bool => is_string($value))
                ->unique()
                ->values()
                ->all();

            if ($edited !== []) {
                throw new ToolError('Nothing was deleted: these transactions were edited after the import: '.implode(', ', $edited).'. Delete them one by one with `delete_transaction` if the user wants them gone.');
            }

            // Delete what is left of the import at once, reverting
            // the balances, as a single entry of the activity log.
            $transactions = $team->transactions()->whereKey($ids)->get();

            TeamActivity::within(function () use ($team, $transactions, $deleteTransaction): void {
                DB::transaction(function () use ($team, $transactions, $deleteTransaction): void {
                    foreach ($transactions as $transaction) {
                        $deleteTransaction->for($team)->using($transaction);
                    }
                });
            });

            return Response::structured([
                'import_id' => $importId,
                'deleted' => $transactions->count(),
            ]);
        });
    }
}
