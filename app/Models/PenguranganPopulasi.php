<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenguranganPopulasi extends Model
{
    protected $table = 'pengurangan_populasis';

    protected $fillable = [
        'kelompok_bebek_id',
        'tanggal',
        'kategori',
        'jumlah',
        'penyebab',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function kelompokBebek()
    {
        return $this->belongsTo(KelompokBebek::class, 'kelompok_bebek_id');
    }
}
