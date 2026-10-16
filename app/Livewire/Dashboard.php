<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\KelompokBebek;
use App\Models\ProduksiTelur;
use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public $totalPopulasi = 0;
    public $produksiHariIni = 0;
    public $stokTelur = 0;
    public $totalPenjualan = 0;
    public $chartProduksiData = [];
    public $chartPenjualanData = [];

    public function mount()
    {
        $this->totalPopulasi = KelompokBebek::where('status', 'Aktif')->sum('populasi_aktif');

        $this->produksiHariIni = ProduksiTelur::whereDate('tanggal_produksi', today())
            ->sum('telur_layak_jual');

        $totalTelurDiproduksi = ProduksiTelur::sum('telur_layak_jual');
        $totalTelurTerjual = DetailPenjualan::sum('jumlah_telur');
        $this->stokTelur = max(0, $totalTelurDiproduksi - $totalTelurTerjual);

        $this->totalPenjualan = Penjualan::where('status_pembayaran', 'Lunas')
            ->sum('total_penjualan');

        $this->loadChartData();
    }

    public function loadChartData()
    {
        $produksi7Hari = ProduksiTelur::select('tanggal_produksi', DB::raw('SUM(telur_layak_jual) as total'))
            ->groupBy('tanggal_produksi')
            ->orderBy('tanggal_produksi', 'desc')
            ->take(7)
            ->get()
            ->reverse();

        $this->chartProduksiData = [
            'labels' => $produksi7Hari->pluck('tanggal_produksi')->map(fn($d) => date('d/m', strtotime($d)))->toArray(),
            'values' => $produksi7Hari->pluck('total')->toArray(),
        ];

        $penjualan7Hari = Penjualan::select('tanggal_penjualan', DB::raw('SUM(total_penjualan) as total'))
            ->where('status_pembayaran', 'Lunas')
            ->groupBy('tanggal_penjualan')
            ->orderBy('tanggal_penjualan', 'desc')
            ->take(7)
            ->get()
            ->reverse();

        $this->chartPenjualanData = [
            'labels' => $penjualan7Hari->pluck('tanggal_penjualan')->map(fn($d) => date('d/m', strtotime($d)))->toArray(),
            'values' => $penjualan7Hari->pluck('total')->toArray(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}