<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelompok_bebek', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kandang'); 
            $table->string('jenis_bebek');
            $table->enum('fase', ['Starter', 'Grower', 'Layer', 'Afkir'])->default('Layer');
            $table->date('tanggal_masuk');
            
            $table->integer('populasi_awal');
            $table->integer('populasi_aktif');
            $table->enum('status', ['Aktif', 'Kosong'])->default('Aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelompok_bebek');
    }
};