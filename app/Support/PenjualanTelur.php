<?php

namespace App\Support;

/**
 * Perhitungan penjualan telur.
 *
 * Semua fungsi murni tanpa akses basis data supaya nilai nota, harga rata-rata
 * dan sisa stok bisa diuji terpisah dari halaman penjualan.
 */
class PenjualanTelur
{
    /** Status pembayaran yang diakui aplikasi. */
    public const STATUS = ['Lunas', 'Belum Lunas'];

    /** Subtotal satu baris nota = jumlah butir x harga per butir. */
    public static function subtotal(int $jumlahTelur, float $hargaPerButir): float
    {
        return round(max(0, $jumlahTelur) * max(0.0, $hargaPerButir), 2);
    }

    /** Harga rata-rata per butir dari total nilai dan total butir. */
    public static function hargaRataRata(float $total, int $jumlahTelur): ?float
    {
        return $jumlahTelur > 0 ? round($total / $jumlahTelur, 2) : null;
    }

    /**
     * Sisa telur yang masih bisa dijual = telur layak jual hasil produksi
     * dikurangi telur yang sudah tercatat terjual.
     */
    public static function stokTersedia(int $produksiLayakJual, int $terjual): int
    {
        return max(0, max(0, $produksiLayakJual) - max(0, $terjual));
    }

    /** Persentase telur terjual terhadap telur layak jual (%). */
    public static function persenTerjual(int $terjual, int $produksiLayakJual): float
    {
        return $produksiLayakJual > 0 ? round($terjual / $produksiLayakJual * 100, 1) : 0.0;
    }

    /** Piutang = nilai nota yang statusnya belum lunas. */
    public static function piutang(float $total, float $lunas): float
    {
        return round(max(0.0, $total - $lunas), 2);
    }
}
