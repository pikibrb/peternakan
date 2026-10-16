<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'umur_awal_minggu' => 'integer',
            'populasi_awal' => 'integer',
            'populasi_aktif' => 'integer',
        ];
    }

    public function produksiTelur()
    {
        return $this->hasMany(ProduksiTelur::class, 'kelompok_bebek_id');
    }

    public function penguranganPopulasi()
    {
        return $this->hasMany(PenguranganPopulasi::class, 'kelompok_bebek_id');
    }

    public function monitoringPertumbuhan()
    {
        return $this->hasMany(MonitoringPertumbuhan::class, 'kelompok_bebek_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'Aktif');
    }

    protected function umurMinggu(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->umur_awal_minggu + (int) $this->tanggal_masuk->diffInWeeks(now())
        );
    }

    protected function dayaHidup(): Attribute
    {
        return Attribute::get(
            fn (): float => $this->populasi_awal > 0
                ? round(($this->populasi_aktif / $this->populasi_awal) * 100, 1)
                : 0.0
        );
    }

    /**
     * Aggregate figures for all active flocks, computed in a single query.
     *
     * @return array{total_populasi: int, kelompok_aktif: int, populasi_awal: int, tingkat_deplesi: float}
     */
    public static function statistikAktif(): array
    {
        $stats = static::aktif()
            ->selectRaw('COUNT(*) as kelompok_aktif, COALESCE(SUM(populasi_aktif), 0) as total_populasi, COALESCE(SUM(populasi_awal), 0) as populasi_awal')
            ->first();

        $populasiAwal = (int) $stats->populasi_awal;
        $totalPopulasi = (int) $stats->total_populasi;

        return [
            'total_populasi' => $totalPopulasi,
            'kelompok_aktif' => (int) $stats->kelompok_aktif,
            'populasi_awal' => $populasiAwal,
            'tingkat_deplesi' => $populasiAwal > 0
                ? round((($populasiAwal - $totalPopulasi) / $populasiAwal) * 100, 1)
                : 0.0,
        ];
    }
}
