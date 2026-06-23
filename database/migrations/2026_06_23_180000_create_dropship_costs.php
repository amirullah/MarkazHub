<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyimpanan PERMANEN biaya dropship per No. Pesanan (dari laporan dropship / template).
 * Agar urutan unggah tidak masalah: bila laporan dropship diunggah SEBELUM laporan
 * marketplace, datanya tetap tersimpan di sini, lalu otomatis diterapkan saat pesanan
 * dibuat. Bila marketplace lebih dulu, backfillDropship memperbarui pesanan yang ada
 * DAN data tetap disimpan di sini sebagai sumber kebenaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dropship_costs')) {
            return;
        }
        Schema::create('dropship_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('external_no', 64);
            $table->decimal('total', 14, 2)->default(0);        // total bayar ke supplier (modal + biaya)
            $table->decimal('product_cost', 14, 2)->default(0); // harga produk saja (modal historis)
            $table->string('code', 64)->nullable();             // kode dropship (opsional, utk catatan)
            $table->timestamps();
            $table->unique(['organization_id', 'external_no']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_costs');
    }
};
