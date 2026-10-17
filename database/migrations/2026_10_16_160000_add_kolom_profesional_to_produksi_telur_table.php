<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produksi_telur', function (Blueprint $table) {
            // Konsumsi pakan pada periode pencatatan (kg). Nullable karena
            // peternak sering baru mencatat telurnya saja.
            $table->float('pakan_kg')->nullable()->after('telur_layak_jual');

            // Berat butir rata-rata dari sampel (gram), dasar hitung
            // konversi pakan per kg telur.
            $table->float('berat_butir_g')->nullable()->after('pakan_kg');

            $table->text('catatan')->nullable()->after('berat_butir_g');

            // Query halaman produksi: filter kelompok + rentang/urutan tanggal.
            $table->index(['kelompok_bebek_id', 'tanggal_produksi'], 'produksi_telur_kelompok_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('produksi_telur', function (Blueprint $table) {
            $table->dropIndex('produksi_telur_kelompok_tanggal_index');
            $table->dropColumn(['pakan_kg', 'berat_butir_g', 'catatan']);
        });
    }
};
