<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\Schemas;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Enums\Width;

class AccountForm
{
    /**
     * Configure the resource's form.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns($schema->getOperation() === Operation::Create->value ? 1 : 3)
            ->components([
                Schemas\Components\Section::make()
                    ->columnSpan($schema->getOperation() === Operation::Create->value ? null : [Width::Medium->value => 2])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->placeholder(trans('Enter a name'))
                            ->prefixIcon(LucideIcon::Type)
                            ->maxLength(255)
                            ->required()
                            ->string(),
                        Forms\Components\Select::make('type')
                            ->placeholder(trans('Select a type'))
                            ->options(AccountType::class)
                            ->enum(AccountType::class)
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('currency_ids')
                            ->helperText(trans('An account holds one balance per currency.'))
                            ->placeholder(trans('Select the currencies'))
                            ->options(self::currencies(...))
                            ->multiple()
                            ->required()
                            ->preload(),
                    ]),
                Schemas\Components\Section::make()
                    ->disabled()
                    ->visible($schema->getOperation() !== Operation::Create->value)
                    ->schema([Forms\Components\DateTimePicker::make('created_at'), Forms\Components\DateTimePicker::make('updated_at')]),
            ]);
    }

    /**
     * Get the currencies the account may hold, which depends on
     * whether it is a crypto account.
     *
     * @return array<string, string>
     */
    protected static function currencies(Get $get): array
    {
        $type = $get('type');
        $type = $type instanceof AccountType ? $type : AccountType::tryFrom(is_string($type) ? $type : '');

        $currencies = [];

        $held = Currency::query()
            ->isActive()
            ->where('type', $type === AccountType::Crypto ? '=' : '!=', CurrencyType::Cryptocurrency)
            ->orderBy('code')
            ->get();

        foreach ($held as $currency) {
            $currencies[$currency->id] = $currency->code;
        }

        return $currencies;
    }
}
