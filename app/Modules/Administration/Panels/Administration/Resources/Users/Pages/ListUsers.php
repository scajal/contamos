<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Users\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Users\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Get the resource's header actions.
     *
     * @return array<\Filament\Actions\Action>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('workOs')
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->url('https://dashboard.workos.com/')
                ->iconPosition(IconPosition::After)
                ->color(Color::Indigo)
                ->openUrlInNewTab()
                ->label(new HtmlString('<div class="flex items-center gap-2"><img class="rounded-full size-4" src="https://cdn.prod.website-files.com/621f54116cab10f6e9215d8b/6a44e9f137f7ee438717c16d_favicon%202.ico" /><span>WorkOS</span></div>')),
        ];
    }
}
