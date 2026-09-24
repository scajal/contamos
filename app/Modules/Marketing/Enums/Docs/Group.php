<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Enums\Docs;

use Filament\Support\Contracts\HasLabel;

/**
 * A heading of the docs sidebar, in the order the
 * sidebar lists them.
 */
enum Group: string implements HasLabel
{
    /**
     * What Contamos is and how to begin.
     */
    case GetStarted = 'get-started';

    /**
     * How the ledger records money.
     */
    case Ledger = 'ledger';

    /**
     * Budgets and the transactions that repeat.
     */
    case Planning = 'planning';

    /**
     * The people a ledger is shared with.
     */
    case Team = 'team';

    /**
     * Everything that fits no other heading.
     */
    case More = 'more';

    /**
     * Get the pages under the group, in sidebar order.
     *
     * @return array<int, \App\Modules\Marketing\Enums\Docs\Page>
     */
    public function pages(): array
    {
        return array_values(array_filter(
            Page::cases(),
            fn (Page $page): bool => $page->group() === $this,
        ));
    }

    /**
     * Get the label for the group.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::GetStarted => trans('Get started'),
            self::Ledger => trans('Ledger'),
            self::Planning => trans('Planning'),
            self::Team => trans('Team'),
            self::More => trans('More'),
        };
    }
}
