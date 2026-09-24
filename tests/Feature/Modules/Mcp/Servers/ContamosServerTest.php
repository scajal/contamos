<?php

declare(strict_types=1);

use App\Modules\Mcp\Prompts\ImportStatement;
use App\Modules\Mcp\Prompts\MonthlyReview;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\ListTeams;
use App\Modules\Mcp\Tools\Transactions\ImportTransactions;
use Laravel\Mcp\Server\Prompt;

/**
 * Feature tests for \App\Modules\Mcp\Servers\ContamosServer.
 */

/**
 * The Contamos server looking for its prompts in a folder the
 * module does not have.
 */
class ContamosServerWithoutPromptsFolder extends ContamosServer
{
    protected function boot(): void
    {
        parent::boot();

        $this->prompts = $this->discover('Missing', Prompt::class);
    }
}

it('serves every tool and prompt the module defines, nested folders included', function (): void {
    [$user] = mcpMember();

    ContamosServer::actingAs($user)
        ->tools()
        ->assertRegistered([ListTeams::class, ImportTransactions::class]);

    ContamosServer::actingAs($user)
        ->prompts()
        ->assertRegistered([ImportStatement::class, MonthlyReview::class]);
});

it('serves nothing of a kind whose folder the module lacks', function (): void {
    [$user] = mcpMember();

    ContamosServerWithoutPromptsFolder::actingAs($user)
        ->prompts()
        ->assertNotRegistered([ImportStatement::class, MonthlyReview::class]);

    ContamosServerWithoutPromptsFolder::actingAs($user)
        ->tools()
        ->assertRegistered([ListTeams::class]);
});
