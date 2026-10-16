<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produksi_telur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_bebek_id')->constrained('kelompok_bebek')->cascadeOnDelete();
            $table->date('tanggal_produksi');
            $table->integer('jumlah_bebek');
            $table->integer('telur_dihasilkan');
            $table->integer('telur_rusak');
            $table->integer('telur_layak_jual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produksi_telur');
    }
};