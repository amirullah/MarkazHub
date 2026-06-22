<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Menampilkan detail produk (item) di dalam sebuah pesanan. Read-only —
 * item dibangun dari import, bukan diedit manual.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Produk dalam Pesanan';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk dalam Pesanan')
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record): string => $record->qty_assumed ? 'warning' : 'gray')
                    ->tooltip(fn ($record): ?string => $record->qty_assumed ? 'Qty diasumsikan (tidak ada di data sumber)' : null),
                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->alignEnd(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->state(fn ($record): float => (float) $record->qty * (float) $record->unit_price)
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('unit_cost')
                    ->label('HPP / Modal')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->alignEnd()
                    ->toggleable(),
            ])
            ->paginated([10, 25, 50]);
    }
}
