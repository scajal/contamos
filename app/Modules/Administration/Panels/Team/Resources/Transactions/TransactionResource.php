<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Finance\Models\Team\Transaction;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Get the attributes the transaction is written from, as the
     * finance actions expect them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        return [
            'account_id' => $data['account_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'category' => $data['category'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
            'description' => $data['description'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? null,
            'quote' => $data['quote'] ?? null,
            'type' => $data['type'] ?? null,
        ];
    }

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\TransactionForm::configure($schema);
    }

    /**
     * Get the resource's navigation group.
     */
    #[\Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('Finance');
    }

    /**
     * Configure the resource's infolist.
     */
    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return Schemas\TransactionInfolist::configure($schema);
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\TransactionsTable::configure($table);
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
