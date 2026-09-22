<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Widgets;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
        return trans('Application');
    }

    /**
     * Get the stats for the widget.
     *
     * @return array<\Filament\Widgets\StatsOverviewWidget\Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        return [
            Stat::make(trans('Teams'), Team::query()->count())
                ->icon(Heroicon::OutlinedUsers)
                ->color(Color::Lime),
            Stat::make(trans('Users'), User::query()->count())
                ->icon(Heroicon::OutlinedUserGroup)
                ->color(Color::Blue),
            Stat::make(trans('Subscriptions'), Subscription::query()->count())
                ->icon(Heroicon::OutlinedArrowPath)
                ->color(Color::Amber),
            Stat::make(trans('Currencies'), Currency::query()->count())
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color(Color::Zinc),
        ];
    }
}
