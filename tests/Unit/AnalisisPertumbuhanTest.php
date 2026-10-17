<?php

namespace Tests\Unit;

use App\Models\KelompokBebek;
use App\Models\MonitoringPertumbuhan;
use App\Support\AnalisisPertumbuhan;
use App\Support\StandarPertumbuhan;
use Tests\TestCase;

class AnalisisPertumbuhanTest extends TestCase
{
    public function test_standar_pertumbuhan_menginterpolasi_nilai_antar_minggu(): void
    {
        // Minggu 1 ada di antara 0 (45 g) dan 2 (145 g).
        $this->assertSame(95.0, StandarPertumbuhan::beratTarget(1));

        // Nilai pada minggu yang ada di tabel dipakai apa adanya.
        $this->assertSame(850.0, StandarPertumbuhan::beratTarget(8));

        // Umur di atas tabel memakai nilai paling akhir (bebek afkir).
        $this->assertSame(1700.0, StandarPertumbuhan::beratTarget(200));

        // Input tidak valid tidak menghasilkan target.
        $this->assertNull(StandarPertumbuhan::beratTarget(null));
        $this->assertNull(StandarPertumbuhan::beratTarget('tidak-angka'));
    }

    public function test_keseragaman_dihitung_dari_koefisien_variasi(): void
    {
        // CV = 60/880 = 6.82% sehingga keseragaman 93.2%.
        $this->assertSame(93.2, AnalisisPertumbuhan::keseragaman(60, 880));

        $this->assertNull(AnalisisPertumbuhan::keseragaman(null, 880));
        $this->assertNull(AnalisisPertumbuhan::keseragaman(20, 0));
    }

    public function test_metrik_pertumbuhan_dihitung_dalam_satu_kali_analisis(): void
    {
        $kelompok = $this->kelompok();
        $sampel = collect([
            $this->sampel(['tanggal' => '2026-06-29', 'umur_hari' => 28, 'jumlah_sampel' => 30, 'berat_rata_rata' => 380, 'berat_std_dev' => 30, 'pakan_kg' => 540, 'kondisi_kesehatan' => 'Sehat']),
            $this->sampel(['tanggal' => '2026-07-13', 'umur_hari' => 42, 'jumlah_sampel' => 30, 'berat_rata_rata' => 660, 'berat_std_dev' => 45, 'pakan_kg' => 437, 'kondisi_kesehatan' => 'Sehat']),
            $this->sampel(['tanggal' => '2026-07-27', 'umur_hari' => 56, 'jumlah_sampel' => 30, 'berat_rata_rata' => 880, 'berat_std_dev' => 60, 'pakan_kg' => 538, 'kondisi_kesehatan' => 'Perlu Perhatian']),
        ]);

        $analisis = AnalisisPertumbuhan::hitung($kelompok, $sampel, 20);

        $baris = $analisis['baris'];

        $this->assertCount(3, $baris);

        // Baris pertama memakai berat standar saat check-in (DOD = 45 g) sebagai acuan.
        $this->assertEqualsWithDelta(12.0, $baris[0]['adg'], 0.05);
        $this->assertEqualsWithDelta(40.2, $baris[0]['konsumsi_ekor_hari'], 0.05);
        $this->assertEqualsWithDelta(3.36, $baris[0]['fcr'], 0.005);

        // Baris berikutnya memakai selisih antar sampling.
        $this->assertSame(14, $baris[1]['delta_hari']);
        $this->assertEqualsWithDelta(20.0, $baris[1]['adg'], 0.05);
        $this->assertEqualsWithDelta(65.0, $baris[1]['konsumsi_ekor_hari'], 0.05);
        $this->assertEqualsWithDelta(3.31, $baris[1]['fcr'], 0.005);

        $this->assertEqualsWithDelta(15.7, $baris[2]['adg'], 0.05);
        $this->assertEqualsWithDelta(1515.0, $baris[2]['pakan_kumulatif_kg'], 0.01);
        $this->assertEqualsWithDelta(3.78, $baris[2]['fcr'], 0.005);
        $this->assertSame(93.2, $baris[2]['keseragaman']);
        $this->assertSame('Perlu Perhatian', $baris[2]['kondisi_kesehatan']);

        // Pakan & berat pada periode ini memang sengaja dibedakan per baris.
        $this->assertSame(538.0, $baris[2]['pakan_kg']);

        $ringkasan = $analisis['ringkasan'];

        $this->assertSame(3, $ringkasan['jumlah_sampling']);
        $this->assertSame(880.0, $ringkasan['berat_terakhir']);
        $this->assertSame(850.0, $ringkasan['target_berat']);
        $this->assertSame('Sesuai target', $ringkasan['status_berat']);
        $this->assertEqualsWithDelta(3.5, $ringkasan['deviasi'], 0.05);
        $this->assertEqualsWithDelta(835.0, $ringkasan['pertambahan_total'], 0.05);
        $this->assertEqualsWithDelta(14.9, $ringkasan['adg'], 0.05);
        $this->assertEqualsWithDelta(1515.0, $ringkasan['pakan_kumulatif_kg'], 0.01);
        $this->assertEqualsWithDelta(56.4, $ringkasan['konsumsi_ekor_hari'], 0.05);
        $this->assertEqualsWithDelta(3.78, $ringkasan['fcr'], 0.005);
        $this->assertSame('Efisien', $ringkasan['predikat_fcr']);
        $this->assertSame('Baik', $ringkasan['predikat_keseragaman']);
        $this->assertSame(20, $ringkasan['deplesi_ekor']);
        $this->assertSame(4.0, $ringkasan['deplesi_persen']);

        // Riwayat ditampilkan terbaru lebih dulu, grafik tetap urut naik.
        $this->assertSame(56, $analisis['baris_terbaru'][0]['umur_hari']);
        $this->assertSame([28, 42, 56], array_column($baris, 'umur_hari'));

        $this->assertSame(['M4 (29/06)', 'M6 (13/07)', 'M8 (27/07)'], $analisis['grafik']['label']);
        $this->assertSame([380.0, 660.0, 880.0], $analisis['grafik']['berat']);
        $this->assertSame([380.0, 650.0, 850.0], $analisis['grafik']['target']);
        $this->assertSame([40.2, 65.0, 80.1], $analisis['grafik']['konsumsi']);
    }

