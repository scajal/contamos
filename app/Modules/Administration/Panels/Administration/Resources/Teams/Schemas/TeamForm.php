<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\Schemas;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Enums\Width;

class TeamForm
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
                    ->schema(self::schema($schema)),
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
    protected static function schema(Schema $schema): array
    {
        return [
            Schemas\Components\Tabs\Tab::make(trans('General'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->placeholder(trans('Enter a name'))
                        ->prefixIcon(LucideIcon::Type)
                        ->maxLength(255)
                        ->required()
                        ->string(),
                    Forms\Components\TextInput::make('slug')
                        ->helperText(trans('Leave it empty to derive the slug from the name.'))
                        ->placeholder(trans('Enter a slug'))
                        ->prefixIcon(LucideIcon::Link)
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->string(),
                ]),
            Schemas\Components\Tabs\Tab::make(trans('Plan'))
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('plan')
                        ->state(fn (Team $record): string => $record->plan()->getLabel())
                        ->visible($schema->getOperation() !== Operation::Create->value)
                        ->label(trans('Current'))
                        ->columnSpanFull()
                        ->badge(),
                    Forms\Components\Select::make('plan_override')
                        ->helperText(trans('If you want to grant a plan by hand, select it here. Leave it empty to use the plan from the subscription.'))
                        ->placeholder(trans('Select a plan'))
                        ->label(trans('Override'))
                        ->options(Plan::class)
                        ->enum(Plan::class)
                        ->native(false),
                    Forms\Components\DateTimePicker::make('plan_override_expires_at')
                        ->helperText(trans('The plan will be granted until this date. Leave it empty to grant the plan indefinitely.'))
                        ->placeholder(trans('Select a date'))
                        ->prefixIcon(LucideIcon::Calendar)
                        ->label(trans('Expires at')),
                ]),
        ];
    }
}
