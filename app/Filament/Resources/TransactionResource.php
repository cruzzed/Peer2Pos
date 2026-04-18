<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static \UnitEnum|string|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Txn')
                    ->formatStateUsing(fn ($state) => substr($state, 0, 8) . '…')
                    ->copyable()
                    ->copyableState(fn ($state) => $state),
                TextColumn::make('cashier_name')->label('Cashier')->searchable(),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('total_amount')->money('USD')->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('originNode.name')->label('Terminal')->toggleable(),
                TextColumn::make('created_at')->dateTime('M d, Y H:i')->sortable()->label('Date'),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'qr'   => 'QR / E-Wallet',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaction')
                ->schema([
                    TextEntry::make('id')->label('ID')->copyable(),
                    TextEntry::make('cashier_name')->label('Cashier'),
                    TextEntry::make('payment_method')->label('Payment')->formatStateUsing(fn ($state) => ucfirst($state)),
                    TextEntry::make('total_amount')->money('USD'),
                    TextEntry::make('originNode.name')->label('Terminal'),
                    TextEntry::make('created_at')->dateTime('M d, Y H:i:s')->label('Date'),
                ])
                ->columns(3),

            Section::make('Line Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('product_name')->label('Product'),
                            TextEntry::make('qty_type_name')->label('Unit'),
                            TextEntry::make('unit_price')->money('USD'),
                            TextEntry::make('qty'),
                            TextEntry::make('subtotal')->money('USD'),
                        ])
                        ->columns(5),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view'  => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
