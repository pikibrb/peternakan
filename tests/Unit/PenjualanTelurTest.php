<?php

namespace Tests\Unit;

use App\Support\PenjualanTelur;
use PHPUnit\Framework\TestCase;

class PenjualanTelurTest extends TestCase
{
    public function test_subtotal_baris_nota(): void
    {
        $this->assertSame(75000.0, PenjualanTelur::subtotal(30, 2500));
        $this->assertSame(0.0, PenjualanTelur::subtotal(30, 0));
    }

    public function test_subtotal_aman_untuk_nilai_negatif(): void
    {
        $this->assertSame(0.0, PenjualanTelur::subtotal(-5, 2500));
        $this->assertSame(0.0, PenjualanTelur::subtotal(5, -2500));
    }

    public function test_harga_rata_rata_per_butir(): void
    {
        $this->assertSame(2425.0, PenjualanTelur::hargaRataRata(97000, 40));
        $this->assertNull(PenjualanTelur::hargaRataRata(97000, 0));
    }

    public function test_harga_rata_rata_pada_nota_bebas_biaya(): void
    {
        $this->assertSame(0.0, PenjualanTelur::hargaRataRata(0, 10));
    }

    public function test_stok_tersedia_adalah_produksi_layak_jual_dikurangi_terjual(): void
    {
        $this->assertSame(1, PenjualanTelur::stokTersedia(30, 29));
        $this->assertSame(0, PenjualanTelur::stokTersedia(30, 45));
    }

    public function test_persentase_terjual(): void
    {
        $this->assertSame(96.7, PenjualanTelur::persenTerjual(29, 30));
        $this->assertSame(0.0, PenjualanTelur::persenTerjual(10, 0));
    }

    public function test_piutang_adalah_nilai_yang_belum_dibayar(): void
    {
        $this->assertSame(50000.0, PenjualanTelur::piutang(97000, 47000));
        $this->assertSame(0.0, PenjualanTelur::piutang(47000, 97000));
    }

    public function test_daftar_status_pembayaran_baku(): void
    {
        $this->assertSame(['Lunas', 'Belum Lunas'], PenjualanTelur::STATUS);
    }
}
