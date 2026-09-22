<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\Schemas;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Forms;
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
                Schemas\Components\Section::make()
                    ->columnSpan($schema->getOperation() === Operation::Create->value ? null : [Width::Medium->value => 2])
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
                Schemas\Components\Section::make()
                    ->disabled()
                    ->visible($schema->getOperation() !== Operation::Create->value)
                    ->schema([Forms\Components\DateTimePicker::make('created_at'), Forms\Components\DateTimePicker::make('updated_at')]),
            ]);
    }
}
