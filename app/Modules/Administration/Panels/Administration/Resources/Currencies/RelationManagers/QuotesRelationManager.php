<?php

namespace App\Modules\Administration\Panels\Administration\Resources\Currencies\RelationManagers;

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Support\Helpers\Amount;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class QuotesRelationManager extends RelationManager
{
    protected static ?string $inverseRelationship = 'currency';

    protected static string $relationship = 'quotes';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Quotes');
    }

    /**
     * Configure the relation manager's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('observed_at', 'desc')
            ->columns($this->getColumns())
            ->headerActions($this->getHeaderActions())
            ->recordActions($this->getRecordActions());
    }

    /**
     * Get the columns for the relation manager's table.
     *
     * @return array<\Filament\Tables\Columns\Layout\Stack>
     */
    protected function getColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('rate')
                ->formatStateUsing(fn (Quote $record): string => new Amount()->format($record->rate, $record->currency)) // @phpstan-ignore-line argument.type False positive.
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('observed_at')
                ->dateTime('d/m/Y H:i'),
        ];
    }

    /**
     * Get the header actions for the relation manager's table.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading(trans('filament-actions::create.single.modal.heading', ['label' => trans('quote')]))
                ->label(trans('filament-actions::create.single.label', ['label' => trans('quote')]))
                ->modalWidth(Width::Medium)
                ->createAnother(false)
                ->slideOver()
                ->schema([
                    Forms\Components\DateTimePicker::make('observed_at')
                        ->helperText(trans('The date from which the quote is available.'))
                        ->prefixIcon(LucideIcon::Calendar)
                        ->required(),
                    Forms\Components\TextInput::make('rate')
                        ->prefixIcon(LucideIcon::DollarSign)
                        ->placeholder(trans('Enter a rate'))
                        ->inputMode('decimal')
                        ->required()
                        ->numeric()
                        ->gte('0'),
                    Forms\Components\Hidden::make('source')
                        ->default(QuoteSource::ManualOrScheduled),
                ]),
        ];
    }

    /**
     * Get the record actions for the relation manager's table.
     *
     * @return array<\Filament\Actions\ActionGroup>
     */
    protected function getRecordActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
            ]),
        ];
    }
}
