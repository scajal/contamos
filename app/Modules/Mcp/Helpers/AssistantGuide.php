<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Helpers;

use App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken;

/**
 * How to connect an AI assistant to Contamos and what to
 * ask it first: the address of the server, the steps each
 * assistant's settings take, and a handful of example
 * prompts. The in-app AI assistants page and the docs both
 * show it, so the two never disagree.
 */
class AssistantGuide
{
    /**
     * Get the guide in the current locale, as the pages
     * receive it.
     *
     * @return array{server_url: string, guides: array<int, array{name: string, steps: array<int, string>}>, prompts: array<int, array{prompt: string, pro: bool}>}
     */
    public static function toArray(): array
    {
        return [
            'server_url' => AuthenticateMcpToken::resource(),
            'guides' => self::guides(),
            'prompts' => self::prompts(),
        ];
    }

    /**
     * Get the steps per assistant, in the order its
     * settings show them, in the given locale.
     *
     * @return array<int, array{name: string, steps: array<int, string>}>
     */
    public static function guides(?string $locale = null): array
    {
        return [
            [
                'name' => 'Claude',
                'steps' => [
                    trans('Open Settings, then Connectors.', [], $locale),
                    trans('Choose Add custom connector.', [], $locale),
                    trans('Paste the server address and sign in to Contamos when asked.', [], $locale),
                ],
            ],
            [
                'name' => 'ChatGPT',
                'steps' => [
                    trans('Open Settings, then Connectors, and turn on developer mode if asked.', [], $locale),
                    trans('Add a new connector with the server address as its MCP server URL.', [], $locale),
                    trans('Sign in to Contamos when asked.', [], $locale),
                ],
            ],
        ];
    }

    /**
     * Get the example prompts in the given locale, each
     * marked with whether the team needs Pro to run it.
     *
     * @return array<int, array{prompt: string, pro: bool}>
     */
    public static function prompts(?string $locale = null): array
    {
        return [
            ['prompt' => trans('What are the balances of my team, in each currency?', [], $locale), 'pro' => false],
            ['prompt' => trans('Summarize my income and expenses for this month by category.', [], $locale), 'pro' => false],
            ['prompt' => trans('Record an expense of 45 dollars for groceries today, paid from my bank account.', [], $locale), 'pro' => false],
            ['prompt' => trans('Transfer 200 dollars from my bank account to cash today.', [], $locale), 'pro' => false],
            ['prompt' => trans('Here is my bank statement. Import its transactions into my team, and show me a dry run before you import anything.', [], $locale), 'pro' => false],
            ['prompt' => trans('How are my budgets doing this month?', [], $locale), 'pro' => true],
        ];
    }

    /**
     * Get the guide as Markdown in the given locale, for
     * the docs page's own Markdown.
     */
    public static function markdown(?string $locale = null): string
    {
        // The server address, then each assistant's steps
        // as a numbered list under its name.
        $markdown = '## '.trans('How to connect', [], $locale)."\n\n"
            .trans('You sign in once; the assistant then acts as you, with your role on each team.', [], $locale)."\n\n"
            .trans('Server address', [], $locale).': `'.AuthenticateMcpToken::resource()."`\n";

        foreach (self::guides($locale) as $guide) {
            $markdown .= "\n### {$guide['name']}\n\n";

            foreach ($guide['steps'] as $index => $step) {
                $markdown .= ($index + 1).". {$step}\n";
            }
        }

        // The example prompts, the ones that need Pro
        // marked as such.
        $markdown .= "\n## ".trans('Example prompts', [], $locale)."\n\n";

        foreach (self::prompts($locale) as $prompt) {
            $markdown .= "- {$prompt['prompt']}".($prompt['pro'] ? ' (Pro)' : '')."\n";
        }

        return $markdown;
    }
}
