<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // Keterangan transaksi (cara bayar, alamat kirim, nama pengepul, dsb).
            $table->text('catatan')->nullable()->after('status_pembayaran');

            // Query halaman: filter status + urutan/rentang tanggal.
            $table->index(['status_pembayaran', 'tanggal_penjualan'], 'penjualan_status_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropIndex('penjualan_status_tanggal_index');
            $table->dropColumn('catatan');
        });
    }
};
