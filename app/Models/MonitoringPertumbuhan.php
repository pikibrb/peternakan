<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
