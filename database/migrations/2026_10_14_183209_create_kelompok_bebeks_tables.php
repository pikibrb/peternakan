<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelompok_bebeks', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_bebek');
            $table->date('tanggal_masuk');
            $table->integer('umur_awal_hari');
            $table->integer('jumlah_awal');
            $table->integer('jumlah_saat_ini');
            $table->string('kondisi_kesehatan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelompok_bebeks');
    }
};