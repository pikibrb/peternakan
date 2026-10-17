<?php

namespace App\Support;

use App\Models\KelompokBebek;
use App\Models\MonitoringPertumbuhan;
use Illuminate\Support\Collection;

/**
 * Mesin perhitungan performa pertumbuhan satu kelompok (flock).
 *
 * Semua angka (ADG, FCR, konsumsi pakan per ekor, keseragaman, dan simpangan
 * terhadap standar) dihitung dalam SATU kali iterasi atas koleksi sampling yang
 * sudah dimuat dari database. Tidak ada query di dalam perulangan, sehingga
 * jumlah query halaman tetap konstan berapa pun banyaknya baris sampling.
 *
 * Hasilnya juga sudah berisi data siap-pakai untuk grafik supaya view tidak
 * perlu menyusun ulang deret angka dengan perulangan sendiri.
 */
class AnalisisPertumbuhan
{
    /**
     * @param  Collection<int, MonitoringPertumbuhan>  $sampel  Diurutkan naik berdasarkan tanggal.
     */
    public static function hitung(KelompokBebek $kelompok, Collection $sampel, mixed $deplesiEkor = 0): array
    {
        $populasi = max(1, (int) $kelompok->populasi_aktif);
        $umurAwalHari = max(0, (int) $kelompok->umur_awal_minggu * 7);
        $toleransi = StandarPertumbuhan::ambang('deviasi_toleransi', 5.0);

        // Berat acuan awal = berat standar pada umur saat kelompok masuk. Dengan
        // acuan ini ADG baris pertama tetap bisa dihitung dan FCR kumulatif
        // sebanding dengan standar yang dihitung sejak check-in.
        $beratAwal = StandarPertumbuhan::beratTarget((int) $kelompok->umur_awal_minggu);

        $baris = [];
        $beratSebelumnya = $beratAwal;
        $umurSebelumnya = $umurAwalHari;
        $pakanKumulatif = 0.0;
        $pertambahanKumulatifKg = 0.0;

        foreach ($sampel as $record) {
            $umurHari = max(0, (int) $record->umur_hari);
            $deltaHari = max(1, $umurHari - $umurSebelumnya);
            $berat = (float) $record->berat_rata_rata;
            $pakan = (float) $record->pakan_kg;

            $beratAwal ??= $berat;

            $pertambahanEkor = $beratSebelumnya === null ? 0.0 : round($berat - $beratSebelumnya, 2);

            $pakanKumulatif += $pakan;
            $pertambahanKumulatifKg += max(0.0, $pertambahanEkor) * $populasi / 1000;

            $umurMinggu = intdiv($umurHari, 7);
            $targetBerat = StandarPertumbuhan::beratTarget($umurMinggu);
            $deviasi = $targetBerat === null || $targetBerat <= 0
                ? null
                : round((($berat - $targetBerat) / $targetBerat) * 100, 1);

            $baris[] = [
                'id' => $record->id,
                'tanggal' => $record->tanggal,
                'tanggal_label' => $record->tanggal?->translatedFormat('d M Y') ?? '-',
                'umur_hari' => $umurHari,
                'umur_minggu' => $umurMinggu,
                'jumlah_sampel' => (int) $record->jumlah_sampel,
                'berat' => round($berat, 1),
                'berat_std_dev' => $record->berat_std_dev === null ? null : round((float) $record->berat_std_dev, 1),
                'delta_hari' => $deltaHari,
                // ADG = pertambahan berat per ekor dibagi jarak hari antar sampling.
                'adg' => $pertambahanEkor <= 0 ? null : round($pertambahanEkor / $deltaHari, 1),
                'pakan_kg' => round($pakan, 2),
                'pakan_kumulatif_kg' => round($pakanKumulatif, 2),
                // Pakan pada periode ini dibagi populasi aktif dan jumlah hari.
                'konsumsi_ekor_hari' => round($pakan * 1000 / ($populasi * $deltaHari), 1),
                // FCR kumulatif = pakan kumulatif / pertambahan berat kumulatif.
                'fcr' => $pertambahanKumulatifKg > 0 ? round($pakanKumulatif / $pertambahanKumulatifKg, 2) : null,
                'keseragaman' => self::keseragaman($record->berat_std_dev, $berat),
                'kondisi_kesehatan' => $record->kondisi_kesehatan,
                'catatan' => $record->catatan,
                'target_berat' => $targetBerat,
                'deviasi' => $deviasi,
                'status_berat' => self::statusBerat($deviasi, $toleransi),
                'di_bawah_target' => $deviasi !== null && $deviasi < -$toleransi,
            ];

            $umurSebelumnya = $umurHari;
            $beratSebelumnya = $berat;
        }

        $terakhir = $baris === [] ? null : $baris[array_key_last($baris)];
        $hariPeriode = $terakhir === null ? 0 : max(0, $terakhir['umur_hari'] - $umurAwalHari);
        $pertambahanTotal = $terakhir === null || $beratAwal === null
            ? null
            : round($terakhir['berat'] - $beratAwal, 1);

        $hariSejakSampling = $terakhir === null || $terakhir['tanggal'] === null
            ? null
            : max(0, (int) $terakhir['tanggal']->diffInDays(today(), false));

        // Target FCR berat hanya berlaku sampai akhir fase grower; setelahnya
        // konversi pakan diukur per kg telur sehingga tidak dibandingkan lagi.
        $mingguTerakhirFcr = StandarPertumbuhan::mingguTerakhir('fcr_target');
        $fcrTarget = $terakhir === null || ($mingguTerakhirFcr !== null && $terakhir['umur_minggu'] > $mingguTerakhirFcr)
            ? null
            : StandarPertumbuhan::fcrTarget($terakhir['umur_minggu']);

        return [
            'baris' => $baris,
            // Tabel riwayat menampilkan sampling terbaru lebih dulu.
            'baris_terbaru' => array_reverse($baris),
            'ringkasan' => [
                'populasi_aktif' => (int) $kelompok->populasi_aktif,
                'umur_awal_hari' => $umurAwalHari,
                'jumlah_sampling' => count($baris),
                'tanggal_terakhir' => $terakhir['tanggal'] ?? null,
                'umur_terakhir_hari' => $terakhir['umur_hari'] ?? null,
                'umur_terakhir_minggu' => $terakhir['umur_minggu'] ?? null,
                'berat_awal' => $beratAwal,
                'berat_terakhir' => $terakhir['berat'] ?? null,
                'target_berat' => $terakhir['target_berat'] ?? null,
                'deviasi' => $terakhir['deviasi'] ?? null,
                'status_berat' => $terakhir['status_berat'] ?? null,
                'pertambahan_total' => $pertambahanTotal,
                'adg' => $pertambahanTotal !== null && $hariPeriode > 0
                    ? round($pertambahanTotal / $hariPeriode, 1)
                    : null,
                'pakan_kumulatif_kg' => round($pakanKumulatif, 2),
                'konsumsi_ekor_hari' => $hariPeriode > 0
                    ? round($pakanKumulatif * 1000 / ($populasi * $hariPeriode), 1)
                    : null,
                'konsumsi_target' => $terakhir === null
                    ? null
                    : StandarPertumbuhan::konsumsiTarget($terakhir['umur_minggu']),
                'fcr' => $terakhir['fcr'] ?? null,
                'fcr_target' => $fcrTarget,
                'keseragaman' => $terakhir['keseragaman'] ?? null,
                'predikat_keseragaman' => self::predikatKeseragaman($terakhir['keseragaman'] ?? null),
                'predikat_fcr' => self::predikatFcr($terakhir['fcr'] ?? null, $fcrTarget),
                'ambang_keseragaman_baik' => StandarPertumbuhan::ambang('keseragaman_baik', 80.0),
                'ambang_keseragaman_cukup' => StandarPertumbuhan::ambang('keseragaman_cukup', 70.0),
                'deplesi_ekor' => (int) round((float) $deplesiEkor),
                'deplesi_persen' => (int) $kelompok->populasi_awal > 0
                    ? round((float) $deplesiEkor / (int) $kelompok->populasi_awal * 100, 1)
                    : 0.0,
                'hari_sejak_sampling' => $hariSejakSampling,
                'selang_sampling_ideal' => (int) StandarPertumbuhan::ambang('selang_sampling_ideal', 7.0),
                'sampling_terlambat' => $hariSejakSampling !== null
                    && $hariSejakSampling > StandarPertumbuhan::ambang('selang_sampling_ideal', 7.0),
            ],
            // Deret angka siap dikirim ke Chart.js tanpa perulangan di view.
            'grafik' => [
                'label' => array_map(
                    static fn (array $r): string => 'M'.$r['umur_minggu'].' ('.$r['tanggal']?->format('d/m').')',
                    $baris
                ),
                'berat' => array_column($baris, 'berat'),
                'target' => array_column($baris, 'target_berat'),
                'konsumsi' => array_column($baris, 'konsumsi_ekor_hari'),
                'fcr' => array_column($baris, 'fcr'),
            ],
        ];
    }