    public function test_target_fcr_tidak_dibandingkan_setelah_fase_grower(): void
    {
        $this->assertSame(22, StandarPertumbuhan::mingguTerakhir('fcr_target'));

        $kelompok = $this->kelompok();
        $kelompok->setAttribute('umur_awal_minggu', 30);

        $analisis = AnalisisPertumbuhan::hitung($kelompok, collect([
            $this->sampel(['tanggal' => '2026-06-29', 'umur_hari' => 224, 'jumlah_sampel' => 30, 'berat_rata_rata' => 1700, 'berat_std_dev' => 90, 'pakan_kg' => 500, 'kondisi_kesehatan' => 'Sehat']),
            $this->sampel(['tanggal' => '2026-07-06', 'umur_hari' => 231, 'jumlah_sampel' => 30, 'berat_rata_rata' => 1710, 'berat_std_dev' => 90, 'pakan_kg' => 500, 'kondisi_kesehatan' => 'Sehat']),
        ]));

        // Minggu 33 sudah lewat fase grower: FCR tetap dihitung, tapi tidak dinilai.
        $this->assertNotNull($analisis['ringkasan']['fcr']);
        $this->assertNull($analisis['ringkasan']['fcr_target']);
        $this->assertNull($analisis['ringkasan']['predikat_fcr']);

        // Target berat masih tersedia karena tabel berat berlanjut sampai minggu 32.
        $this->assertSame(1700.0, $analisis['ringkasan']['target_berat']);
    }

    public function test_sampling_kosong_menghasilkan_ringkasan_nol(): void
    {
        $analisis = AnalisisPertumbuhan::hitung($this->kelompok(), collect());

        $this->assertSame([], $analisis['baris']);
        $this->assertSame([], $analisis['baris_terbaru']);
        $this->assertSame(0, $analisis['ringkasan']['jumlah_sampling']);
        $this->assertNull($analisis['ringkasan']['berat_terakhir']);
        $this->assertNull($analisis['ringkasan']['fcr']);
        $this->assertNull($analisis['ringkasan']['adg']);
        $this->assertNull($analisis['ringkasan']['hari_sejak_sampling']);
        $this->assertNull($analisis['ringkasan']['fcr_target']);
        $this->assertSame([], $analisis['grafik']['label']);
        $this->assertSame([], $analisis['grafik']['berat']);
    }

    private function kelompok(): KelompokBebek
    {
        $kelompok = new KelompokBebek;

        $kelompok->setAttribute('nama_kandang', 'Kandang A1');
        $kelompok->setAttribute('jenis_bebek', 'Mojosari');
        $kelompok->setAttribute('fase', 'Grower');
        $kelompok->setAttribute('tanggal_masuk', '2026-06-01');
        $kelompok->setAttribute('umur_awal_minggu', 0);
        $kelompok->setAttribute('populasi_awal', 500);
        $kelompok->setAttribute('populasi_aktif', 480);
        $kelompok->setAttribute('status', 'Aktif');

        return $kelompok;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sampel(array $attributes): MonitoringPertumbuhan
    {
        $rekaman = new MonitoringPertumbuhan;

        foreach ($attributes as $kolom => $nilai) {
            $rekaman->setAttribute($kolom, $nilai);
        }

        return $rekaman;
    }
}
