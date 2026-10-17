<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengurangan_populasis', function (Blueprint $table) {
            // Mati / Afkir / Hilang — memisahkan kematian asli dari afkir.
            $table->string('kategori')->default('Mati')->after('tanggal');

            // Detail tambahan di luar kategori penyebab baku.
            $table->text('catatan')->nullable()->after('penyebab');

            // Query halaman: filter kandang + rentang/urutan tanggal.
            $table->index(['kelompok_bebek_id', 'tanggal'], 'pengurangan_populasi_kelompok_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('pengurangan_populasis', function (Blueprint $table) {
            $table->dropIndex('pengurangan_populasi_kelompok_tanggal_index');
            $table->dropColumn(['kategori', 'catatan']);
        });
    }
};
