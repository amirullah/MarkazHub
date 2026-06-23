<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default TRUE: org existing yang sudah pakai Jakmall tidak berubah perilakunya.
        if (! Schema::hasColumn('organizations', 'uses_jakmall')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('uses_jakmall')->default(true)->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'uses_jakmall')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('uses_jakmall');
            });
        }
    }
};
