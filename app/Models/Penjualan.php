<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    protected $table = 'penjualans';

    protected $fillable = [
        'pembeli_id',
        'tanggal_penjualan',
        'total_penjualan',
        'status_pembayaran',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penjualan' => 'date',
            'total_penjualan' => 'decimal:2',
        ];
    }

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'pembeli_id');
    }

    public function detailPenjualans()
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }

    public function scopeLunas($query)
    {
        return $query->where('status_pembayaran', 'Lunas');
    }

    public function scopeBelumLunas($query)
    {
        return $query->where('status_pembayaran', 'Belum Lunas');
    }
}
