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
        'berat_rata_rata',
        'pakan_kg',
        'kondisi_kesehatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat_rata_rata' => 'float',
            'pakan_kg' => 'float',
        ];
    }

    public function kelompokBebek()
    {
        return $this->belongsTo(KelompokBebek::class, 'kelompok_bebek_id');
    }
}
