<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Prompts;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * Walks the assistant through importing a bank statement the
 * safe way: parse, dry run, review with the user, then import.
 */
#[Name('import_statement')]
#[Description("Import a pasted or attached bank statement into one of the team's accounts: parse it, dry run, review suspected duplicates with you, then import.")]
class ImportStatement extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $team = $this->text($request->get('team'));
        $account = $this->text($request->get('account'));
        $timezone = $this->text($request->get('timezone'));

        $timezoneLine = $timezone !== ''
            ? "Pass `timezone: \"{$timezone}\"` for the batch."
            : 'Ask me for my timezone if any line carries a time; dates alone need none.';

        return Response::text(<<<MARKDOWN
            Import the bank statement I paste or attach into the account `{$account}` of the Contamos team `{$team}`.

            1. Parse every line of the statement into a row with `type` (`income` for money in, `expense` for money out), a positive `amount` as a decimal string with a dot, `occurred_at` (the date, "YYYY-MM-DD"), a short `description`, and a `category`.
               - Income categories: {$this->categories(TransactionType::Income)}.
               - Expense categories: {$this->categories(TransactionType::Expense)}.
               - Leave out transfers between my own accounts, balances and totals.
            2. Call `import_transactions` with `team: "{$team}"`, `account: "{$account}"`, all the rows and `dry_run: true`. {$timezoneLine} If the account holds several currencies, ask me which one and pass `currency`. Split statements over 200 lines into several calls.
            3. Show me the summary: how many rows, the totals per currency and every suspected duplicate. Fix any row the dry run rejects and try again. Ask me to confirm before importing, and whether suspected duplicates are genuine.
            4. Import by calling `import_transactions` again without `dry_run`, adding `allow_duplicates: true` only if I confirmed the duplicates are genuine, or leaving those rows out otherwise.
            5. Report the `import_id` and tell me `delete_import` can undo the whole import.
            MARKDOWN);
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('team', 'The slug of the team, as listed by `list_teams`.', required: true),
            new Argument('account', 'The id of the account the statement belongs to, as listed by `list_accounts`.', required: true),
            new Argument('timezone', 'Your IANA timezone, such as "America/Montevideo".'),
        ];
    }

    /**
     * List the category values a type offers.
     */
    protected function categories(TransactionType $type): string
    {
        return collect(Category::casesFor($type))
            ->map(fn (Category $category): string => $category->value)
            ->implode(', ');
    }

    /**
     * Get an argument as a string.
     */
    protected function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
