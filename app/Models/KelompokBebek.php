<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class KelompokBebek extends Model
{
    protected $table = 'kelompok_bebek';

    protected $fillable = [
        'nama_kandang',
        'jenis_bebek',
        'fase',
        'tanggal_masuk',
        'umur_awal_minggu',
        'populasi_awal',
        'populasi_aktif',
        'status',
    ];

    public function getUmurMingguAttribute()
    {
        $lamaDiKandang = Carbon::parse($this->tanggal_masuk)->diffInWeeks(now());
        
        return $this->umur_awal_minggu + $lamaDiKandang;
    }

    public function getDayaHidupAttribute()
    {
        if ($this->populasi_awal == 0) return 0;
        return round(($this->populasi_aktif / $this->populasi_awal) * 100, 1);
    }
}