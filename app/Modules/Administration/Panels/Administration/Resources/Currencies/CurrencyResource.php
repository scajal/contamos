<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Currencies;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Catalog\Models\Currency;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\CurrencyForm::configure($schema);
    }

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Application');
    }

    /**
     * Get the resource's relations.
     *
     * @return array<string>
     */
    #[\Override]
    public static function getRelations(): array
    {
        return [
            RelationManagers\QuotesRelationManager::class,
        ];
    }

    /**
     * Configure the resource's infolist.
     */
    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return Schemas\CurrencyInfolist::configure($schema);
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\CurrenciesTable::configure($table);
    }

    /**
     * Get the resource's pages.
     *
     * @return array<\Filament\Resources\Pages\PageRegistration>
     */
    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
            'view' => Pages\ViewCurrency::route('/{record}'),
        ];
    }
}
