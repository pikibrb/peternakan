<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    /**
     * Simpan tanggal sebagai tanggal murni (Y-m-d), bukan string datetime.
     * MySQL memotong otomatis isi kolom DATE, tetapi SQLite menyimpannya apa
     * adanya sehingga pengecekan unik "satu kandang satu produksi per hari"
     * (Rule::unique pada form) gagal cocok. Menulis Y-m-d membuat perilaku
     * konsisten di semua driver.
     */
    protected function tanggalProduksi(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => blank($value) ? null : Carbon::parse($value)->toDateString(),
        );
    }
}
