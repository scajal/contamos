<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\Schemas;

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
                    Forms\Components\Select::make('currencies')
                        ->helperText(trans('An account holds one balance per currency.'))
                        ->placeholder(trans('Select the currencies'))
                        ->relationship(titleAttribute: 'name')
                        ->columnSpanFull()
                        ->multiple()
                        ->required()
                        ->preload(),
                ]),
        ];
    }
}
