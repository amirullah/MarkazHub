<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modal produk historis (Total Harga Produk dari Laporan Pesanan Jakmall),
        // yaitu biaya bila pesanan dropship dianggap packing sendiri (tanpa biaya mitra).
        if (! Schema::hasColumn('orders', 'dropship_modal')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('dropship_modal', 14, 2)->default(0)->after('dropship_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'dropship_modal')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('dropship_modal');
            });
        }
    }
};
