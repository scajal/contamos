<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\BillingTransactions;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Billing\Models\Transaction;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BillingTransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'paddle_id';

    /**
     * Get the resource model's label.
     */
    #[\Override]
    public static function getModelLabel(): string
    {
        return trans('billing transaction');
    }

    /**
     * Get the resource model's plural label.
     */
    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return trans('Billing transactions');
    }

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Billing');
    }

    /**
     * Configure the resource's infolist.
     */
    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return Schemas\BillingTransactionInfolist::configure($schema);
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\BillingTransactionsTable::configure($table);
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
            'index' => Pages\ListBillingTransactions::route('/'),
            'view' => Pages\ViewBillingTransaction::route('/{record}'),
        ];
    }
}
