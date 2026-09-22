<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Schemas;

use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use DateTimeZone;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RecurringRuleForm
{
    /**
     * Configure the resource's form.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make(trans('Entry'))
                    ->columns(2)
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
                            ->options([
                                TransactionType::Expense->value => TransactionType::Expense->getLabel(),
                                TransactionType::Income->value => TransactionType::Income->getLabel(),
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->placeholder(trans('Enter an amount'))
                            ->prefixIcon(LucideIcon::DollarSign)
                            ->inputMode('decimal')
                            ->required()
                            ->numeric()
                            ->gt('0'),
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
                Schemas\Components\Section::make(trans('Schedule'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('frequency')
                            ->placeholder(trans('Select a frequency'))
                            ->options(Frequency::class)
                            ->enum(Frequency::class)
                            ->required(),
                        Forms\Components\TextInput::make('interval')
                            ->helperText(trans('How many periods pass between occurrences.'))
                            ->prefixIcon(LucideIcon::Hash)
                            ->default(1)
                            ->required()
                            ->integer()
                            ->minValue(1),
                        Forms\Components\DatePicker::make('starts_on')
                            ->prefixIcon(LucideIcon::Calendar)
                            ->required(),
                        Forms\Components\DatePicker::make('ends_on')
                            ->helperText(trans('Leave it empty to keep the rule running.'))
                            ->prefixIcon(LucideIcon::Calendar),
                        Forms\Components\TimePicker::make('time_of_day')
                            ->prefixIcon(LucideIcon::Clock)
                            ->seconds(false)
                            ->required(),
                        Forms\Components\Select::make('timezone')
                            ->placeholder(trans('Select a timezone'))
                            ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                            ->default(config()->string('app.timezone'))
                            ->searchable()
                            ->required(),
                    ]),
            ]);
    }

    /**
     * Get the accounts the rule may record entries on.
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
     * Get the tags the rule's occurrences will carry.
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
