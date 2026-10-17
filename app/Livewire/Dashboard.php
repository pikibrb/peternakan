<?php

namespace App\Livewire;

use App\Models\DetailPenjualan;
use App\Models\KelompokBebek;
use App\Models\Penjualan;
use App\Models\ProduksiTelur;
use App\Support\PenjualanTelur;
use App\Support\PoultryFormulas;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    /** Ringkasan populasi aktif (satu query agregat). */
    public array $populasi = [];

    public int $produksiHariIni = 0;

    public float $henDayHariIni = 0.0;

    public int $stokTelur = 0;

    public float $omzetBulanIni = 0.0;

    public float $piutang = 0.0;

    public array $chartProduksiData = [];

    public array $chartPenjualanData = [];

    public function mount(): void
    {
        $this->populasi = KelompokBebek::statistikAktif();

        $hariIni = ProduksiTelur::query()
            ->whereDate('tanggal_produksi', today())
            ->selectRaw('
                COALESCE(SUM(telur_dihasilkan), 0) AS telur,
                COALESCE(SUM(telur_layak_jual), 0) AS layak,
                COALESCE(SUM(jumlah_bebek), 0)     AS bebek
            ')
            ->first();

        $this->produksiHariIni = (int) $hariIni->layak;
        $this->henDayHariIni = PoultryFormulas::henDayPct((int) $hariIni->telur, (int) $hariIni->bebek);

        $this->stokTelur = PenjualanTelur::stokTersedia(
            (int) ProduksiTelur::sum('telur_layak_jual'),
            (int) DetailPenjualan::sum('jumlah_telur'),
        );

        $bulanIni = Penjualan::query()
            ->whereBetween('tanggal_penjualan', [today()->startOfMonth(), today()->endOfMonth()])
            ->selectRaw('
                COALESCE(SUM(total_penjualan), 0) AS omzet,
                COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN total_penjualan ELSE 0 END), 0) AS lunas
            ', [PenjualanTelur::STATUS[0]])
            ->first();

        $this->omzetBulanIni = (float) $bulanIni->omzet;
        $this->piutang = PenjualanTelur::piutang($this->omzetBulanIni, (float) $bulanIni->lunas);

        $this->muatGrafik();
    }

    /**
     * Dua query agregat untuk grafik tujuh hari terakhir (satu per grafik).
     */
    private function muatGrafik(): void
    {
        $produksi = ProduksiTelur::query()
            ->whereDate('tanggal_produksi', '>=', today()->subDays(6))
            ->groupBy('tanggal_produksi')
            ->orderBy('tanggal_produksi')
            ->selectRaw('tanggal_produksi, SUM(telur_layak_jual) AS layak')
            ->get()
            ->mapWithKeys(fn ($baris) => [(string) $baris->tanggal_produksi?->toDateString() => (int) $baris->layak])
            ->all();

        $this->chartProduksiData = $this->susunTujuhHari($produksi);

        $penjualan = Penjualan::query()
            ->lunas()
            ->whereDate('tanggal_penjualan', '>=', today()->subDays(6))
            ->groupBy('tanggal_penjualan')
            ->orderBy('tanggal_penjualan')
            ->selectRaw('tanggal_penjualan, SUM(total_penjualan) AS total')
            ->get()
            ->mapWithKeys(fn ($baris) => [(string) $baris->tanggal_penjualan?->toDateString() => (float) $baris->total])
            ->all();

        $this->chartPenjualanData = $this->susunTujuhHari($penjualan);
    }

    /**
     * Lengkapi tujuh hari terakhir untuk grafik: hari tanpa catatan tetap tampil
     * sebagai nol supaya jarak antarhari tidak menyesatkan.
     *
     * @param  array<string, int|float>  $nilaiPerTanggal
     * @return array{labels: list<string>, values: list<int|float>}
     */
    private function susunTujuhHari(array $nilaiPerTanggal): array
    {
        $label = [];
        $nilai = [];

        foreach (range(6, 0) as $selisih) {
            $tanggal = today()->subDays($selisih);

            $label[] = $tanggal->format('d/m');
            $nilai[] = $nilaiPerTanggal[$tanggal->toDateString()] ?? 0;
        }

        return ['labels' => $label, 'values' => $nilai];
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
