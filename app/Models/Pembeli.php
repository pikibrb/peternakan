<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembeli extends Model
{
    protected $table = 'pembelis';

    protected $fillable = [
        'nama_pembeli',
        'kontak',
    ];

    public function penjualans()
    {
        return $this->hasMany(Penjualan::class, 'pembeli_id');
    }
}
