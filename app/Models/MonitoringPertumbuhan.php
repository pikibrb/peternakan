<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MonitoringPertumbuhan extends Model
{
    protected $table = 'monitoring_pertumbuhans';

    protected $fillable = [
        'kelompok_bebek_id',
        'tanggal',
        'umur_hari',
        'jumlah_sampel',
        'berat_rata_rata',
        'berat_std_dev',
        'pakan_kg',
        'kondisi_kesehatan',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'umur_hari' => 'integer',
            'jumlah_sampel' => 'integer',
            'berat_rata_rata' => 'float',
            'berat_std_dev' => 'float',
            'pakan_kg' => 'float',
        ];
    }

    public function kelompokBebek()
    {
        return $this->belongsTo(KelompokBebek::class, 'kelompok_bebek_id');
    }

    /**
     * Simpan tanggal sebagai tanggal murni (Y-m-d), bukan string datetime.
     * MySQL memotong otomatis isi kolom DATE, tetapi SQLite menyimpannya apa
     * adanya sehingga pengecekan unik "satu kandang satu sampling per hari"
     * (Rule::unique pada form) gagal cocok. Menulis Y-m-d membuat perilaku
     * konsisten di semua driver.
     */
    protected function tanggal(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => blank($value) ? null : Carbon::parse($value)->toDateString(),
        );
    }
}
