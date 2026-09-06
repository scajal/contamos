<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Currencies\CurrencyResource;
use App\Modules\Administration\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Unit tests for \App\Modules\Administration\Resources\Resource
 * navigation icon branches that Filament pages do not reach.
 */
it('converts an outlined backed enum navigation icon into a solid heroicon', function (): void {
    expect(CurrencyResource::getActiveNavigationIcon())->toBe('heroicon-s-currency-dollar');
});

it('returns null when the resource has no navigation icon', function (): void {
    $resource = new class extends Resource
    {
        protected static string|\BackedEnum|null $navigationIcon = null;
    };

    expect($resource::getActiveNavigationIcon())->toBeNull();
});

it('converts a string outlined navigation icon into a solid heroicon', function (): void {
    $resource = new class extends Resource
    {
        protected static string|\BackedEnum|null $navigationIcon = 'o-star';
    };

    expect($resource::getActiveNavigationIcon())->toBe('heroicon-s-star');
});

it('converts an htmlable outlined navigation icon into a solid heroicon', function (): void {
    $resource = new class extends Resource
    {
        public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
        {
            return new HtmlString('o-custom');
        }
    };

    expect($resource::getActiveNavigationIcon())->toBe('heroicon-s-custom');
});
