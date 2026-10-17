<?php

namespace App\Support;

/**
 * Jembatan antara config/standar_pertumbuhan.php dan pemakaiannya di halaman
 * Monitoring Pertumbuhan.
 *
 * Tabel referensi hanya berisi sebagian minggu, jadi nilainya diinterpolasi.
 * Agar pencarian target tidak mengulang proses interpolasi untuk setiap baris
 * sampling, tabel hasil interpolasi disusun sekali per minggu (0-104) lalu
 * disimpan di cache statis — pemanggilan berikutnya hanya O(1).
 */
class StandarPertumbuhan
{
    /**
     * Umur maksimum (minggu) yang disiapkan di tabel interpolasi.
     */
    private const MINGGU_MAKSIMUM = 104;

    /**
     * @var array<string, array<int, float>>|null
     */
    private static ?array $tabel = null;

    /**
     * Nilai target untuk sebuah kunci tabel referensi.
     */
    public static function target(string $kunci, mixed $umurMinggu): ?float
    {
        if (! is_numeric($umurMinggu)) {
            return null;
        }

        $umur = max(0, (int) $umurMinggu);
        $tabel = self::tabel($kunci);

        if ($tabel === []) {
            return null;
        }

        if (isset($tabel[$umur])) {
            return $tabel[$umur];
        }

        // Umur di luar tabel (bebek sudah afkir) memakai nilai paling akhir.
        return $tabel[array_key_last($tabel)];
    }

    public static function beratTarget(mixed $umurMinggu): ?float
    {
        return self::target('berat_target', $umurMinggu);
    }

    public static function fcrTarget(mixed $umurMinggu): ?float
    {
        return self::target('fcr_target', $umurMinggu);
    }

    public static function konsumsiTarget(mixed $umurMinggu): ?float
    {
        return self::target('konsumsi_harian_target', $umurMinggu);
    }

    /**
     * Minggu terakhir yang punya nilai pada tabel referensi.
     */
    public static function mingguTerakhir(string $kunci): ?int
    {
        $referensi = config("standar_pertumbuhan.{$kunci}");

        if (! is_array($referensi) || $referensi === []) {
            return null;
        }

        return (int) array_key_last($referensi);
    }

    /**
     * Nilai ambang batas penilaian.
     */
    public static function ambang(string $kunci, float $bawaan = 0.0): float
    {
        $nilai = config("standar_pertumbuhan.ambang.{$kunci}");

        return is_numeric($nilai) ? (float) $nilai : $bawaan;
    }

    /**
     * Tabel referensi yang sudah diinterpolasi per minggu (disusun sekali saja).
     *
     * @return array<int, float>
     */
    private static function tabel(string $kunci): array
    {
        if (self::$tabel === null) {
            self::$tabel = [];
        }

        if (! array_key_exists($kunci, self::$tabel)) {
            self::$tabel[$kunci] = self::susun($kunci);
        }

        return self::$tabel[$kunci];
    }

    /**
     * @return array<int, float>
     */
    private static function susun(string $kunci): array
    {
        $referensi = config("standar_pertumbuhan.{$kunci}", []);

        if (! is_array($referensi) || $referensi === []) {
            return [];
        }

        ksort($referensi);

        $minggu = array_keys($referensi);
        $pertama = (int) $minggu[0];
        $terakhir = (int) $minggu[count($minggu) - 1];

        $tabel = [];

        for ($umur = 0; $umur <= self::MINGGU_MAKSIMUM; $umur++) {
            $tabel[$umur] = self::interpolasi($referensi, $minggu, $umur, $pertama, $terakhir);
        }

        return $tabel;
    }

    /**
     * @param  array<int, mixed>  $referensi
     * @param  array<int, mixed>  $minggu
     */
    private static function interpolasi(array $referensi, array $minggu, int $umur, int $pertama, int $terakhir): float
    {
        if ($umur <= $pertama) {
            return (float) $referensi[$pertama];
        }

        if ($umur >= $terakhir) {
            return (float) $referensi[$terakhir];
        }

        $bawah = $pertama;

        foreach ($minggu as $atas) {
            if ((int) $atas >= $umur) {
                $rentang = (int) $atas - $bawah;

                if ($rentang <= 0) {
                    return (float) $referensi[$atas];
                }

                $rasio = ($umur - $bawah) / $rentang;

                return round(
                    (float) $referensi[$bawah] + $rasio * ((float) $referensi[$atas] - (float) $referensi[$bawah]),
                    2
                );
            }

            $bawah = (int) $atas;
        }

        return (float) $referensi[$terakhir];
    }
}
