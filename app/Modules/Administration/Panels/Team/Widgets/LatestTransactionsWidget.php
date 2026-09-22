<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Widgets;

use App\Modules\Administration\Panels\Team\Resources\Transactions\TransactionResource;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Support\Helpers\Amount;
use Filament\Facades\Filament;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestTransactionsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Get the heading of the widget.
     */
    #[\Override]
    protected function getTableHeading(): ?string
    {
        return trans('Latest transactions');
    }

    /**
     * Configure the widget's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('occurred_at', 'desc')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->weight(FontWeight::Medium)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('account.name'),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn (Transaction $record): string => new Amount()->format((string) $record->amount, $record->currency)),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->dateTime(),
            ])
            ->recordUrl(fn (Transaction $record): string => TransactionResource::getUrl('view', ['record' => $record]));
    }

    /**
     * Get the transactions the team recorded most recently.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected function getTableQuery(): Builder
    {
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        return $team->transactions()
            ->getQuery()
            ->with(['account', 'currency'])
            ->limit(10);
    }
}
