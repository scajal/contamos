<?php

declare(strict_types=1);

namespace App\Modules\Administration\Providers;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Actions;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Components;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use LaravelLang\Locales\Facades\Locales;

abstract class PanelProvider extends \Filament\PanelProvider
{
    /**
     * Bootstrap any panel services.
     */
    public function boot(): void
    {
        $this->configureComponents();
        $this->configurePlugins();
    }

    /**
     * Configure the administration panel components.
     */
    protected function configureComponents(): void
    {
        Actions\ActionGroup::configureUsing(
            fn (Actions\ActionGroup $group): Actions\ActionGroup => $group
                ->icon(Heroicon::EllipsisHorizontal)
                ->iconPosition(IconPosition::After)
                ->color(Color::Zinc)
        );

        Actions\BulkActionGroup::configureUsing(
            fn (Actions\BulkActionGroup $group): Actions\BulkActionGroup => $group
                ->button()
                ->label(trans('Actions'))
                ->icon(Heroicon::OutlinedChevronDown)
                ->iconPosition(IconPosition::After)
        );

        Tables\Table::configureUsing(
            fn (Tables\Table $table): Tables\Table => $table
                ->deferFilters()
                ->deferLoading()
                ->extremePaginationLinks()
                ->filtersFormWidth(Width::FourExtraLarge)
                ->filtersFormColumns(2)
        );

        Tables\Filters\BaseFilter::configureUsing(function (Tables\Filters\BaseFilter $filter): void {
            /** @var string $label */
            $label = $filter->getLabel();

            $filter->indicator(trans($label));
        });

        Components\ViewComponent::configureUsing(function (Components\ViewComponent $component): void {
            if (method_exists($component, 'translateLabel')) {
                $component->translateLabel();
            }
        });
    }

    /**
     * Configure the administration panel plugins.
     */
    protected function configurePlugins(): void
    {
        LanguageSwitch::configureUsing(
            fn (LanguageSwitch $switch): LanguageSwitch => $switch->locales(
                Locales::installed()->pluck('code')->toArray()
            )->userPreferredLocale(fn (): array|string|null => request()->cookie('filament_language_switch_locale'))
        );
    }

    /**
     * Configure the administration panel.
     */
    #[\Override]
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->authMiddleware([\Filament\Http\Middleware\Authenticate::class, \Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS::class])
            ->favicon('favico.ico')
            ->maxContentWidth(Width::Full)
            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Filament\Http\Middleware\AuthenticateSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
                \App\Modules\Support\Http\Middleware\SetLocale::class,
            ])
            ->sidebarFullyCollapsibleOnDesktop()
            ->spa()
            ->unsavedChangesAlerts()
            ->viteTheme('resources/css/administration/theme.css');
    }
}
