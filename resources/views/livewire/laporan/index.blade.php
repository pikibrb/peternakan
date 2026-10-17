<?php

use App\Models\DetailPenjualan;
use App\Models\KelompokBebek;
use App\Models\MonitoringPertumbuhan;
use App\Models\PenguranganPopulasi;
use App\Models\Penjualan;
use App\Models\ProduksiTelur;
use App\Support\PenjualanTelur;
use App\Support\PopulasiBebek;
use App\Support\PoultryFormulas;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    /** Jenis riwayat yang bisa disaring; '' berarti semua jenis. */
    public const JENIS = ['', 'Produksi', 'Sampling', 'Deplesi', 'Penjualan'];

    public $start_date = '';

    public $end_date = '';

    /** '' = seluruh kandang. */
    public $kelompokId = '';

    public $jenisRiwayat = '';

    /** Jumlah catatan riwayat yang ditampilkan per jenis (tombol "muat lebih banyak"). */
    public $batas = 25;

    public function mount(): void
    {
        $this->start_date = today()->subDays(29)->toDateString();
        $this->end_date = today()->toDateString();
    }

    public function updating(string $properti): void
    {
        if (in_array($properti, ['start_date', 'end_date', 'kelompokId', 'jenisRiwayat'], true)) {
            $this->batas = 25;
        }
    }

    public function muatLebihBanyak(): void
    {
        $this->batas += 25;
    }

    public function cetak(): void
    {
        $this->dispatch('cetak-laporan');
    }

    /** Kandang yang ikut dihitung: satu kandang terpilih atau seluruh kandang. */
    private function daftarKandang()
    {
        return KelompokBebek::query()
            ->when($this->kelompokId !== '', fn ($q) => $q->whereKey($this->kelompokId))
            ->orderBy('nama_kandang')
            ->get();
    }

    private function rentang($query)
    {
        return $query
            ->when($this->start_date, fn ($q) => $q->whereDate('tanggal', '>=', $this->start_date))
            ->when($this->end_date, fn ($q) => $q->whereDate('tanggal', '<=', $this->end_date));
    }

    public function with(): array
    {
        $kandang = $this->daftarKandang();
        $idKandang = $kandang->pluck('id')->all();

        return [
            'kandang' => $kandang,
            'ringkasan' => $this->ringkasan($idKandang),
            'perKandang' => $this->perKandang($kandang),
            'riwayat' => $this->riwayat($idKandang),
            'jenis' => self::JENIS,
            'periode' => Carbon::parse($this->start_date)->format('d/m/Y').' – '.Carbon::parse($this->end_date)->format('d/m/Y'),
        ];
    }

    /**
     * Seluruh angka ringkasan dihitung dengan satu query agregat per sumber data,
     * bukan dengan menelusuri baris di PHP.
     */
    private function ringkasan(array $idKandang): array
    {
        $produksi = ProduksiTelur::query()
            ->whereIn('kelompok_bebek_id', $idKandang)
            ->when($this->start_date, fn ($q) => $q->whereDate('tanggal_produksi', '>=', $this->start_date))
            ->when($this->end_date, fn ($q) => $q->whereDate('tanggal_produksi', '<=', $this->end_date))
            ->selectRaw('
                COALESCE(SUM(telur_dihasilkan), 0) AS telur,
                COALESCE(SUM(telur_layak_jual), 0) AS layak,
                COALESCE(SUM(telur_rusak), 0)      AS rusak,
                COALESCE(SUM(jumlah_bebek), 0)     AS bebek,
                COALESCE(SUM(pakan_kg), 0)         AS pakan,
                COUNT(*)                           AS catatan,
                COUNT(DISTINCT tanggal_produksi)   AS hari
            ')
            ->first();

        $deplesi = $this->rentang(PenguranganPopulasi::query()->whereIn('kelompok_bebek_id', $idKandang))
            ->selectRaw('COALESCE(SUM(jumlah), 0) AS ekor, COUNT(*) AS catatan')
            ->first();

        $sampling = $this->rentang(MonitoringPertumbuhan::query()->whereIn('kelompok_bebek_id', $idKandang))
            ->selectRaw('
                COUNT(*)                                AS catatan,
                COALESCE(SUM(jumlah_sampel), 0)         AS sampel,
                COALESCE(SUM(pakan_kg), 0)              AS pakan
            ')
            ->first();

        $samplingTerakhir = $this->rentang(MonitoringPertumbuhan::query()->whereIn('kelompok_bebek_id', $idKandang))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first();

        $nota = Penjualan::query()
            ->when($this->start_date, fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $this->start_date))
            ->when($this->end_date, fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $this->end_date))
            ->selectRaw('
                COUNT(*) AS jumlah_nota,
                COALESCE(SUM(total_penjualan), 0) AS omzet,
                COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN total_penjualan ELSE 0 END), 0) AS omzet_lunas
            ', [PenjualanTelur::STATUS[0]])
            ->first();

        $telurTerjual = (int) DetailPenjualan::query()
            ->whereHas('penjualan', function ($q) {
                $q->when($this->start_date, fn ($qq) => $qq->whereDate('tanggal_penjualan', '>=', $this->start_date))
                    ->when($this->end_date, fn ($qq) => $qq->whereDate('tanggal_penjualan', '<=', $this->end_date));
            })
            ->sum('jumlah_telur');

        $telur = (int) $produksi->telur;
        $layak = (int) $produksi->layak;
        $bebek = (int) $produksi->bebek;
        $pakan = (float) $produksi->pakan;
        $omzet = (float) $nota->omzet;
        $omzetLunas = (float) $nota->omzet_lunas;
        $populasi = KelompokBebek::statistikAktif();

        return [
            'telur' => $telur,
            'layak' => $layak,
            'rusak' => (int) $produksi->rusak,
            'bebek' => $bebek,
            'pakan' => $pakan,
            'hari_produksi' => (int) $produksi->hari,
            'catatan_produksi' => (int) $produksi->catatan,
            'hen_day' => PoultryFormulas::weightedHenDayPct($telur, $bebek),
            'saleable_pct' => PoultryFormulas::saleablePct($layak, $telur),
            'feed_per_dozen' => PoultryFormulas::feedPerDozenKilograms($pakan > 0 ? $pakan : null, $layak),
            'deplesi_ekor' => (int) $deplesi->ekor,
            'deplesi_catatan' => (int) $deplesi->catatan,
            'deplesi_persen' => PopulasiBebek::deplesiPersen((int) $deplesi->ekor, (int) $populasi['populasi_awal']),
            'sampling_catatan' => (int) $sampling->catatan,
            'sampling_sampel' => (int) $sampling->sampel,
            'sampling_terakhir' => $samplingTerakhir,
            'nota' => (int) $nota->jumlah_nota,
            'omzet' => $omzet,
            'omzet_lunas' => $omzetLunas,
            'piutang' => PenjualanTelur::piutang($omzet, $omzetLunas),
            'telur_terjual' => $telurTerjual,
            'terjual_pct' => PenjualanTelur::persenTerjual($telurTerjual, $layak),
            'populasi_awal' => (int) $populasi['populasi_awal'],
            'populasi_aktif' => (int) $populasi['total_populasi'],
            'daya_hidup' => PopulasiBebek::dayaHidupPersen((int) $populasi['total_populasi'], (int) $populasi['populasi_awal']),
        ];
    }

    /**
     * Performa per kandang pada periode terpilih: tiga query agregat yang
     * dikelompokkan per kandang lalu digabung ke koleksi kandang yang sudah ada.
     */
    private function perKandang($kandang): array
    {
        $idKandang = $kandang->pluck('id')->all();

        $produksi = ProduksiTelur::query()
            ->whereIn('kelompok_bebek_id', $idKandang)
            ->when($this->start_date, fn ($q) => $q->whereDate('tanggal_produksi', '>=', $this->start_date))
            ->when($this->end_date, fn ($q) => $q->whereDate('tanggal_produksi', '<=', $this->end_date))
            ->groupBy('kelompok_bebek_id')
            ->selectRaw('kelompok_bebek_id, SUM(telur_dihasilkan) AS telur, SUM(telur_layak_jual) AS layak, SUM(jumlah_bebek) AS bebek, COUNT(*) AS catatan')
            ->get()
            ->keyBy('kelompok_bebek_id');

        $deplesi = $this->rentang(PenguranganPopulasi::query()->whereIn('kelompok_bebek_id', $idKandang))
            ->groupBy('kelompok_bebek_id')
            ->selectRaw('kelompok_bebek_id, SUM(jumlah) AS ekor')
            ->get()
            ->keyBy('kelompok_bebek_id');

        $sampling = $this->rentang(MonitoringPertumbuhan::query()->whereIn('kelompok_bebek_id', $idKandang))
            ->groupBy('kelompok_bebek_id')
            ->selectRaw('kelompok_bebek_id, COUNT(*) AS catatan')
            ->get()
            ->keyBy('kelompok_bebek_id');

        return $kandang->map(function (KelompokBebek $flock) use ($produksi, $deplesi, $sampling) {
            $barisProduksi = $produksi->get($flock->id);
            $telur = (int) ($barisProduksi->telur ?? 0);
            $bebek = (int) ($barisProduksi->bebek ?? 0);
            $ekorHilang = (int) ($deplesi->get($flock->id)->ekor ?? 0);

            return [
                'id' => $flock->id,
                'nama_kandang' => $flock->nama_kandang,
                'jenis_bebek' => $flock->jenis_bebek,
                'fase' => $flock->fase,
                'status' => $flock->status,
                'populasi_aktif' => (int) $flock->populasi_aktif,
                'telur' => $telur,
                'layak' => (int) ($barisProduksi->layak ?? 0),
                'hen_day' => PoultryFormulas::weightedHenDayPct($telur, $bebek),
                'deplesi_ekor' => $ekorHilang,
                'deplesi_persen' => PopulasiBebek::deplesiPersen($ekorHilang, (int) $flock->populasi_awal),
                'sampling_catatan' => (int) ($sampling->get($flock->id)->catatan ?? 0),
                'catatan_produksi' => (int) ($barisProduksi->catatan ?? 0),
            ];
        })->all();
    }

    /**
     * Riwayat aktivitas gabungan: empat query terbaru (masing-masing dibatasi),
     * digabung dan diurutkan di PHP. Tidak ada query per baris.
     */
    private function riwayat(array $idKandang): array
    {
        $jenis = $this->jenisRiwayat;
        $batas = $this->batas;
        $aktivitas = [];

        if ($jenis === '' || $jenis === 'Produksi') {
            $produksi = ProduksiTelur::query()
                ->whereIn('kelompok_bebek_id', $idKandang)
                ->with('kelompokBebek:id,nama_kandang,jenis_bebek')
                ->when($this->start_date, fn ($q) => $q->whereDate('tanggal_produksi', '>=', $this->start_date))
                ->when($this->end_date, fn ($q) => $q->whereDate('tanggal_produksi', '<=', $this->end_date))
                ->orderByDesc('tanggal_produksi')->orderByDesc('id')
                ->limit($batas)
                ->get();

            foreach ($produksi as $baris) {
                $aktivitas[] = [
                    'kunci' => sprintf('%s-%010d', $baris->tanggal_produksi?->toDateString(), $baris->id),
                    'tanggal' => $baris->tanggal_produksi,
                    'jenis' => 'Produksi',
                    'ikon' => 'egg',
                    'warna' => 'bg-emerald-50 text-emerald-600',
                    'kandang' => $baris->kelompokBebek?->nama_kandang ?? '-',
                    'keterangan' => number_format((int) $baris->telur_dihasilkan, 0, ',', '.').' butir dipanen, '
                        .number_format((int) $baris->telur_layak_jual, 0, ',', '.').' layak jual',
                    'nilai' => PoultryFormulas::henDayPct((int) $baris->telur_dihasilkan, (int) $baris->jumlah_bebek).'% hen-day',
                    'tautan' => route('produksi-telur'),
                ];
            }
        }

        if ($jenis === '' || $jenis === 'Sampling') {
            $sampling = $this->rentang(MonitoringPertumbuhan::query()->whereIn('kelompok_bebek_id', $idKandang))
                ->with('kelompokBebek:id,nama_kandang,jenis_bebek')
                ->orderByDesc('tanggal')->orderByDesc('id')
                ->limit($batas)
                ->get();

            foreach ($sampling as $baris) {
                $aktivitas[] = [
                    'kunci' => sprintf('%s-%010d', $baris->tanggal?->toDateString(), $baris->id),
                    'tanggal' => $baris->tanggal,
                    'jenis' => 'Sampling',
                    'ikon' => 'monitor_weight',
                    'warna' => 'bg-indigo-50 text-indigo-600',
                    'kandang' => $baris->kelompokBebek?->nama_kandang ?? '-',
                    'keterangan' => number_format((float) $baris->berat_rata_rata, 1, ',', '.').' g/ekor dari '
                        .number_format((int) $baris->jumlah_sampel, 0, ',', '.').' sampel, umur '
                        .number_format((int) $baris->umur_hari, 0, ',', '.').' hari',
                    'nilai' => $baris->kondisi_kesehatan,
                    'tautan' => route('monitoring-pertumbuhan'),
                ];
            }
        }

        if ($jenis === '' || $jenis === 'Deplesi') {
            $deplesi = $this->rentang(PenguranganPopulasi::query()->whereIn('kelompok_bebek_id', $idKandang))
                ->with('kelompokBebek:id,nama_kandang,jenis_bebek')
                ->orderByDesc('tanggal')->orderByDesc('id')
                ->limit($batas)
                ->get();

            foreach ($deplesi as $baris) {
                $aktivitas[] = [
                    'kunci' => sprintf('%s-%010d', $baris->tanggal?->toDateString(), $baris->id),
                    'tanggal' => $baris->tanggal,
                    'jenis' => 'Deplesi',
                    'ikon' => 'heart_broken',
                    'warna' => 'bg-red-50 text-red-600',
                    'kandang' => $baris->kelompokBebek?->nama_kandang ?? '-',
                    'keterangan' => number_format((int) $baris->jumlah, 0, ',', '.').' ekor hilang — '
                        .($baris->penyebab ?? 'penyebab tidak dicatat'),
                    'nilai' => (string) ($baris->kategori ?? 'Mati'),
                    'tautan' => route('pengurangan-populasi'),
                ];
            }
        }

        if ($jenis === '' || $jenis === 'Penjualan') {
            $penjualan = Penjualan::query()
                ->with('pembeli:id,nama_pembeli')
                ->withSum('detailPenjualans as jumlah_telur_terjual', 'jumlah_telur')
                ->when($this->start_date, fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $this->start_date))
                ->when($this->end_date, fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $this->end_date))
                ->orderByDesc('tanggal_penjualan')->orderByDesc('id')
                ->limit($batas)
                ->get();

            foreach ($penjualan as $baris) {
                $aktivitas[] = [
                    'kunci' => sprintf('%s-%010d', $baris->tanggal_penjualan?->toDateString(), $baris->id),
                    'tanggal' => $baris->tanggal_penjualan,
                    'jenis' => 'Penjualan',
                    'ikon' => 'shopping_basket',
                    'warna' => 'bg-amber-50 text-amber-600',
                    'kandang' => $baris->pembeli?->nama_pembeli ?? 'Pembeli umum',
                    'keterangan' => 'Rp '.number_format((float) $baris->total_penjualan, 0, ',', '.').' untuk '
                        .number_format((int) $baris->jumlah_telur_terjual, 0, ',', '.').' butir telur',
                    'nilai' => $baris->status_pembayaran,
                    'tautan' => route('penjualan'),
                ];
            }
        }

        $urut = collect($aktivitas)->sortByDesc('kunci')->values()->all();

        return [
            'baris' => $urut,
            'total' => count($urut),
            'kena_batas' => count($urut) >= $batas,
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Laporan &amp; Riwayat</h1>
            <p class="text-sm text-gray-500 mt-1">
                Rekap produksi, deplesi, pertumbuhan, dan penjualan pada periode terpilih, lengkap dengan riwayat
                aktivitas kandang.
            </p>
        </div>
        <button wire:click="cetak" type="button"
                class="flex items-center justify-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg text-sm font-semibold transition-colors shadow-sm shrink-0 print:hidden">
            <span class="material-symbols-outlined text-[20px]">print</span>
            Cetak Laporan
        </button>
    </div>

    {{-- Filter --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 print:hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-700">Dari tanggal</label>
                <input wire:model.live="start_date" type="date" max="{{ $end_date }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-700">Sampai tanggal</label>
                <input wire:model.live="end_date" type="date" max="{{ today()->toDateString() }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-700">Kandang</label>
                <select wire:model.live="kelompokId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white cursor-pointer">
                    <option value="">Semua kandang</option>
                    @foreach ($kandang as $pilihan)
                        <option value="{{ $pilihan->id }}">{{ $pilihan->nama_kandang }} — {{ $pilihan->jenis_bebek }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-700">Jenis riwayat</label>
                <select wire:model.live="jenisRiwayat"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white cursor-pointer">
                    @foreach ($jenis as $opsi)
                        <option value="{{ $opsi }}">{{ $opsi === '' ? 'Semua jenis' : $opsi }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Ringkasan periode --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card label="Telur Layak Jual" ikon="egg" warna="emerald"
                     :nilai="number_format($ringkasan['layak'], 0, ',', '.')" satuan="butir"
                     :bawah="number_format($ringkasan['telur'], 0, ',', '.').' butir dipanen • '
                        .number_format($ringkasan['saleable_pct'], 1, ',', '.').'% layak jual • '
                        .number_format($ringkasan['rusak'], 0, ',', '.').' butir rusak'" />

        <x-stat-card label="Hen-day Production" ikon="insights" warna="blue"
                     :nilai="number_format($ringkasan['hen_day'], 1, ',', '.')" satuan="%"
                     :bawah="number_format($ringkasan['hari_produksi'], 0, ',', '.').' hari produksi dari '
                        .number_format($ringkasan['catatan_produksi'], 0, ',', '.').' pencatatan'" />

        <x-stat-card label="Deplesi Periode" ikon="heart_broken" warna="red"
                     :nilai="number_format($ringkasan['deplesi_ekor'], 0, ',', '.')" satuan="ekor"
                     :bawah="number_format($ringkasan['deplesi_persen'], 2, ',', '.').'% dari populasi awal '
                        .number_format($ringkasan['populasi_awal'], 0, ',', '.').' ekor • '
                        .number_format($ringkasan['deplesi_catatan'], 0, ',', '.').' catatan'" />

        <x-stat-card label="Penjualan Telur" ikon="shopping_basket" warna="amber"
                     :nilai="number_format($ringkasan['omzet'], 0, ',', '.')" prefiks="Rp"
                     :bawah="number_format($ringkasan['telur_terjual'], 0, ',', '.').' butir terjual ('
                        .number_format($ringkasan['terjual_pct'], 1, ',', '.').'% produksi layak jual) • '
                        .number_format($ringkasan['nota'], 0, ',', '.').' nota'" />
    </div>

    {{-- Konteks produksi & pakan --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 lg:col-span-2">
            <h2 class="font-semibold text-gray-900">Kinerja Produksi Periode</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Dihitung dari catatan produksi telur; hen-day memakai total ekor-hari pengeram pada periode ini.
            </p>

            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ekor-hari Pengeram</dt>
                    <dd class="text-lg font-bold text-gray-900 mt-1">{{ number_format($ringkasan['bebek'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pakan Terpakai</dt>
                    <dd class="text-lg font-bold text-gray-900 mt-1">
                        {{ number_format($ringkasan['pakan'], 1, ',', '.') }} <span class="text-xs font-normal text-gray-500">kg</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Konversi Pakan</dt>
                    <dd class="text-lg font-bold text-gray-900 mt-1">
                        @if ($ringkasan['feed_per_dozen'] !== null)
                            {{ number_format($ringkasan['feed_per_dozen'], 2, ',', '.') }}
                            <span class="text-xs font-normal text-gray-500">kg/lusin</span>
                        @else
                            &mdash;
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-5 pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sampling Pertumbuhan</p>
                    <p class="font-semibold text-gray-900 mt-1">
                        {{ number_format($ringkasan['sampling_catatan'], 0, ',', '.') }} kali •
                        {{ number_format($ringkasan['sampling_sampel'], 0, ',', '.') }} ekor tersampel
                    </p>
                    @if ($ringkasan['sampling_terakhir'])
                        <p class="text-xs text-gray-500 mt-1">
                            Terakhir {{ $ringkasan['sampling_terakhir']->tanggal?->format('d/m/Y') }} •
                            {{ number_format((float) $ringkasan['sampling_terakhir']->berat_rata_rata, 1, ',', '.') }} g/ekor
                        </p>
                    @else
                        <p class="text-xs text-gray-500 mt-1">Belum ada sampling pada periode ini.</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Piutang Penjualan</p>
                    <p class="font-semibold {{ $ringkasan['piutang'] > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-1">
                        Rp {{ number_format($ringkasan['piutang'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Lunas Rp {{ number_format($ringkasan['omzet_lunas'], 0, ',', '.') }} dari
                        Rp {{ number_format($ringkasan['omzet'], 0, ',', '.') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Populasi Kini</p>
                    <p class="font-semibold text-gray-900 mt-1">
                        {{ number_format($ringkasan['populasi_aktif'], 0, ',', '.') }} ekor aktif
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        dari {{ number_format($ringkasan['populasi_awal'], 0, ',', '.') }} ekor awal
                        ({{ number_format($ringkasan['daya_hidup'], 1, ',', '.') }}% daya hidup)
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900">Catatan Periode</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">{{ $periode }}</p>
            <ul class="space-y-3 text-sm">
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                    <span class="text-gray-700">
                        {{ number_format($ringkasan['catatan_produksi'], 0, ',', '.') }} pencatatan produksi pada
                        {{ number_format($ringkasan['hari_produksi'], 0, ',', '.') }} hari berbeda.
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-500">warning</span>
                    <span class="text-gray-700">
                        Deplesi {{ number_format($ringkasan['deplesi_ekor'], 0, ',', '.') }} ekor
                        ({{ number_format($ringkasan['deplesi_persen'], 2, ',', '.') }}% populasi awal).
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-indigo-500">info</span>
                    <span class="text-gray-700">
                        Telur terjual {{ number_format($ringkasan['terjual_pct'], 1, ',', '.') }}% dari produksi layak
                        jual periode ini; stok tidak dihitung di sini karena stok bersifat kumulatif.
                    </span>
                </li>
            </ul>
        </div>
    </div>

    {{-- Performa per kandang --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200">
            <h2 class="font-semibold text-gray-900">Performa per Kandang</h2>
            <p class="text-xs text-gray-500 mt-0.5">Perbandingan produksi, hen-day, dan deplesi setiap kandang pada periode terpilih.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                        <th class="p-4">Kandang</th>
                        <th class="p-4 text-center">Populasi Aktif</th>
                        <th class="p-4 text-right">Telur Dihasilkan</th>
                        <th class="p-4 text-right">Layak Jual</th>
                        <th class="p-4 text-center">Hen-day</th>
                        <th class="p-4 text-right">Deplesi</th>
                        <th class="p-4 text-center">Sampling</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($perKandang as $baris)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <p class="font-semibold text-sm text-gray-900">{{ $baris['nama_kandang'] }}</p>
                                <p class="text-xs text-gray-500">{{ $baris['jenis_bebek'] }} • {{ $baris['fase'] }} • {{ $baris['status'] }}</p>
                            </td>
                            <td class="p-4 text-center text-sm text-gray-700">{{ number_format($baris['populasi_aktif'], 0, ',', '.') }} ekor</td>
                            <td class="p-4 text-right text-sm text-gray-900">{{ number_format($baris['telur'], 0, ',', '.') }} butir</td>
                            <td class="p-4 text-right text-sm text-gray-700">{{ number_format($baris['layak'], 0, ',', '.') }} butir</td>
                            <td class="p-4 text-center text-sm font-semibold text-gray-900">
                                {{ $baris['telur'] > 0 ? number_format($baris['hen_day'], 1, ',', '.').'%' : '—' }}
                            </td>
                            <td class="p-4 text-right text-sm {{ $baris['deplesi_persen'] > 0 ? 'text-red-600' : 'text-gray-500' }}">
                                {{ number_format($baris['deplesi_ekor'], 0, ',', '.') }} ekor
                                <span class="block text-xs text-gray-500">{{ number_format($baris['deplesi_persen'], 2, ',', '.') }}%</span>
                            </td>
                            <td class="p-4 text-center text-sm text-gray-700">
                                {{ number_format($baris['sampling_catatan'], 0, ',', '.') }} kali
                                <span class="block text-xs text-gray-500">{{ $baris['catatan_produksi'] }} catatan produksi</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                <span class="material-symbols-outlined text-4xl text-gray-300">description</span>
                                <p class="font-medium text-gray-900 mt-2">Belum ada kandang untuk dilaporkan</p>
                                <p class="text-sm mt-1">Daftarkan kandang di menu Data Bebek terlebih dahulu.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Riwayat aktivitas --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-gray-900">Riwayat Aktivitas</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Pencatatan terbaru dari produksi, sampling, deplesi, dan penjualan pada periode ini.
                </p>
            </div>
            <p class="text-xs text-gray-500 shrink-0">
                Menampilkan {{ number_format($riwayat['total'], 0, ',', '.') }} catatan terbaru
            </p>
        </div>

        <ul class="divide-y divide-gray-100">
            @forelse ($riwayat['baris'] as $catatan)
                <li class="p-4 flex items-start gap-4 hover:bg-gray-50 transition-colors">
                    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $catatan['warna'] }}">
                        <span class="material-symbols-outlined text-[20px]">{{ $catatan['ikon'] }}</span>
                    </span>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900">{{ $catatan['jenis'] }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-600">{{ $catatan['kandang'] }}</span>
                            <span class="text-xs text-gray-500">{{ $catatan['tanggal']?->format('d/m/Y') ?? '-' }}</span>
                        </div>
                        <p class="text-sm text-gray-700 mt-1">{{ $catatan['keterangan'] }}</p>
                    </div>

                    <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $catatan['nilai'] }}</p>
                        <a href="{{ $catatan['tautan'] }}" wire:navigate
                           class="text-xs text-emerald-600 hover:text-emerald-700 font-medium print:hidden">Buka menu</a>
                    </div>
                </li>
            @empty
                <li class="p-8 text-center text-gray-500">
                    <span class="material-symbols-outlined text-4xl text-gray-300">history</span>
                    <p class="font-medium text-gray-900 mt-2">Belum ada aktivitas pada periode ini</p>
                    <p class="text-sm mt-1">Ubah rentang tanggal atau catat aktivitas kandang terlebih dahulu.</p>
                </li>
            @endforelse
        </ul>

        @if ($riwayat['kena_batas'])
            <div class="p-4 border-t border-gray-200 bg-gray-50/50 text-center print:hidden">
                <button wire:click="muatLebihBanyak" type="button"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg text-sm font-semibold transition-colors">
                    Muat 25 catatan lebih banyak
                </button>
            </div>
        @endif
    </div>
</div>
