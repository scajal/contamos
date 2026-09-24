<?php

declare(strict_types=1);

use App\Modules\Mcp\Prompts\ImportStatement;
use App\Modules\Mcp\Prompts\MonthlyReview;
use App\Modules\Mcp\Servers\ContamosServer;

/**
 * Feature tests for \App\Modules\Mcp\Prompts\ImportStatement and
 * \App\Modules\Mcp\Prompts\MonthlyReview.
 */
it('lists both prompts', function (): void {
    [$user] = mcpMember();

    ContamosServer::actingAs($user)
        ->prompts()
        ->assertRegistered([ImportStatement::class, MonthlyReview::class]);
});

it('renders the import statement prompt with its arguments', function (): void {
    [$user] = mcpMember();

    ContamosServer::actingAs($user)
        ->prompt(ImportStatement::class, ['team' => 'home', 'account' => '01ACCOUNT', 'timezone' => 'America/Montevideo'])
        ->assertOk()
        ->assertSee(['`01ACCOUNT`', 'team: "home"', 'dry_run: true', 'timezone: "America/Montevideo"', 'delete_import']);
});

it('asks for the timezone in the import statement prompt when none is given', function (): void {
    [$user] = mcpMember();

    ContamosServer::actingAs($user)
        ->prompt(ImportStatement::class, ['team' => 'home', 'account' => '01ACCOUNT'])
        ->assertOk()
        ->assertSee('Ask me for my timezone if any line carries a time; dates alone need none.')
        ->assertDontSee('Pass `timezone:');
});

it('renders the monthly review prompt with its arguments', function (): void {
    [$user] = mcpMember();

    ContamosServer::actingAs($user)
        ->prompt(MonthlyReview::class, ['team' => 'home', 'month' => '2026-08'])
        ->assertOk()
        ->assertSee(['get_monthly_report', 'month: "2026-08"', 'list_budgets']);
});
