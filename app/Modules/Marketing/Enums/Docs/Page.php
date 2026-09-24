<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Enums\Docs;

use App\Modules\Marketing\Objects\Document;
use App\Modules\Mcp\Helpers\AssistantGuide;
use Filament\Support\Contracts\HasLabel;

/**
 * A page of the docs, named by its slug, which is shared
 * by every locale. The order of the cases is the order
 * of the sidebar, the sitemap and llms-full.txt, so
 * adding a page is adding a case here and its Markdown
 * file in each locale.
 */
enum Page: string implements HasLabel
{
    /**
     * What Contamos is, and the index of the docs.
     */
    case Introduction = 'introduction';

    /**
     * From signing up to a first transaction.
     */
    case Quickstart = 'quickstart';

    /**
     * Accounts, the currencies they hold and their balances.
     */
    case AccountsAndCurrencies = 'accounts-and-currencies';

    /**
     * Income, expenses and transfers between accounts.
     */
    case TransactionsAndTransfers = 'transactions-and-transfers';

    /**
     * Labels that follow money across categories.
     */
    case Tags = 'tags';

    /**
     * The exchange rate every transaction keeps.
     */
    case ExchangeRates = 'exchange-rates';

    /**
     * Monthly budgets per category or tag.
     */
    case Budgets = 'budgets';

    /**
     * Rules that record a transaction on a schedule.
     */
    case RecurringTransactions = 'recurring-transactions';

    /**
     * Invitations, roles and leaving a team.
     */
    case MembersAndRoles = 'members-and-roles';

    /**
     * Every change made inside a team.
     */
    case ActivityLog = 'activity-log';

    /**
     * What each plan includes and allows.
     */
    case PlansAndLimits = 'plans-and-limits';

    /**
     * Connecting Claude or ChatGPT to a ledger.
     */
    case AiAssistants = 'ai-assistants';

    /**
     * Get the group the page is listed under.
     */
    public function group(): Group
    {
        return match ($this) {
            self::Introduction, self::Quickstart => Group::GetStarted,
            self::AccountsAndCurrencies, self::TransactionsAndTransfers, self::Tags, self::ExchangeRates => Group::Ledger,
            self::Budgets, self::RecurringTransactions => Group::Planning,
            self::MembersAndRoles, self::ActivityLog => Group::Team,
            self::PlansAndLimits, self::AiAssistants => Group::More,
        };
    }

    /**
     * Get the page's document in the given locale.
     */
    public function document(string $locale): Document
    {
        return Document::find('docs', $this->value, $locale);
    }

    /**
     * Get the page as Markdown in the given locale. The AI
     * assistants page ends with the guide the app's own
     * assistants page shows, which is kept in one place
     * rather than copied into the file.
     */
    public function markdown(string $locale): string
    {
        $markdown = $this->document($locale)->markdown();

        return $this === self::AiAssistants
            ? $markdown."\n".AssistantGuide::markdown($locale)
            : $markdown;
    }

    /**
     * Get the URL of the page in the given locale. The
     * introduction is the index of the docs, so it is
     * reached at the docs' own URL.
     */
    public function url(string $locale): string
    {
        return $this === self::Introduction
            ? route('docs.index', ['locale' => $locale])
            : route('docs.show', ['locale' => $locale, 'page' => $this->value]);
    }

    /**
     * Get the URL of the page's Markdown in the given
     * locale.
     */
    public function markdownUrl(string $locale): string
    {
        return route('docs.markdown', ['locale' => $locale, 'page' => $this->value]);
    }

    /**
     * Get the label the sidebar lists the page by.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Introduction => trans('Introduction'),
            self::Quickstart => trans('Quickstart'),
            self::AccountsAndCurrencies => trans('Accounts & currencies'),
            self::TransactionsAndTransfers => trans('Transactions & transfers'),
            self::Tags => trans('Tags'),
            self::ExchangeRates => trans('Exchange rates'),
            self::Budgets => trans('Budgets'),
            self::RecurringTransactions => trans('Recurring transactions'),
            self::MembersAndRoles => trans('Members & roles'),
            self::ActivityLog => trans('Activity log'),
            self::PlansAndLimits => trans('Plans & limits'),
            self::AiAssistants => trans('AI assistants'),
        };
    }
}
