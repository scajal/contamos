<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules;

use App\Modules\Administration\Resources\Resource;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecurringRuleResource extends Resource
{
    protected static ?string $model = RecurringRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    /**
     * Get the attributes the rule and its schedule are written from,
     * as the finance actions expect them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        return [
            'account_id' => $data['account_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
            'description' => $data['description'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'frequency' => $data['frequency'] ?? null,
            'interval' => $data['interval'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'time_of_day' => $data['time_of_day'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'type' => $data['type'] ?? null,
        ];
    }

    /**
     * Configure the resource's form.
     */
    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return Schemas\RecurringRuleForm::configure($schema);
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
     * Configure the resource's table.
     */
    #[\Override]
    public static function table(Table $table): Table
    {
        return Tables\RecurringRulesTable::configure($table);
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
            'index' => Pages\ListRecurringRules::route('/'),
            'create' => Pages\CreateRecurringRule::route('/create'),
            'edit' => Pages\EditRecurringRule::route('/{record}/edit'),
        ];
    }
}
