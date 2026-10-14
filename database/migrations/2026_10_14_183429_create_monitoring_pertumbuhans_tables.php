<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_pertumbuhans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_bebek_id')->constrained('kelompok_bebeks')->cascadeOnDelete();
            $table->date('tanggal');
            $table->integer('umur_hari');
            $table->float('berat_rata_rata');
            $table->float('pakan_kg');
            $table->string('kondisi_kesehatan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_pertumbuhans');
    }
};