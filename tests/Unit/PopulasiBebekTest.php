<?php

namespace Tests\Unit;

use App\Support\PopulasiBebek;
use PHPUnit\Framework\TestCase;

class PopulasiBebekTest extends TestCase
{
    public function test_pengurangan_mengurangi_populasi_aktif(): void
    {
        $this->assertSame(495, PopulasiBebek::setelahPengurangan(500, 5));
    }

    public function test_populasi_aktif_tidak_pernah_negatif(): void
    {
        $this->assertSame(0, PopulasiBebek::setelahPengurangan(3, 10));
    }

    public function test_jumlah_negatif_diabaikan(): void
    {
        $this->assertSame(500, PopulasiBebek::setelahPengurangan(500, -7));
        $this->assertSame(500, PopulasiBebek::setelahPemulihan(500, -7));
    }

    public function test_pemulihan_mengembalikan_populasi(): void
    {
        $this->assertSame(500, PopulasiBebek::setelahPemulihan(495, 5));
    }

    public function test_batas_pencatatan_memperhitungkan_catatan_lama_yang_dikoreksi(): void
    {
        // Tanpa koreksi: batas = populasi aktif.
        $this->assertSame(495, PopulasiBebek::batasPencatatan(495));

        // Saat mengoreksi catatan 8 ekor, 8 ekor itu sudah dikembalikan lebih dulu.
        $this->assertSame(500, PopulasiBebek::batasPencatatan(492, 8));

        // Populasi kosong tidak boleh dicatat.
        $this->assertSame(0, PopulasiBebek::batasPencatatan(0, 0));
    }

    public function test_persentase_deplesi_dan_daya_hidup(): void
    {
        $this->assertSame(4.0, PopulasiBebek::deplesiPersen(20, 500));
        $this->assertSame(96.0, PopulasiBebek::dayaHidupPersen(480, 500));
    }

    public function test_persentase_aman_saat_populasi_awal_nol(): void
    {
        $this->assertSame(0.0, PopulasiBebek::deplesiPersen(20, 0));
        $this->assertSame(0.0, PopulasiBebek::dayaHidupPersen(20, 0));
    }

    public function test_estimasi_kerugian(): void
    {
        $this->assertSame(1100000.0, PopulasiBebek::estimasiKerugian(20, 55000));
        $this->assertSame(0.0, PopulasiBebek::estimasiKerugian(-3, 55000));
    }

    public function test_predikat_deplesi_pada_batas_ambang(): void
    {
        $this->assertSame('Sangat Baik', PopulasiBebek::predikatDeplesi(3.0));
        $this->assertSame('Normal', PopulasiBebek::predikatDeplesi(3.1));
        $this->assertSame('Normal', PopulasiBebek::predikatDeplesi(6.0));
        $this->assertSame('Waspada', PopulasiBebek::predikatDeplesi(6.1));
        $this->assertSame('Waspada', PopulasiBebek::predikatDeplesi(10.0));
        $this->assertSame('Bahaya', PopulasiBebek::predikatDeplesi(10.1));
    }

    public function test_daftar_kategori_dan_penyebab_baku(): void
    {
        $this->assertSame(['Mati', 'Afkir', 'Hilang'], PopulasiBebek::KATEGORI);
        $this->assertContains('Penyakit', PopulasiBebek::PENYEBAB);
        $this->assertSame(PopulasiBebek::KATEGORI, array_unique(PopulasiBebek::KATEGORI));
        $this->assertSame(PopulasiBebek::PENYEBAB, array_unique(PopulasiBebek::PENYEBAB));
    }
}
