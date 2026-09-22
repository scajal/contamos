<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Widgets;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Get the heading of the widget.
     */
    #[\Override]
    protected function getHeading(): ?string
    {
        return trans('Finance');
    }

    /**
     * Get the stats for the widget.
     *
     * @return array<\Filament\Widgets\StatsOverviewWidget\Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        return [
            Stat::make(trans('Accounts'), $team->accounts()->count())
                ->icon(Heroicon::OutlinedWallet)
                ->color(Color::Lime),
            Stat::make(trans('Transactions'), $team->transactions()->count())
                ->icon(Heroicon::OutlinedBanknotes)
                ->color(Color::Blue),
            Stat::make(trans('Tags'), $team->tags()->count())
                ->icon(Heroicon::OutlinedTag)
                ->color(Color::Amber),
            Stat::make(trans('Members'), $team->users()->count())
                ->icon(Heroicon::OutlinedUserGroup)
                ->color(Color::Zinc),
        ];
    }
}
