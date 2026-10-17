<?php

use App\Livewire\Forms\PenjualanForm;
use App\Models\DetailPenjualan;
use App\Models\Pembeli;
use App\Models\Penjualan;
use App\Models\ProduksiTelur;
use App\Support\PenjualanTelur;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public PenjualanForm $form;

    public $startDate = '';

    public $endDate = '';

    public $statusFilter = 'Semua';

    public $pembeliFilter = '';

    public $isModalOpen = false;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: today()->subDays(29)->toDateString();
        $this->endDate = $this->endDate ?: today()->toDateString();
        $this->form->tanggal_penjualan = today()->toDateString();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
        $this->segarkanGrafik();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
        $this->segarkanGrafik();
    }

    public function updatedPembeliFilter(): void
    {
        $this->resetPage();
        $this->segarkanGrafik();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /** Canvas tidak ikut ter-morph, jadi sisi klien diminta menggambar ulang. */
    private function segarkanGrafik(): void
    {
        $this->dispatch('penjualan-chart-refresh');
    }

    public function openModal(): void
    {
        $this->resetValidation();
        $this->form->reset();
        $this->form->tanggal_penjualan = today()->toDateString();
        $this->form->pembeli_id = $this->pembeliFilter !== '' ? $this->pembeliFilter : '';
        $this->form->items = [PenjualanForm::itemKosong()];

        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $this->form->setRekaman(Penjualan::with('detailPenjualans')->findOrFail($id));
        $this->isModalOpen = true;
    }

    public function tutupModal(): void
    {
        $this->isModalOpen = false;
        $this->resetValidation();
    }

    public function tambahItem(): void
    {
        $this->form->tambahItem();
    }

    public function hapusItem(int $index): void
    {
        $this->form->hapusItem($index);
    }

    public function save(): void
    {
        $dikoreksi = $this->form->isEdit();

        if ($dikoreksi) {
            $this->form->update();
        } else {
            $this->form->store();
        }

        $this->isModalOpen = false;
        $this->form->reset();
        $this->form->tanggal_penjualan = today()->toDateString();
        $this->resetPage();

        $this->dispatch('swal:alert', [
            'title' => 'Tersimpan!',
            'text' => $dikoreksi
                ? 'Nota penjualan berhasil dikoreksi.'
                : 'Nota penjualan baru berhasil dicatat.',
            'icon' => 'success',
        ]);

        $this->segarkanGrafik();
    }

    /** Tukar status pembayaran nota langsung dari tabel piutang. */
    public function ubahStatus(int $id): void
    {
        $nota = Penjualan::findOrFail($id);

        $nota->status_pembayaran = $nota->status_pembayaran === 'Lunas'
            ? PenjualanTelur::STATUS[1]
            : PenjualanTelur::STATUS[0];

        $nota->save();

        $this->dispatch('swal:alert', [
            'title' => 'Status diperbarui',
            'text' => 'Nota ditandai '.$nota->status_pembayaran.'.',
            'icon' => 'success',
        ]);

        $this->segarkanGrafik();
    }

    public function hapus(int $id): void
    {
        // Baris detail ikut terhapus lewat constraint cascade.
        Penjualan::findOrFail($id)->delete();

        $this->resetPage();

        $this->dispatch('swal:alert', [
            'title' => 'Terhapus!',
            'text' => 'Nota penjualan beserta rinciannya sudah dihapus.',
            'icon' => 'success',
        ]);

        $this->segarkanGrafik();
    }

    /**
     * Builder dasar laporan; selalu instance baru supaya agregat dan paginator
     * tidak saling mewarisi limit/order.
     */
    private function laporanQuery()
    {
        return Penjualan::query()
            ->when($this->pembeliFilter !== '', fn ($q) => $q->where('pembeli_id', (int) $this->pembeliFilter))
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $this->endDate))
            ->when($this->statusFilter !== 'Semua', fn ($q) => $q->where('status_pembayaran', $this->statusFilter));
    }

    /** Butir telur yang tercatat terjual pada rentang laporan. */
    private function butirTerjual(): array
    {
        $hasil = DetailPenjualan::query()
            ->join('penjualans', 'penjualans.id', '=', 'detail_penjualans.penjualan_id')
            ->when($this->pembeliFilter !== '', fn ($q) => $q->where('penjualans.pembeli_id', (int) $this->pembeliFilter))
            ->when($this->startDate, fn ($q) => $q->whereDate('penjualans.tanggal_penjualan', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('penjualans.tanggal_penjualan', '<=', $this->endDate))
            ->when($this->statusFilter !== 'Semua', fn ($q) => $q->where('penjualans.status_pembayaran', $this->statusFilter))
            ->selectRaw('
                COALESCE(SUM(detail_penjualans.jumlah_telur), 0) AS ekor,
                COALESCE(SUM(detail_penjualans.subtotal), 0)     AS nilai
            ')
            ->first();

        return [
            'ekor' => (int) $hasil->ekor,
            'nilai' => (float) $hasil->nilai,
        ];
    }

    public function with(): array
    {
        $pembeli = Pembeli::query()->orderBy('nama_pembeli')->get(['id', 'nama_pembeli', 'kontak']);

        // Satu query agregat untuk seluruh kartu nilai.
        $agg = $this->laporanQuery()->selectRaw('
            COALESCE(SUM(total_penjualan), 0) AS total,
            COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN total_penjualan ELSE 0 END), 0) AS lunas,
            COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN 1 ELSE 0 END), 0) AS catatan_lunas,
            COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN total_penjualan ELSE 0 END), 0) AS belum_lunas,
            COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN 1 ELSE 0 END), 0) AS catatan_belum_lunas,
            COUNT(*) AS transaksi,
            COUNT(DISTINCT pembeli_id) AS pembeli_unik
        ', [PenjualanTelur::STATUS[0], PenjualanTelur::STATUS[0], PenjualanTelur::STATUS[1], PenjualanTelur::STATUS[1]])->first();

        $total = (float) $agg->total;
        $lunas = (float) $agg->lunas;
        $belumLunas = (float) $agg->belum_lunas;

        $terjual = $this->butirTerjual();

        // Produksi layak jual hanya mengikuti rentang tanggal: patokan stok.
        $produksiLayakJual = (int) ProduksiTelur::query()
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal_produksi', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal_produksi', '<=', $this->endDate))
            ->sum('telur_layak_jual');

        $riwayat = $this->laporanQuery()
            ->with([
                'pembeli:id,nama_pembeli,kontak',
                'detailPenjualans:id,penjualan_id,jumlah_telur,harga_per_butir,subtotal',
            ])
            ->orderByDesc('tanggal_penjualan')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (Penjualan $nota) {
                $nota->jumlah_telur = (int) $nota->detailPenjualans->sum('jumlah_telur');
                $nota->jumlah_baris = $nota->detailPenjualans->count();
                $nota->harga_rata_rata = PenjualanTelur::hargaRataRata((float) $nota->total_penjualan, $nota->jumlah_telur);

                return $nota;
            });

        // Grafik nilai harian 30 hari terakhir: nota lunas vs piutang.
        $harian = Penjualan::query()
            ->when($this->pembeliFilter !== '', fn ($q) => $q->where('pembeli_id', (int) $this->pembeliFilter))
            ->whereDate('tanggal_penjualan', '>=', today()->subDays(29)->toDateString())
            ->groupBy('tanggal_penjualan', 'status_pembayaran')
            ->selectRaw('
                tanggal_penjualan,
                status_pembayaran,
                COALESCE(SUM(total_penjualan), 0) AS nilai
            ')
            ->get();

        // Pembeli teratas pada rentang terpilih (filter pembeli tidak diterapkan
        // supaya perbandingan antar pembeli tetap terlihat).
        $pembeliTeratas = Penjualan::query()
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $this->endDate))
            ->when($this->statusFilter !== 'Semua', fn ($q) => $q->where('status_pembayaran', $this->statusFilter))
            ->groupBy('pembeli_id')
            ->orderByDesc('nilai')
            ->selectRaw('pembeli_id, COALESCE(SUM(total_penjualan), 0) AS nilai')
            ->limit(5)
            ->get();

        $petaNama = $pembeli->pluck('nama_pembeli', 'id');

        $label = [];
        $perHariLunas = [];
        $perHariPiutang = [];
        $nilaiHarian = [];

        foreach ($harian as $baris) {
            $kunci = $baris->tanggal_penjualan?->toDateString();

            if ($kunci === null) {
                continue;
            }

            $nilaiHarian[$kunci] ??= ['lunas' => 0.0, 'piutang' => 0.0];

            if ($baris->status_pembayaran === PenjualanTelur::STATUS[0]) {
                $nilaiHarian[$kunci]['lunas'] += (float) $baris->nilai;

                continue;
            }

            $nilaiHarian[$kunci]['piutang'] += (float) $baris->nilai;
        }

        for ($selisih = 29; $selisih >= 0; $selisih--) {
            $tanggal = today()->subDays($selisih);
            $nilai = $nilaiHarian[$tanggal->toDateString()] ?? ['lunas' => 0.0, 'piutang' => 0.0];

            $label[] = $tanggal->format('d/m');
            $perHariLunas[] = round($nilai['lunas'], 2);
            $perHariPiutang[] = round($nilai['piutang'], 2);
        }

        // Stok siap jual dihitung sepanjang catatan (produksi layak jual - butir terjual).
        $produksiSepanjangWaktu = (int) ProduksiTelur::query()->sum('telur_layak_jual');
        $terjualSepanjangWaktu = (int) DetailPenjualan::query()->sum('jumlah_telur');

        return [
            'pembeli' => $pembeli,
            'riwayat' => $riwayat,

            'ringkasan' => [
                'total' => $total,
                'lunas' => $lunas,
                'catatan_lunas' => (int) $agg->catatan_lunas,
                'belum_lunas' => $belumLunas,
                'catatan_belum_lunas' => (int) $agg->catatan_belum_lunas,
                'transaksi' => (int) $agg->transaksi,
                'pembeli_unik' => (int) $agg->pembeli_unik,
                'persen_lunas' => $total > 0 ? round($lunas / $total * 100, 1) : 0.0,
                'rata_per_nota' => (int) $agg->transaksi > 0 ? round($total / (int) $agg->transaksi, 2) : 0.0,
            ],

            'telur' => [
                'terjual' => $terjual['ekor'],
                'nilai' => $terjual['nilai'],
                'harga_rata_rata' => PenjualanTelur::hargaRataRata($terjual['nilai'], $terjual['ekor']),
                'produksi_layak_jual' => $produksiLayakJual,
                'persen_terjual' => PenjualanTelur::persenTerjual($terjual['ekor'], $produksiLayakJual),
            ],

            'stok' => [
                'produksi' => $produksiSepanjangWaktu,
                'terjual' => $terjualSepanjangWaktu,
                'tersedia' => PenjualanTelur::stokTersedia($produksiSepanjangWaktu, $terjualSepanjangWaktu),
                'persen_terjual' => PenjualanTelur::persenTerjual($terjualSepanjangWaktu, $produksiSepanjangWaktu),
            ],

            'grafik' => [
                'label' => $label,
                'lunas' => $perHariLunas,
                'piutang' => $perHariPiutang,
                'pembeli_label' => $pembeliTeratas
                    ->map(fn ($baris) => $baris->pembeli_id === null
                        ? 'Tanpa nama'
                        : ($petaNama[$baris->pembeli_id] ?? 'Pembeli dihapus'))
                    ->all(),
                'pembeli_nilai' => $pembeliTeratas->map(fn ($baris) => round((float) $baris->nilai, 2))->all(),
                'ada_penjualan' => $harian->isNotEmpty(),
            ],

            // Ringkasan stok untuk peringatan di modal hanya dihitung saat modal terbuka.
            'stok_modal' => $this->isModalOpen ? [
                'tersedia' => PenjualanTelur::stokTersedia(
                    $produksiSepanjangWaktu,
                    $terjualSepanjangWaktu - $this->butirNotaTerkoreksi()
                ),
                'butir_lain' => $this->butirNotaTerkoreksi(),
            ] : null,
        ];
    }

    /** Butir pada nota yang sedang dikoreksi, supaya tidak terhitung dua kali. */
    private function butirNotaTerkoreksi(): int
    {
        if (! $this->form->isEdit()) {
            return 0;
        }

        return (int) DetailPenjualan::query()->where('penjualan_id', $this->form->id)->sum('jumlah_telur');
    }
}; ?>

