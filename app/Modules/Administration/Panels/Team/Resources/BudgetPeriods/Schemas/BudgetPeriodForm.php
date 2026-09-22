<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Schemas;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Tag;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class BudgetPeriodForm
{
    /**
     * Configure the resource's form. What a period follows is
     * chosen first and never again: an edit shows the target
     * but leaves it alone.
     */
    public static function configure(Schema $schema): Schema
    {
        $isCreate = $schema->getOperation() === Operation::Create->value;

        return $schema->components([
            Forms\Components\Select::make('target_kind')
                ->label(trans('Target'))
                ->options(fn (): array => collect(BudgetTargetKind::cases())
                    ->mapWithKeys(fn (BudgetTargetKind $kind): array => [$kind->value => $kind->getLabel()])
                    ->all())
                ->default(BudgetTargetKind::Category->value)
                // A budget follows one thing, so picking a kind
                // empties the picker of the other.
                ->afterStateUpdated(function (Set $set): void {
                    $set('category', null);
                    $set('tag_id', null);
                })
                ->disabled(! $isCreate)
                ->dehydrated(false)
                ->required()
                ->live(),
            Forms\Components\Select::make('category')
                ->placeholder(trans('Select a category'))
                ->options(fn (): array => collect(Category::cases())
                    ->mapWithKeys(fn (Category $category): array => [$category->value => $category->getLabel()])
                    ->all())
                ->visible(fn (Get $get): bool => $get('target_kind') === BudgetTargetKind::Category->value)
                ->required(fn (Get $get): bool => $get('target_kind') === BudgetTargetKind::Category->value)
                ->disabled(! $isCreate)
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('tag_id')
                ->placeholder(trans('Select a tag'))
                ->options(fn (): array => self::teamTags())
                ->visible(fn (Get $get): bool => $get('target_kind') === BudgetTargetKind::Tag->value)
                ->required(fn (Get $get): bool => $get('target_kind') === BudgetTargetKind::Tag->value)
                ->disabled(! $isCreate)
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('amount')
                ->placeholder(trans('Enter an amount'))
                ->prefixIcon(LucideIcon::DollarSign)
                ->inputMode('decimal')
                ->required()
                ->numeric()
                ->gt('0'),
            Forms\Components\Select::make('currency_id')
                ->placeholder(trans('Select a currency'))
                ->options(fn (): array => Currency::query()->isActive()->orderBy('code')->pluck('code', 'id')->all())
                ->searchable()
                ->required()
                ->preload(),
            Forms\Components\DatePicker::make('from_month')
                ->helperText(trans('The first month the budget applies to.'))
                ->prefixIcon(LucideIcon::Calendar)
                ->displayFormat('m/Y')
                ->required(),
            Forms\Components\DatePicker::make('until_month')
                ->helperText(trans('Leave it empty to keep the budget running.'))
                ->prefixIcon(LucideIcon::Calendar)
                ->displayFormat('m/Y'),
        ]);
    }

    /**
     * The tags of the team the panel is looking at, which are
     * the only ones a budget of theirs may follow.
     *
     * @return array<string, string>
     */
    protected static function teamTags(): array
    {
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var array<string, string> $tags */
        $tags = $team->tags()
            ->orderBy('name')
            ->pluck('name', new Tag()->getKeyName())
            ->all();

        return $tags;
    }
}
