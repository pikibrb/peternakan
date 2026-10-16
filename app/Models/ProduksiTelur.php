<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiTelur extends Model
{
    protected $table = 'produksi_telur';

    protected $fillable = [
        'kelompok_bebek_id',
        'tanggal_produksi',
        'jumlah_bebek_aktif',
        'total_telur',
        'telur_rusak',
        'telur_layak_jual',
    ];

    public function kelompokBebek()
    {
        return $this->belongsTo(KelompokBebek::class, 'kelompok_bebek_id');
    }
}