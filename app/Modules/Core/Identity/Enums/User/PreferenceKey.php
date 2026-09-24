<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Enums\User;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Cookie as CookieJar;
use LaravelLang\Locales\Facades\Locales;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The choices a member makes about how the app looks and
 * reads, stored on their account so they follow them across
 * devices. Each one mirrors an unencrypted cookie the browser
 * and the marketing pages read.
 */
enum PreferenceKey: string implements HasLabel
{
    /**
     * Represents the colour scheme the app is painted in.
     */
    case Appearance = 'appearance';

    /**
     * Represents the language the app is shown in.
     */
    case Locale = 'locale';

    /**
     * Indicate if the given value is one the preference
     * accepts.
     *
     * @phpstan-assert-if-true string $value
     */
    public function accepts(mixed $value): bool
    {
        return is_string($value) && in_array($value, $this->values(), true);
    }

    /**
     * Build the cookie that carries the given value to the
     * browser. The in-app menus write it from the browser
     * too, so it is not HttpOnly: a browser refuses to
     * overwrite one that is.
     */
    public function cookie(string $value): Cookie
    {
        return CookieJar::make($this->cookieName(), $value, 60 * 24 * 365, httpOnly: false);
    }

    /**
     * Get the name of the cookie that mirrors the preference.
     */
    public function cookieName(): string
    {
        return match ($this) {
            self::Appearance => 'appearance',
            self::Locale => 'filament_language_switch_locale',
        };
    }

    /**
     * Get the label for the preference.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Appearance => trans('Theme'),
            self::Locale => trans('Language'),
        };
    }

    /**
     * Get the values the preference accepts: the installed
     * locales, or the colour schemes the menus offer.
     *
     * @return array<int, string>
     */
    public function values(): array
    {
        return match ($this) {
            self::Appearance => ['light', 'dark', 'system'],
            self::Locale => self::installedLocales(),
        };
    }

    /**
     * Get the codes of the locales the app is translated into.
     *
     * @return array<int, string>
     */
    private static function installedLocales(): array
    {
        /** @var array<int, string> $locales */
        $locales = Locales::installed()->pluck('code')->all();

        return $locales;
    }
}