<div class="space-y-6"
     x-data="{
        konfirmasiSimpan() {
            Grafik.konfirmasi({
                judul: '{{ $form->isEdit() ? 'Simpan koreksi nota?' : 'Simpan nota penjualan?' }}',
                teks: 'Total nota dihitung dari jumlah butir dikali harga per butir setiap baris.',
                tombol: 'Ya, simpan',
                aksi: () => $wire.save(),
            });
        },
        konfirmasiHapus(id) {
            Grafik.konfirmasi({
                judul: 'Hapus nota penjualan?',
                teks: 'Nota beserta rincian telurnya akan dihapus dari riwayat penjualan.',
                ikon: 'warning',
                warna: '#dc2626',
                tombol: 'Ya, hapus',
                aksi: () => $wire.hapus(id),
            });
        }
     }">

    {{-- Header + filter --}}
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Penjualan Telur</h1>
            <p class="text-sm text-gray-500 mt-1">
                Nota penjualan, harga rata-rata per butir, dan piutang pembeli yang belum lunas.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full xl:w-auto">
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <select wire:model.live="pembeliFilter"
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Semua pembeli</option>
                    @foreach ($pembeli as $pilihan)
                        <option value="{{ $pilihan->id }}">{{ $pilihan->nama_pembeli }}</option>
                    @endforeach
                </select>

                <select wire:model.live="statusFilter"
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="Semua">Semua status</option>
                    <option value="Lunas">Lunas</option>
                    <option value="Belum Lunas">Belum lunas</option>
                </select>

                <input wire:model.live="startDate" type="date"
                       class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">

                <input wire:model.live="endDate" type="date"
                       class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <button wire:click="openModal" type="button"
                    class="flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm shrink-0">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Catat Penjualan
            </button>
        </div>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Penjualan</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        <span class="text-sm font-normal text-gray-500">Rp</span>
                        {{ number_format($ringkasan['total'], 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                {{ number_format($ringkasan['transaksi'], 0, ',', '.') }} nota
                &bull; {{ number_format($ringkasan['pembeli_unik'], 0, ',', '.') }} pembeli
                &bull; rata-rata Rp {{ number_format($ringkasan['rata_per_nota'], 0, ',', '.') }}
            </p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sudah Dibayar</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        <span class="text-sm font-normal text-gray-500">Rp</span>
                        {{ number_format($ringkasan['lunas'], 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-2.5 bg-teal-50 text-teal-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">paid</span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: {{ min(100, $ringkasan['persen_lunas']) }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    {{ number_format($ringkasan['persen_lunas'], 1, ',', '.') }}% dari nilai penjualan
                    &bull; {{ number_format($ringkasan['catatan_lunas'], 0, ',', '.') }} nota lunas
                </p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Piutang</p>
                    <p class="text-2xl font-bold {{ $ringkasan['belum_lunas'] > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-1">
                        <span class="text-sm font-normal text-gray-500">Rp</span>
                        {{ number_format($ringkasan['belum_lunas'], 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-2.5 bg-amber-50 text-amber-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">hourglass_empty</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                {{ number_format($ringkasan['catatan_belum_lunas'], 0, ',', '.') }} nota belum lunas
                &bull; tandai lunas dari tabel di bawah
            </p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Telur Terjual</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($telur['terjual'], 0, ',', '.') }}
                        <span class="text-sm font-normal text-gray-500">butir</span>
                    </p>
                </div>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">egg_alt</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                @if ($telur['harga_rata_rata'] !== null)
                    Rp {{ number_format($telur['harga_rata_rata'], 0, ',', '.') }}/butir
                @else
                    &mdash;
                @endif
                &bull; {{ number_format($telur['persen_terjual'], 1, ',', '.') }}% dari
                {{ number_format($telur['produksi_layak_jual'], 0, ',', '.') }} butir layak jual
            </p>
        </div>
    </div>

    {{-- Stok siap jual --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h3 class="font-semibold text-gray-900">Stok Siap Jual</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Selisih telur layak jual yang sudah diproduksi dengan telur yang sudah tercatat terjual.
                </p>
            </div>

            <div class="grid grid-cols-3 gap-4 shrink-0">
                <div>
                    <p class="text-xs text-gray-500">Produksi layak jual</p>
                    <p class="text-lg font-bold text-gray-900">{{ number_format($stok['produksi'], 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">butir</span></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Sudah terjual</p>
                    <p class="text-lg font-bold text-gray-900">{{ number_format($stok['terjual'], 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">butir</span></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Tersedia</p>
                    <p class="text-lg font-bold {{ $stok['tersedia'] > 0 ? 'text-emerald-700' : 'text-gray-900' }}">
                        {{ number_format($stok['tersedia'], 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">butir</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Grafik --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Penjualan Harian</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Nilai nota lunas dan piutang, 30 hari terakhir.
            </p>

            @if (collect($grafik['lunas'])->sum() + collect($grafik['piutang'])->sum() > 0)
                <div class="h-72" wire:ignore>
                    <canvas id="grafikPenjualan"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">point_of_sale</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada penjualan 30 hari terakhir</p>
                    <p class="text-xs text-gray-500 mt-1">Catat nota pertama untuk membentuk grafik.</p>
                </div>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Pembeli Teratas</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Lima pembeli dengan nilai beli terbesar pada rentang terpilih.</p>

            @if (count($grafik['pembeli_label']) > 0)
                <div class="h-72" wire:ignore>
                    <canvas id="grafikPembeli"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">storefront</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada pembeli</p>
                    <p class="text-xs text-gray-500 mt-1">Grafik terbentuk setelah ada nota penjualan.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Riwayat nota --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">Riwayat Penjualan</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Klik ikon panah untuk melihat rincian baris nota. Status pembayaran bisa diubah langsung.
                </p>
            </div>
            <span class="text-xs text-gray-500 shrink-0">{{ $riwayat->total() }} nota</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Pembeli</th>
                        <th class="p-4 text-right">Butir</th>
                        <th class="p-4 text-right">Harga/Butir</th>
                        <th class="p-4 text-right">Total</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>

                @forelse ($riwayat as $nota)
                    <tbody x-data="{ rincian: false }" class="border-b border-gray-100 last:border-b-0">
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $nota->tanggal_penjualan?->translatedFormat('d M Y') ?? '-' }}</p>
                                <p class="text-xs text-gray-500">{{ $nota->tanggal_penjualan?->translatedFormat('l') }}</p>
                            </td>
                            <td class="p-4">
                                <p class="text-sm font-medium text-gray-900">{{ $nota->pembeli?->nama_pembeli ?? 'Tanpa nama (pasar)' }}</p>
                                <p class="text-xs text-gray-500">{{ $nota->pembeli?->kontak ?? 'Tanpa kontak' }}</p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($nota->jumlah_telur, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">{{ $nota->jumlah_baris }} baris</p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm text-gray-800">
                                    @if ($nota->harga_rata_rata !== null)
                                        Rp {{ number_format($nota->harga_rata_rata, 0, ',', '.') }}
                                    @else
                                        &mdash;
                                    @endif
                                </p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-bold text-gray-900">Rp {{ number_format((float) $nota->total_penjualan, 0, ',', '.') }}</p>
                            </td>
                            <td class="p-4 text-center">
                                @if ($nota->status_pembayaran === 'Lunas')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Lunas
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Belum lunas
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="rincian = !rincian" type="button"
                                            class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                                            title="Rincian baris nota">
                                        <span class="material-symbols-outlined text-[20px]" x-text="rincian ? 'expand_less' : 'expand_more'">expand_more</span>
                                    </button>
                                    <button wire:click="ubahStatus({{ $nota->id }})" type="button"
                                            class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-md transition-colors"
                                            title="{{ $nota->status_pembayaran === 'Lunas' ? 'Tandai belum lunas' : 'Tandai lunas' }}">
                                        <span class="material-symbols-outlined text-[20px]">price_check</span>
                                    </button>
                                    <button wire:click="edit({{ $nota->id }})" type="button"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                            title="Koreksi nota">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button @click="konfirmasiHapus({{ $nota->id }})" type="button"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                            title="Hapus nota">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr x-show="rincian" style="display: none;" class="bg-gray-50/60">
                            <td colspan="7" class="px-4 pb-4 pt-0">
                                <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="text-xs uppercase tracking-wider font-semibold text-gray-500 border-b border-gray-200">
                                                <th class="p-3">Baris</th>
                                                <th class="p-3 text-right">Butir</th>
                                                <th class="p-3 text-right">Harga/Butir</th>
                                                <th class="p-3 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($nota->detailPenjualans as $index => $detail)
                                                <tr>
                                                    <td class="p-3 text-sm text-gray-700">Baris {{ $index + 1 }}</td>
                                                    <td class="p-3 text-sm text-right text-gray-800">{{ number_format($detail->jumlah_telur, 0, ',', '.') }}</td>
                                                    <td class="p-3 text-sm text-right text-gray-800">Rp {{ number_format((float) $detail->harga_per_butir, 0, ',', '.') }}</td>
                                                    <td class="p-3 text-sm text-right font-semibold text-gray-900">Rp {{ number_format((float) $detail->subtotal, 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    @if ($nota->catatan)
                                        <p class="p-3 text-xs text-gray-500 border-t border-gray-100">{{ $nota->catatan }}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">point_of_sale</span>
                                    <p class="font-medium text-gray-900">Belum ada nota penjualan</p>
                                    <p class="text-sm mt-1">Ubah rentang tanggal atau catat penjualan pertama Anda.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        @if ($riwayat->hasPages())
            <div class="p-4 border-t border-gray-200 bg-white">
                {{ $riwayat->links() }}
            </div>
        @endif
    </div>

    {{-- Modal nota --}}
    <x-app-modal :judul="$form->isEdit() ? 'Koreksi Nota Penjualan' : 'Catat Nota Penjualan'"
                 subjudul="Satu nota bisa memuat beberapa baris dengan harga berbeda.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Tanggal Penjualan</label>
                        <input wire:model="form.tanggal_penjualan" type="date" max="{{ today()->toDateString() }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('form.tanggal_penjualan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Status Pembayaran</label>
                        <select wire:model="form.status_pembayaran"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="Lunas">Lunas (dibayar sekarang)</option>
                            <option value="Belum Lunas">Belum lunas (piutang)</option>
                        </select>
                        @error('form.status_pembayaran') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-semibold text-gray-700">Pembeli</label>
                        @unless ($form->isEdit())
                            <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                <input wire:model.live="form.pembeli_baru" type="checkbox"
                                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Pembeli baru
                            </label>
                        @endunless
                    </div>

                    @if ($form->pembeliBaruAktif())
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input wire:model="form.nama_pembeli_baru" type="text" placeholder="Nama pembeli baru"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <input wire:model="form.kontak_pembeli_baru" type="text" placeholder="Kontak (opsional)"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        </div>
                        @error('form.nama_pembeli_baru') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500">Pembeli baru langsung disimpan ke daftar pembeli.</p>
                    @else
                        <select wire:model="form.pembeli_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">-- Tanpa nama (pasar) --</option>
                            @foreach ($pembeli as $pilihan)
                                <option value="{{ $pilihan->id }}">
                                    {{ $pilihan->nama_pembeli }}{{ $pilihan->kontak ? ' • '.$pilihan->kontak : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('form.pembeli_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between gap-3 px-3 py-2 bg-gray-50 border-b border-gray-200">
                        <span class="text-sm font-semibold text-gray-700">Rincian Telur</span>
                        <button wire:click="tambahItem" type="button"
                                class="flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span>
                            Tambah baris
                        </button>
                    </div>

                    <div class="p-3 flex flex-col gap-3">
                        @foreach ($form->items as $index => $item)
                            <div class="grid grid-cols-12 gap-2 items-start" wire:key="baris-nota-{{ $index }}">
                                <div class="col-span-5 flex flex-col gap-1">
                                    <label class="text-xs text-gray-500">Jumlah (butir)</label>
                                    <input wire:model.live="form.items.{{ $index }}.jumlah_telur" type="number" min="1"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                    @error('form.items.'.$index.'.jumlah_telur')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-span-5 flex flex-col gap-1">
                                    <label class="text-xs text-gray-500">Harga per butir (Rp)</label>
                                    <input wire:model.live="form.items.{{ $index }}.harga_per_butir" type="number" min="0" step="50"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                    @error('form.items.'.$index.'.harga_per_butir')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-span-2 flex flex-col gap-1">
                                    <label class="text-xs text-gray-500">Subtotal</label>
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-gray-700 py-2 truncate">
                                            Rp {{ number_format((int) ($item['jumlah_telur'] ?? 0) * (float) ($item['harga_per_butir'] ?? 0), 0, ',', '.') }}
                                        </span>
                                        @if (count($form->items) > 1)
                                            <button wire:click="hapusItem({{ $index }})" type="button"
                                                    class="p-1 text-gray-400 hover:text-red-600 shrink-0" title="Hapus baris">
                                                <span class="material-symbols-outlined text-[18px]">remove_circle</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between px-3 py-2.5 bg-gray-50 border-t border-gray-200">
                        <span class="text-sm text-gray-600">
                            Total {{ number_format($form->totalEkor(), 0, ',', '.') }} butir
                        </span>
                        <span class="text-base font-bold text-gray-900">
                            Rp {{ number_format($form->total(), 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                @if ($stok_modal)
                    @php
                        $melebihiStok = $form->totalEkor() > $stok_modal['tersedia'];
                    @endphp
                    <div class="rounded-lg border px-3 py-2 text-xs {{ $melebihiStok ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-gray-50 text-gray-600' }}">
                        Stok siap jual tercatat:
                        <span class="font-semibold">{{ number_format($stok_modal['tersedia'], 0, ',', '.') }}</span> butir.
                        @if ($melebihiStok)
                            Nota ini {{ number_format($form->totalEkor() - $stok_modal['tersedia'], 0, ',', '.') }} butir
                            melebihi stok — periksa apakah produksi hariannya sudah dicatat.
                        @endif
                    </div>
                @endif

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Catatan <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <textarea wire:model="form.catatan" rows="2" placeholder="Misal: dikirim ke Pasar Beringharjo, dibayar tunai"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none"></textarea>
                    @error('form.catatan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

            <x-slot:aksi>
                <button wire:click="tutupModal" type="button"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors">
                    Batal
                </button>
                <button @click="konfirmasiSimpan()" type="button"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors">
                    {{ $form->isEdit() ? 'Simpan Koreksi' : 'Simpan Nota' }}
                </button>
            </x-slot:aksi>
        </x-app-modal>

    {{-- Data grafik dikirim lewat atribut data supaya canvas tidak ikut ter-morph. --}}
    <div id="dataGrafikPenjualan" class="hidden" data-grafik="{{ json_encode($grafik) }}"></div>

    @script
        <script>
            const gambarGrafikPenjualan = () => {
                const data = Grafik.bacaDataGrafik('dataGrafikPenjualan');

                if (!data || !Array.isArray(data.label) || data.label.length === 0) {
                    return;
                }

                Grafik.gambarGrafik('grafikPenjualan', {
                    type: 'bar',
                    data: {
                        labels: data.label,
                        datasets: [
                            {
                                label: 'Lunas (Rp)',
                                data: data.lunas,
                                backgroundColor: '#059669',
                                borderRadius: 4,
                                stack: 'nilai'
                            },
                            {
                                label: 'Piutang (Rp)',
                                data: data.piutang,
                                backgroundColor: '#f59e0b',
                                borderRadius: 4,
                                stack: 'nilai'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    label: (konteks) => konteks.dataset.label + ': Rp ' + Number(konteks.parsed.y).toLocaleString('id-ID')
                                }
                            }
                        },
                        scales: {
                            x: { stacked: true },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: {
                                    callback: (nilai) => 'Rp ' + Number(nilai).toLocaleString('id-ID')
                                }
                            }
                        }
                    }
                });

                if (Array.isArray(data.pembeli_label) && data.pembeli_label.length > 0) {
                    Grafik.gambarGrafik('grafikPembeli', {
                        type: 'doughnut',
                        data: {
                            labels: data.pembeli_label,
                            datasets: [
                                {
                                    data: data.pembeli_nilai,
                                    backgroundColor: [
                                        '#059669', '#0ea5e9', '#6366f1', '#f59e0b', '#a855f7'
                                    ],
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                                tooltip: {
                                    callbacks: {
                                        label: (konteks) => konteks.label + ': Rp ' + Number(konteks.parsed).toLocaleString('id-ID')
                                    }
                                }
                            }
                        }
                    });
                }
            };

            gambarGrafikPenjualan();
            Grafik.pantauGrafik('penjualan-chart-refresh', gambarGrafikPenjualan);
            Grafik.pasangPeringatanSwal();
        </script>
    @endscript
</div>
