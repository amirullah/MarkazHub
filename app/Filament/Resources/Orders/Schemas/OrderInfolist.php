<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    private static function rp(): \Closure
    {
        return fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.');
    }

    public static function configure(Schema $schema): Schema
    {
        $rp = self::rp();

        return $schema->components([
            Section::make('Informasi Pesanan')
                ->columns(4)
                ->schema([
                    TextEntry::make('external_no')->label('No. Pesanan')->copyable()->weight('bold'),
                    TextEntry::make('order_date')->label('Tanggal')->dateTime('d M Y H:i'),
                    TextEntry::make('store.name')->label('Toko'),
                    TextEntry::make('marketplace')->label('Channel')->badge()
                        ->formatStateUsing(fn ($s) => OrderForm::CHANNEL[$s] ?? $s)
                        ->color(fn ($s) => $s === 'SHOPEE' ? 'warning' : 'success'),
                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn ($s) => OrderForm::STATUS[$s] ?? $s),
                    TextEntry::make('fulfillment')->label('Pemenuhan')->badge()->color('gray')
                        ->formatStateUsing(fn ($s) => OrderForm::FULFILLMENT[$s] ?? $s),
                    TextEntry::make('buyer_name')->label('Pembeli')->placeholder('—'),
                    TextEntry::make('income_verified')->label('Laba')->badge()
                        ->formatStateUsing(fn ($s) => $s ? 'Final' : 'Estimasi')
                        ->color(fn ($s) => $s ? 'success' : 'gray'),
                ]),

            Section::make('Pendapatan')
                ->description('Uang masuk')
                ->columns(3)
                ->schema([
                    TextEntry::make('product_revenue')->label('Omzet Produk')->formatStateUsing($rp),
                    TextEntry::make('shipping_charged_to_buyer')->label('Ongkir dari Pembeli')->formatStateUsing($rp),
                    TextEntry::make('other_income')->label('Pendapatan Lain')->formatStateUsing($rp),
                ]),

            Section::make('Biaya')
                ->description('Pengurang laba')
                ->columns(3)
                ->schema([
                    TextEntry::make('cogs')->label('HPP / Modal')->formatStateUsing($rp),
                    TextEntry::make('admin_fee')->label('Biaya Admin')->formatStateUsing($rp),
                    TextEntry::make('shipping_cost_seller')->label('Ongkir Ditanggung Seller')->formatStateUsing($rp),
                    TextEntry::make('voucher_seller_borne')->label('Voucher Ditanggung Seller')->formatStateUsing($rp),
                    TextEntry::make('dropship_cost')->label('Biaya Dropship')->formatStateUsing($rp),
                    TextEntry::make('other_cost')->label('Biaya Lain')->formatStateUsing($rp),
                ]),

            Section::make('Ringkasan Laba')
                ->columns(2)
                ->schema([
                    TextEntry::make('net')->label('Uang Bersih Marketplace (sebelum modal)')->formatStateUsing($rp),
                    TextEntry::make('profit')->label('LABA BERSIH')
                        ->formatStateUsing($rp)
                        ->weight('bold')
                        ->size('lg')
                        ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success'),
                ]),
        ]);
    }
}
