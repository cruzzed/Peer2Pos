<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\QtyTypesRelationManager;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('item_code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->label('Item Code'),
            TextInput::make('name')->required()->maxLength(255),

            Select::make('category_id')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->required()->maxLength(255),
                ]),
            Select::make('supplier_id')
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Select::make('location_id')
                ->relationship('location', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_code')->searchable()->sortable()->label('Code'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category.name')->sortable(),
                TextColumn::make('supplier.name')->sortable()->toggleable(),
                TextColumn::make('location.name')->sortable()->toggleable(),
                TextColumn::make('qtyTypes_count')->counts('qtyTypes')->label('SKUs'),
                TextColumn::make('stock_qty')->label('Stock')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name'),
                SelectFilter::make('supplier')->relationship('supplier', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            QtyTypesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
