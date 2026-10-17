<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_pertumbuhans', function (Blueprint $table) {
            // Jumlah ekor yang ditimbang saat pengambilan sampel.
            $table->integer('jumlah_sampel')->default(0)->after('umur_hari');

            // Simpangan baku berat sampel (gram) -> dasar hitung keseragaman.
            $table->float('berat_std_dev')->nullable()->after('berat_rata_rata');

            $table->text('catatan')->nullable()->after('kondisi_kesehatan');

            // Mempercepat query utama halaman monitoring:
            // where kelompok_bebek_id = ? order by tanggal.
            $table->index(['kelompok_bebek_id', 'tanggal'], 'monitoring_pertumbuhan_kelompok_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_pertumbuhans', function (Blueprint $table) {
            $table->dropIndex('monitoring_pertumbuhan_kelompok_tanggal_index');
            $table->dropColumn(['jumlah_sampel', 'berat_std_dev', 'catatan']);
        });
    }
};
