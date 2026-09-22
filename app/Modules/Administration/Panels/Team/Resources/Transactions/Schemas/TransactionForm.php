<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Schemas;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Enums\Width;

class TransactionForm
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
                        Forms\Components\Select::make('account_id')
                            ->placeholder(trans('Select an account'))
                            ->options(self::accounts(...))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('currency_id')
                            ->placeholder(trans('Select a currency'))
                            ->options(self::currencies(...))
                            ->searchable()
                            ->required()
                            ->preload(),
                        Forms\Components\Select::make('type')
                            ->placeholder(trans('Select a type'))
                            ->options(TransactionType::class)
                            ->enum(TransactionType::class)
                            // The two lists do not overlap, so the
                            // category is dropped on a switch.
                            ->afterStateUpdated(fn (Set $set): mixed => $set('category', null))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('category')
                            ->placeholder(trans('Select a category'))
                            ->options(self::categories(...))
                            ->required(fn (Get $get): bool => self::categories($get) !== [])
                            ->disabled(fn (Get $get): bool => self::categories($get) === [])
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('amount')
                            ->placeholder(trans('Enter an amount'))
                            ->prefixIcon(LucideIcon::DollarSign)
                            ->inputMode('decimal')
                            ->required()
                            ->numeric()
                            ->gt('0'),
                        Forms\Components\TextInput::make('quote')
                            ->helperText(trans('Leave it empty to use the exchange rate of the selected date.'))
                            ->placeholder(trans('Enter a rate'))
                            ->prefixIcon(LucideIcon::ArrowRightLeft)
                            ->inputMode('decimal')
                            ->numeric()
                            ->gt('0'),
                        Forms\Components\DateTimePicker::make('occurred_at')
                            ->prefixIcon(LucideIcon::Calendar)
                            ->seconds(false)
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->placeholder(trans('Enter a description'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('tag_ids')
                            ->placeholder(trans('Select the tags'))
                            ->options(self::tags(...))
                            ->columnSpanFull()
                            ->multiple()
                            ->preload(),
                    ]),
                Schemas\Components\Section::make()
                    ->disabled()
                    ->visible($schema->getOperation() !== Operation::Create->value)
                    ->schema([Forms\Components\DateTimePicker::make('created_at'), Forms\Components\DateTimePicker::make('updated_at')]),
            ]);
    }

    /**
     * Get the accounts the transaction may be recorded on.
     *
     * @return array<string, string>
     */
    protected static function accounts(): array
    {
        $accounts = [];

        foreach (self::team()->accounts()->orderBy('name')->get() as $account) {
            $accounts[$account->id] = $account->name;
        }

        return $accounts;
    }

    /**
     * Get the currencies the selected account holds.
     *
     * @return array<string, string>
     */
    protected static function currencies(Get $get): array
    {
        $account = self::team()->accounts()->whereKey($get('account_id'))->first();

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
     * Get the categories the selected type offers, and none at all
     * for a type that is never filed under one.
     *
     * @return array<string, string>
     */
    protected static function categories(Get $get): array
    {
        $value = $get('type');

        // The select casts its state to the enum when the type is
        // one of its cases, and leaves it a plain string until then.
        $type = $value instanceof TransactionType ? $value : TransactionType::tryFrom(is_string($value) ? $value : '');

        $categories = [];

        foreach ($type instanceof TransactionType ? Category::casesFor($type) : [] as $category) {
            $categories[$category->value] = $category->getLabel();
        }

        return $categories;
    }

    /**
     * Get the tags the transaction may carry.
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
