<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('pengurangan_populasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_bebek_id')->constrained('kelompok_bebeks')->cascadeOnDelete();
            $table->date('tanggal');
            $table->integer('jumlah');
            $table->string('penyebab');
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengurangan_populasis');
    }
};
