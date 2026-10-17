<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelompok_bebek', function (Blueprint $table) {
            $table->integer('umur_awal_minggu')->default(0)->after('tanggal_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('kelompok_bebek', function (Blueprint $table) {
            $table->dropColumn('umur_awal_minggu');
        });
    }
};
