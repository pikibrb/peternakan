<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiTelur extends Model
{
    protected $table = 'produksi_telur';

    protected $fillable = [
        'kelompok_bebek_id',
        'tanggal_produksi',
        'jumlah_bebek',
        'telur_dihasilkan',
        'telur_rusak',
        'telur_layak_jual',
        'pakan_kg',
        'berat_butir_g',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_produksi' => 'date',
            'jumlah_bebek' => 'integer',
            'telur_dihasilkan' => 'integer',
            'telur_rusak' => 'integer',
            'telur_layak_jual' => 'integer',
            'pakan_kg' => 'float',
            'berat_butir_g' => 'float',
        ];
    }

    public function kelompokBebek()
    {
        return $this->belongsTo(KelompokBebek::class, 'kelompok_bebek_id');
    }
}
