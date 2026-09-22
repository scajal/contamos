<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers\Schemas;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TransferForm
{
    /**
     * Configure the resource's form.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make(trans('Origin'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('origin_account_id')
                            ->placeholder(trans('Select an account'))
                            ->options(self::accounts(...))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('origin_currency_id')
                            ->placeholder(trans('Select a currency'))
                            ->options(fn (Get $get): array => self::currencies($get('origin_account_id')))
                            ->searchable()
                            ->required()
                            ->preload(),
                        Forms\Components\TextInput::make('amount_sent')
                            ->placeholder(trans('Enter an amount'))
                            ->prefixIcon(LucideIcon::DollarSign)
                            ->inputMode('decimal')
                            ->required()
                            ->numeric()
                            ->gt('0'),
                        Forms\Components\TextInput::make('origin_fee')
                            ->placeholder(trans('Enter a fee'))
                            ->prefixIcon(LucideIcon::DollarSign)
                            ->inputMode('decimal')
                            ->numeric()
                            ->gt('0'),
                        Forms\Components\TextInput::make('origin_quote')
                            ->placeholder(trans('Enter a rate'))
                            ->prefixIcon(LucideIcon::ArrowRightLeft)
                            ->inputMode('decimal')
                            ->required()
                            ->numeric()
                            ->gt('0'),
                    ]),
                Schemas\Components\Section::make(trans('Destination'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('destination_account_id')
                            ->placeholder(trans('Select an account'))
                            ->different('origin_account_id')
                            ->options(self::accounts(...))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('destination_currency_id')
                            ->placeholder(trans('Select a currency'))
                            ->options(fn (Get $get): array => self::currencies($get('destination_account_id')))
                            ->searchable()
                            ->required()
                            ->preload(),
                        Forms\Components\TextInput::make('amount_received')
                            ->placeholder(trans('Enter an amount'))
                            ->prefixIcon(LucideIcon::DollarSign)
                            ->inputMode('decimal')
                            ->required()
                            ->numeric()
                            ->gt('0'),
                        Forms\Components\TextInput::make('destination_fee')
                            ->placeholder(trans('Enter a fee'))
                            ->prefixIcon(LucideIcon::DollarSign)
                            ->inputMode('decimal')
                            ->numeric()
                            ->gt('0'),
                        Forms\Components\TextInput::make('destination_quote')
                            ->placeholder(trans('Enter a rate'))
                            ->prefixIcon(LucideIcon::ArrowRightLeft)
                            ->inputMode('decimal')
                            ->required()
                            ->numeric()
                            ->gt('0'),
                    ]),
                Schemas\Components\Section::make()
                    ->schema([
                        Forms\Components\DateTimePicker::make('occurred_at')
                            ->prefixIcon(LucideIcon::Calendar)
                            ->seconds(false)
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->placeholder(trans('Enter a description'))
                            ->maxLength(255),
                        Forms\Components\Select::make('tag_ids')
                            ->placeholder(trans('Select the tags'))
                            ->options(self::tags(...))
                            ->multiple()
                            ->preload(),
                    ]),
            ]);
    }

    /**
     * Get the accounts the transfer may move money between.
     *
     * @return array<string, string>
     */
    protected static function accounts(): array
    {
        $accounts = [];

        foreach (self::team()->accounts()->isActive()->orderBy('name')->get() as $account) {
            $accounts[$account->id] = $account->name;
        }

        return $accounts;
    }

    /**
     * Get the currencies the given account holds.
     *
     * @return array<string, string>
     */
    protected static function currencies(mixed $accountId): array
    {
        $account = self::team()->accounts()->whereKey($accountId)->first();

        if (! $account instanceof Account) {
            return [];
        }

        $currencies = [];

        foreach ($account->currencies()->orderBy('code')->get() as $currency) {
            $currencies[$currency->id] = $currency->code;
        }

        return $currencies;
    }

    /**
     * Get the tags the transfer may carry.
     *
     * @return array<string, string>
     */
    protected static function tags(): array
    {
        $tags = [];

        foreach (self::team()->tags()->orderBy('name')->get() as $tag) {
            $tags[$tag->id] = $tag->name;
        }

        return $tags;
    }

    /**
     * Get the team the panel is scoped to.
     */
    protected static function team(): Team
    {
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        return $team;
    }
}
