<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        // Modal historis per produk = modal pesanan dropship 1-produk TERBARU yang memuat
        // SKU ini (dari Laporan Pesanan Jakmall). Pesanan 1-produk → dropship_modal = modal SKU itu.
        $subModalHistoris = '(SELECT o.dropship_modal / NULLIF(i.qty, 0) FROM order_items i '
            . 'JOIN orders o ON o.id = i.order_id '
            . "WHERE i.sku = products.sku AND o.organization_id = products.organization_id "
            . "AND o.fulfillment = 'DROPSHIP' AND o.dropship_modal > 0 "
            . 'AND (SELECT COUNT(*) FROM order_items i2 WHERE i2.order_id = o.id) = 1 '
            . 'ORDER BY o.order_date DESC LIMIT 1)';

        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'category'])
                ->select('products.*')
                ->selectRaw($subModalHistoris . ' AS modal_historis'))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('cost_price')
                    ->label('HPP / Modal')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('dropship_cost')
                    ->label('Modal Dropship')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd()
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesJakmall()),
                TextColumn::make('modal_historis')
                    ->label('Modal Historis')
                    ->tooltip('Modal produk saat pesanan dropship terakhir (dari Laporan Pesanan Jakmall). Bandingkan dengan HPP/Modal kini untuk lihat perubahan harga.')
                    ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '—')
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('perubahan_hpp')
                    ->label('Δ HPP (kini − historis)')
                    ->state(fn ($record) => $record->modal_historis !== null
                        ? (float) $record->cost_price - (float) $record->modal_historis : null)
                    ->formatStateUsing(function ($state): string {
                        if ($state === null) {
                            return '—';
                        }
                        $s = (float) $state;
                        if (abs($s) < 1) {
                            return 'tetap';
                        }
                        return ($s > 0 ? '▲ +Rp' : '▼ −Rp') . number_format(abs($s), 0, ',', '.');
                    })
                    ->badge()
                    ->color(fn ($state): string => $state === null || abs((float) $state) < 1
                        ? 'gray' : ((float) $state > 0 ? 'danger' : 'success'))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('category.fee_shopee')
                    ->label('Admin Shopee')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%' : '—')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('category.fee_tokotiktok')
                    ->label('Admin Toped/TikTok')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%' : '—')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('ada_modal_historis')
                    ->label('Hanya produk dropship (ada modal historis)')
                    ->query(fn ($query) => $query->whereIn('products.sku', function ($q): void {
                        $q->select('i.sku')->from('order_items as i')
                            ->join('orders as o', 'o.id', '=', 'i.order_id')
                            ->where('o.organization_id', (int) auth()->user()->organization_id)
                            ->where('o.fulfillment', 'DROPSHIP')
                            ->where('o.dropship_modal', '>', 0);
                    })),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
