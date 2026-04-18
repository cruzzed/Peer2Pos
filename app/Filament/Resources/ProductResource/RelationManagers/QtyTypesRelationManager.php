<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Discount;
use App\Models\QtyType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QtyTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'qtyTypes';

    protected static ?string $title = 'Qty Types';

    /** Temporarily holds extracted discount data during a create/save cycle. */
    protected ?array $pendingDiscountData = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(50),
            TextInput::make('barcode')->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('qty')->numeric()->required()->minValue(1)->default(1),
            Toggle::make('is_base')->label('Base Unit')->default(false)
                ->helperText('Exactly one qty type per product should be the base (qty=1).'),

            TextInput::make('base_price')
                ->numeric()->prefix('$')->required()->minValue(0)
                ->label('Base Price')
                ->helperText('Purchase / reference cost.'),
            TextInput::make('selling_price')
                ->numeric()->prefix('$')->required()->minValue(0)
                ->label('Selling Price')
                ->helperText('Price shown at checkout before discount.'),

            Fieldset::make('Discount')
                ->schema([
                    Toggle::make('discount_enabled')->label('Enable Discount')->default(false)->live(),
                    Select::make('discount_type')
                        ->options(['fixed' => 'Fixed Amount', 'percent' => 'Percentage'])
                        ->default('fixed')->label('Type'),
                    TextInput::make('discount_value')
                        ->numeric()->minValue(0)->default(0)->label('Value')
                        ->helperText('Amount or % subtracted from selling price.'),
                    Toggle::make('discount_timed')->label('Timed Discount')->default(false)->live(),
                    DatePicker::make('discount_date_start')
                        ->label('Start Date')
                        ->visible(fn ($get) => $get('discount_timed')),
                    DatePicker::make('discount_date_end')
                        ->label('End Date')
                        ->visible(fn ($get) => $get('discount_timed')),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('barcode')->searchable(),
                TextColumn::make('qty')->sortable(),
                IconColumn::make('is_base')->boolean()->label('Base'),
                TextColumn::make('base_price')->money('USD'),
                TextColumn::make('selling_price')->money('USD'),
                IconColumn::make('discount.enabled')->boolean()->label('Discount'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record) {
                        $this->applyDiscount($record);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record) {
                        $this->applyDiscount($record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingDiscountData = $this->extractDiscountData($data);
        return $this->removeDiscountFields($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingDiscountData = $this->extractDiscountData($data);
        return $this->removeDiscountFields($data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $discount = QtyType::find($data['id'] ?? null)?->discount;

        if ($discount) {
            $data['discount_enabled']    = $discount->enabled;
            $data['discount_type']       = $discount->type;
            $data['discount_value']      = $discount->value;
            $data['discount_timed']      = $discount->timed;
            $data['discount_date_start'] = $discount->date_start?->format('Y-m-d');
            $data['discount_date_end']   = $discount->date_end?->format('Y-m-d');
        }

        return $data;
    }

    private function extractDiscountData(array $data): array
    {
        return [
            'enabled'    => (bool) ($data['discount_enabled'] ?? false),
            'type'       => $data['discount_type'] ?? 'fixed',
            'value'      => $data['discount_value'] ?? 0,
            'timed'      => (bool) ($data['discount_timed'] ?? false),
            'date_start' => $data['discount_date_start'] ?? null,
            'date_end'   => $data['discount_date_end'] ?? null,
        ];
    }

    private function removeDiscountFields(array $data): array
    {
        unset(
            $data['discount_enabled'],
            $data['discount_type'],
            $data['discount_value'],
            $data['discount_timed'],
            $data['discount_date_start'],
            $data['discount_date_end'],
        );
        return $data;
    }

    private function applyDiscount($record): void
    {
        if ($this->pendingDiscountData === null) {
            return;
        }

        Discount::updateOrCreate(
            ['qty_type_id' => $record->id],
            $this->pendingDiscountData
        );

        $this->pendingDiscountData = null;
    }
}
