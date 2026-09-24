<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Servers;

use App\Modules\Mcp\Helpers\McpContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * The Contamos MCP server. AI assistants reach it over HTTP at
 * /mcp and act as the signed-in user, on the teams that user
 * belongs to, with the same permissions the web gives them.
 */
#[Name('Contamos')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Contamos is a shared ledger for any group that shares money. You act as the signed-in user.

    - Every team-scoped tool takes a required `team` argument: the team's slug from `list_teams`. Always pass it; never assume a team. Ask the user when more than one could be meant.
    - Each team's plan applies as it does in the app: a Free team sees a limited history and has caps on accounts and members, and budgets, recurring transactions and the activity log need Pro. When a limit is hit, the error carries an upgrade link; share it with the user rather than retrying.
    - Amounts are exact decimal strings in major units, such as "1250.50". Never send floats.
    - Always pass an IANA `timezone` (for example "America/Montevideo") whenever you give a time. A date on its own is fine without one.
    - Never invent exchange rates. Leave `quote` out to use the rate recorded for the date, and ask the user when none exists.
    - When an account holds several currencies and the user did not say which, ask instead of guessing.
    - Before importing a bank statement, run `import_transactions` with `dry_run: true`, show the user the summary and any suspected duplicates, and only then import.
    - Destructive tools change or delete the user's records; confirm with the user before calling them.
    - Notification preferences (which emails a person gets about a team) cannot be changed here; send the user to the Notifications tab of the team's settings in the app.
    MARKDOWN)]
class ContamosServer extends Server
{
    /**
     * Register every tool and prompt the module defines, so a new
     * one is served as soon as its class exists.
     */
    protected function boot(): void
    {
        // Mark the request as an MCP call, so what it writes to the
        // activity log says it came from an assistant.
        McpContext::enter();

        $this->tools = $this->discover('Tools', Tool::class);
        $this->prompts = $this->discover('Prompts', Prompt::class);
    }

    /**
     * Find the concrete classes of the given kind in a folder of
     * the module, sorted by name so the listing is stable.
     *
     * @template TPrimitive of object
     *
     * @param  class-string<TPrimitive>  $kind
     * @return array<int, class-string<TPrimitive>>
     */
    protected function discover(string $folder, string $kind): array
    {
        $directory = app_path('Modules/Mcp/'.$folder);

        // A module without the folder has nothing of that kind.
        if (! File::isDirectory($directory)) {
            return [];
        }

        /** @var array<int, class-string<TPrimitive>> $classes */
        $classes = collect(File::allFiles($directory))
            ->map(fn (\SplFileInfo $file): string => 'App\\Modules\\Mcp\\'.$folder.'\\'.Str::of($file->getPathname())
                ->after($directory.DIRECTORY_SEPARATOR)
                ->beforeLast('.php')
                ->replace(DIRECTORY_SEPARATOR, '\\'))
            ->filter(fn (string $class): bool => class_exists($class)
                && is_subclass_of($class, $kind)
                && ! new ReflectionClass($class)->isAbstract())
            ->sort()
            ->values()
            ->all();

        return $classes;
    }
}
