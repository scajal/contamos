<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\DeleteImport;
use App\Modules\Mcp\Tools\Transactions\ImportTransactions;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\DeleteImport.
 */

/**
 * Import two expenses into a fresh account of the team and
 * return the account and the import id.
 *
 * @return array{0: \App\Modules\Core\Finance\Models\Team\Account, 1: string}
 */
function importTwoExpenses(User $user, Team $team): array
{
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach(
        Currency::factory()->active()->fiat()->create(['code' => 'ZZD', 'decimal_places' => 2]),
        ['balance' => '100'],
    );

    $row = [
        'type' => 'expense',
        'category' => Category::casesFor(TransactionType::Expense)[0]->value,
        'quote' => '1',
    ];

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [
                [...$row, 'amount' => '10', 'occurred_at' => '2026-08-14'],
                [...$row, 'amount' => '20', 'occurred_at' => '2026-08-15'],
            ],
        ])
        ->assertOk();

    /** @var string $importId */
    $importId = activitiesOf($team, 'created')->last()?->batch_uuid;

    return [$account, $importId];
}

it('deletes exactly the imported transactions and reverts the balance', function (): void {
    [$user, $team] = mcpMember();
    $unrelated = Transaction::factory()->for($team)->create();
    [$account, $importId] = importTwoExpenses($user, $team);

    ContamosServer::actingAs($user)
        ->tool(DeleteImport::class, ['team' => $team->slug, 'import_id' => $importId])
        ->assertOk()
        ->assertSee('"deleted":2');

    expect(Transaction::query()->pluck('id')->all())->toBe([$unrelated->getKey()])
        ->and(balanceOf($account, $account->currencies()->sole()))->toBe(100.0);
});

it('refuses when an imported transaction was edited since', function (): void {
    [$user, $team] = mcpMember();
    [$account, $importId] = importTwoExpenses($user, $team);
    $edited = $account->transactions()->firstOrFail();
    $edited->update(['description' => 'Corrected by hand']);

    ContamosServer::actingAs($user)
        ->tool(DeleteImport::class, ['team' => $team->slug, 'import_id' => $importId])
        ->assertHasErrors(['were edited after the import: '.$edited->getKey()]);

    expect($account->transactions()->count())->toBe(2);
});

it('does not find an import of another team', function (): void {
    [$user, $team] = mcpMember();
    [$stranger, $strangerTeam] = mcpMember();
    [, $importId] = importTwoExpenses($stranger, $strangerTeam);

    ContamosServer::actingAs($user)
        ->tool(DeleteImport::class, ['team' => $team->slug, 'import_id' => $importId])
        ->assertHasErrors(['Import not found.']);

    expect(Transaction::query()->count())->toBe(2);
});

it('is a destructive tool', function (): void {
    expect(new DeleteImport()->annotations())->toMatchArray(['destructiveHint' => true]);
});
