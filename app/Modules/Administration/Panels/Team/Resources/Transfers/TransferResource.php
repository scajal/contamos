<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Finance\Models\Team\Transfer;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Get the attributes the transfer is written from, both of its
     * legs and the fees they carry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        return [
            'amount_received' => $data['amount_received'] ?? null,
            'amount_sent' => $data['amount_sent'] ?? null,
            'description' => $data['description'] ?? null,
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'destination_currency_id' => $data['destination_currency_id'] ?? null,
            'destination_fee' => $data['destination_fee'] ?? null,
            'destination_quote' => $data['destination_quote'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? null,
            'origin_account_id' => $data['origin_account_id'] ?? null,
            'origin_currency_id' => $data['origin_currency_id'] ?? null,
            'origin_fee' => $data['origin_fee'] ?? null,
            'origin_quote' => $data['origin_quote'] ?? null,
        ];
    }

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\TransferForm::configure($schema);
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
        return Schemas\TransferInfolist::configure($schema);
    }

    /**
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\TransfersTable::configure($table);
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
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'view' => Pages\ViewTransfer::route('/{record}'),
        ];
    }
}
