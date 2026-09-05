<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Currencies\Schemas;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Enums\Width;

class CurrencyForm
{
    /**
     * Configure the resource's form.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns($schema->getOperation() === Operation::Create->value ? 1 : 3)
            ->components([
                Schemas\Components\Tabs::make()
                    ->columnSpan($schema->getOperation() === Operation::Create->value ? null : [Width::Medium->value => 2])
                    ->schema(self::schema()),
                Schemas\Components\Section::make()
                    ->disabled()
                    ->visible($schema->getOperation() !== Operation::Create->value)
                    ->schema([Forms\Components\DateTimePicker::make('created_at'), Forms\Components\DateTimePicker::make('updated_at')]),
            ]);
    }

    /**
     * Get the schema of the form.
     *
     * @return array<int, \Filament\Schemas\Components\Tabs\Tab>
     */
    protected static function schema(): array
    {
        return [
            Schemas\Components\Tabs\Tab::make('General')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->placeholder(trans('Select a type'))
                        ->disabledOn(Operation::Edit->value)
                        ->options(CurrencyType::class)
                        ->enum(CurrencyType::class)
                        ->columnSpanFull()
                        ->searchable()
                        ->required()
                        ->preload(),
                    Schemas\Components\Grid::make()
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->disabledOn(Operation::Edit->value)
                                ->prefixIcon(LucideIcon::Type)
                                ->placeholder(trans('Enter a code'))
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->required()
                                ->string()
                                ->live(debounce: 500),
                            Forms\Components\TextInput::make('name')
                                ->placeholder(trans('Enter a name'))
                                ->prefixIcon(LucideIcon::Type)
                                ->maxLength(255)
                                ->required()
                                ->string(),
                        ]),
                ]),
            Schemas\Components\Tabs\Tab::make('Financial')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('symbol')
                        ->placeholder(trans('Enter a symbol'))
                        ->prefixIcon(LucideIcon::DollarSign)
                        ->maxLength(255)
                        ->required()
                        ->string(),
                    Forms\Components\TextInput::make('decimal_places')
                        ->placeholder(trans('Enter a decimal places'))
                        ->prefixIcon(LucideIcon::DecimalsArrowRight)
                        ->disabledOn(Operation::Edit->value)
                        ->maxLength(255)
                        ->required()
                        ->numeric(),
                    Forms\Components\Select::make('quote_source')
                        ->placeholder(trans('Select a quote source'))
                        ->options(QuoteSource::class)
                        ->enum(QuoteSource::class)
                        ->columnSpanFull()
                        ->searchable()
                        ->required()
                        ->preload(),
                ]),
        ];
    }
}