    /**
     * Keseragaman populasi = 100 - koefisien variasi berat sampel.
     */
    public static function keseragaman(mixed $standarDeviasi, mixed $rataRata): ?float
    {
        if (! is_numeric($standarDeviasi) || ! is_numeric($rataRata) || (float) $rataRata <= 0) {
            return null;
        }

        $variasi = (float) $standarDeviasi / (float) $rataRata * 100;

        return round(max(0.0, 100 - $variasi), 1);
    }

    private static function statusBerat(?float $deviasi, float $toleransi): ?string
    {
        if ($deviasi === null) {
            return null;
        }

        if ($deviasi > $toleransi) {
            return 'Di atas target';
        }

        if ($deviasi < -$toleransi) {
            return 'Di bawah target';
        }

        return 'Sesuai target';
    }

    private static function predikatKeseragaman(?float $keseragaman): ?string
    {
        if ($keseragaman === null) {
            return null;
        }

        if ($keseragaman >= StandarPertumbuhan::ambang('keseragaman_baik', 80.0)) {
            return 'Baik';
        }

        if ($keseragaman >= StandarPertumbuhan::ambang('keseragaman_cukup', 70.0)) {
            return 'Cukup';
        }

        return 'Kurang';
    }

    private static function predikatFcr(?float $fcr, ?float $target): ?string
    {
        if ($fcr === null || $target === null) {
            return null;
        }

        return $fcr <= $target ? 'Efisien' : 'Boros';
    }
}
