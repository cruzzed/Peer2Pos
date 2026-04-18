<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncStatusResource\Pages;
use App\Models\SyncStatus;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncStatusResource extends Resource
{
    protected static ?string $model = SyncStatus::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static \UnitEnum|string|null $navigationGroup = 'Sync';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('syncable_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->sortable(),
                TextColumn::make('syncable_id')->label('Record ID')->limit(8)->tooltip(fn ($state) => $state),
                TextColumn::make('node.name')->label('Peer')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'synced' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('synced_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'synced' => 'Synced', 'failed' => 'Failed']),
                SelectFilter::make('node')->relationship('node', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncStatuses::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
