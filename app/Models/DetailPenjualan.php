<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPenjualan extends Model
{
    protected $table = 'detail_penjualans';

    protected $fillable = [
        'penjualan_id',
        'jumlah_telur',
        'harga_per_butir',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'harga_per_butir' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }
}
