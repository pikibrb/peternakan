<?php

namespace App\Support;

/**
 * Perhitungan deplesi (pengurangan populasi) bebek.
 *
 * Semua fungsi murni tanpa akses basis data supaya aturan populasi bisa diuji
 * terpisah dari halaman pencatatan.
 */
class PopulasiBebek
{
    /** Kategori pencatatan pengurangan populasi yang diakui aplikasi. */
    public const KATEGORI = ['Mati', 'Afkir', 'Hilang'];

    /** Penyebab baku agar pengurangan bisa direkap per penyebab. */
    public const PENYEBAB = [
        'Penyakit',
        'Predator',
        'Stres Panas',
        'Kanibalisme',
        'Kecelakaan',
        'Belum Diketahui',
        'Lainnya',
    ];

    /**
     * Populasi aktif setelah sekian ekor dikurangi. Nilai tidak pernah negatif
     * karena populasi aktif adalah stok nyata di kandang.
     */
    public static function setelahPengurangan(int $populasiAktif, int $jumlah): int
    {
        return max(0, max(0, $populasiAktif) - max(0, $jumlah));
    }

    /**
     * Populasi aktif setelah pencatatan dibatalkan (dihapus atau jumlahnya
     * dikoreksi turun).
     */
    public static function setelahPemulihan(int $populasiAktif, int $jumlah): int
    {
        return max(0, $populasiAktif) + max(0, $jumlah);
    }

    /**
     * Batas jumlah ekor yang masih boleh dicatat tanpa membuat populasi aktif
     * negatif. Saat mengedit, jumlah catatan lama sudah "dikembalikan" dulu.
     */
    public static function batasPencatatan(int $populasiAktif, int $jumlahLamaDikembalikan = 0): int
    {
        return max(0, max(0, $populasiAktif) + max(0, $jumlahLamaDikembalikan));
    }

    /** Persentase deplesi terhadap populasi awal (%). */
    public static function deplesiPersen(int $deplesi, int $populasiAwal): float
    {
        return $populasiAwal > 0 ? round($deplesi / $populasiAwal * 100, 1) : 0.0;
    }

    /** Daya hidup (%) = populasi aktif dibanding populasi awal. */
    public static function dayaHidupPersen(int $populasiAktif, int $populasiAwal): float
    {
        return $populasiAwal > 0 ? round($populasiAktif / $populasiAwal * 100, 1) : 0.0;
    }

    /** Estimasi nilai kerugian (Rp) dari sekian ekor yang hilang. */
    public static function estimasiKerugian(int $jumlah, float $hargaPerEkor): float
    {
        return round(max(0, $jumlah) * max(0.0, $hargaPerEkor), 2);
    }

    /** Predikat tingkat deplesi untuk ditampilkan ke peternak. */
    public static function predikatDeplesi(float $deplesiPersen): string
    {
        return match (true) {
            $deplesiPersen <= 3.0 => 'Sangat Baik',
            $deplesiPersen <= 6.0 => 'Normal',
            $deplesiPersen <= 10.0 => 'Waspada',
            default => 'Bahaya',
        };
    }
}
