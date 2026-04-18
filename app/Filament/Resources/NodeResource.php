<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NodeResource\Pages;
use App\Models\Node;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-server';

    protected static \UnitEnum|string|null $navigationGroup = 'Sync';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('url')
                ->url()
                ->placeholder('http://192.168.1.10:8000')
                ->helperText('Leave blank for the local node.'),
            Toggle::make('is_self')->disabled()->helperText('Only one node can be marked as self.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('url')->copyable()->toggleable(),
                IconColumn::make('is_self')->boolean()->label('Self'),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (Node $record) => $record->is_self),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNodes::route('/'),
            'create' => Pages\CreateNode::route('/create'),
            'edit' => Pages\EditNode::route('/{record}/edit'),
        ];
    }
}
