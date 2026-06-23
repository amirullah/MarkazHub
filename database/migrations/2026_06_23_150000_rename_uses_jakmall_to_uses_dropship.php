<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generalisasi: toggle bukan khusus Jakmall, tapi "berjualan dropship" (sumber apa pun).
        if (Schema::hasColumn('organizations', 'uses_jakmall') && ! Schema::hasColumn('organizations', 'uses_dropship')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->renameColumn('uses_jakmall', 'uses_dropship');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'uses_dropship') && ! Schema::hasColumn('organizations', 'uses_jakmall')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->renameColumn('uses_dropship', 'uses_jakmall');
            });
        }
    }
};
