<?php

use App\Livewire\Forms\ProduksiTelurForm;
use App\Models\KelompokBebek;
use App\Models\ProduksiTelur;
use App\Support\PoultryFormulas;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $flock_id = null;

    public $start_date = '';

    public $end_date = '';

    // Asumsi harga untuk kolom ekonomi (per laporan, tidak disimpan).
    public $harga_butir = 0;

    public $harga_pakan_per_kg = 0;

    public ProduksiTelurForm $form;

    public $isModalOpen = false;

    public function mount(): void
    {
        $this->start_date = today()->subDays(6)->toDateString();
        $this->end_date = today()->toDateString();
    }

    public function updatedFlockId(): void
    {
        $this->resetPage();

        // Grafik tren tidak ikut ter-morph (canvas di-ignore), jadi minta
        // sisi klien menggambarnya ulang.
        $this->dispatch('produksi-chart-refresh');
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->resetValidation();
        $this->form->reset();
        $this->form->tanggal_produksi = today()->toDateString();
        $this->form->kelompok_bebek_id = $this->flock_id
            ?? KelompokBebek::query()->where('status', 'Aktif')->orderBy('nama_kandang')->value('id');

        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $this->form->reset();
        $this->form->setRekaman(ProduksiTelur::findOrFail($id));

        $this->isModalOpen = true;
    }

    public function tutupModal(): void
    {
        $this->isModalOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $dikoreksi = $this->form->isEdit();

        if ($dikoreksi) {
            $this->form->update(ProduksiTelur::findOrFail($this->form->id));
        } else {
            $this->form->store();
        }

        $this->tutupModal();
        $this->form->reset();

        $this->dispatch('swal:alert', [
            'title' => 'Tersimpan!',
            'text' => $dikoreksi
                ? 'Catatan produksi berhasil dikoreksi dan metrik dihitung ulang.'
                : 'Data produksi telur berhasil dicatat.',
            'icon' => 'success',
        ]);

        $this->dispatch('produksi-chart-refresh');
    }

    public function hapus(int $id): void
    {
        ProduksiTelur::destroy($id);

        $this->dispatch('swal:alert', [
            'title' => 'Terhapus!',
            'text' => 'Baris produksi sudah dihapus dari riwayat.',
            'icon' => 'success',
        ]);

        $this->dispatch('produksi-chart-refresh');
    }

    /**
     * Builder dasar laporan; selalu instance baru supaya agregat dan
     * paginator tidak saling mewarisi limit/order.
     */
    private function laporanQuery()
    {
        return ProduksiTelur::query()
            ->when($this->flock_id, fn ($q) => $q->where('kelompok_bebek_id', $this->flock_id))
            ->when($this->start_date, fn ($q) => $q->whereDate('tanggal_produksi', '>=', $this->start_date))
            ->when($this->end_date, fn ($q) => $q->whereDate('tanggal_produksi', '<=', $this->end_date));
    }

    public function with(): array
    {
        $hargaButir = (float) $this->harga_butir;
        $hargaPakan = (float) $this->harga_pakan_per_kg;

        $entries = $this->laporanQuery()
            ->with('kelompokBebek:id,nama_kandang,jenis_bebek')
            ->orderByDesc('tanggal_produksi')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (ProduksiTelur $row) use ($hargaButir) {
                $row->hen_day_pct = PoultryFormulas::henDayPct($row->telur_dihasilkan, $row->jumlah_bebek);
                $row->saleable_pct = PoultryFormulas::saleablePct($row->telur_layak_jual, $row->telur_dihasilkan);
                $row->rusak_pct = PoultryFormulas::rusakPct($row->telur_rusak, $row->telur_dihasilkan);
                $row->feed_per_dozen = PoultryFormulas::feedPerDozenKilograms($row->pakan_kg, $row->telur_layak_jual);
                $row->feed_per_kg_egg = PoultryFormulas::feedPerKgEgg($row->pakan_kg, $row->telur_layak_jual, $row->berat_butir_g);
                $row->nilai_jual = $row->telur_layak_jual * $hargaButir;

                return $row;
            });

        // Satu query agregat untuk seluruh kartu ringkasan (bukan loop PHP).
        // SUM(pakan_kg) otomatis mengabaikan baris yang pakannya tidak dicatat.
        $agg = $this->laporanQuery()->selectRaw('
            COALESCE(SUM(telur_dihasilkan), 0) AS total_dihasilkan,
            COALESCE(SUM(telur_layak_jual), 0) AS total_layak_jual,
            COALESCE(SUM(telur_rusak), 0)      AS total_rusak,
            COALESCE(SUM(jumlah_bebek), 0)     AS total_bebek,
            COALESCE(SUM(pakan_kg), 0)         AS total_pakan,
            COUNT(*)                           AS jumlah_catatan,
            COUNT(DISTINCT tanggal_produksi)   AS jumlah_hari
        ')->first();

        $totalDihasilkan = (int) $agg->total_dihasilkan;
        $totalLayakJual = (int) $agg->total_layak_jual;
        $totalRusak = (int) $agg->total_rusak;
        $totalBebek = (int) $agg->total_bebek;
        $totalPakan = (float) $agg->total_pakan;

        $nilaiJual = $totalLayakJual * $hargaButir;
        $biayaPakan = $totalPakan * $hargaPakan;
        $margin = $nilaiJual - $biayaPakan;

        // Tren 30 hari terakhir untuk grafik (mengikuti filter kandang).
        $tren = ProduksiTelur::query()
            ->when($this->flock_id, fn ($q) => $q->where('kelompok_bebek_id', $this->flock_id))
            ->whereDate('tanggal_produksi', '>=', today()->subDays(29)->toDateString())
            ->groupBy('tanggal_produksi')
            ->orderBy('tanggal_produksi')
            ->selectRaw('
                tanggal_produksi,
                COALESCE(SUM(telur_layak_jual), 0) AS layak_jual,
                COALESCE(SUM(telur_rusak), 0)      AS rusak,
                COALESCE(SUM(telur_dihasilkan), 0) AS dihasilkan,
                COALESCE(SUM(jumlah_bebek), 0)     AS bebek
            ')
            ->get();

        $rataHarian = $tren->count() > 0 ? (int) round($tren->sum('layak_jual') / $tren->count()) : 0;
        $proyeksiMingguan = (int) round($rataHarian * 7);

        return [
            'flocks' => KelompokBebek::query()
                ->orderBy('nama_kandang')
                ->get(['id', 'nama_kandang', 'jenis_bebek', 'status']),

            'entries' => $entries,

            'ringkasan' => [
                'total_dihasilkan' => $totalDihasilkan,
                'total_layak_jual' => $totalLayakJual,
                'total_rusak' => $totalRusak,
                'total_bebek' => $totalBebek,
                'total_pakan' => $totalPakan > 0 ? round($totalPakan, 1) : null,
                'jumlah_catatan' => (int) $agg->jumlah_catatan,
                'jumlah_hari' => (int) $agg->jumlah_hari,
                'hen_day_pct' => PoultryFormulas::weightedHenDayPct($totalDihasilkan, $totalBebek),
                'saleable_pct' => PoultryFormulas::saleablePct($totalLayakJual, $totalDihasilkan),
                'rusak_pct' => PoultryFormulas::rusakPct($totalRusak, $totalDihasilkan),
                'feed_per_dozen' => PoultryFormulas::feedPerDozenKilograms(
                    $totalPakan > 0 ? $totalPakan : null,
                    $totalLayakJual
                ),
                // Setiap baris = satu hari untuk satu kandang, jadi jumlah
                // bebek yang dijumlahkan = total "bird-days".
                'konsumsi_ekor_hari' => $totalBebek > 0 && $totalPakan > 0
                    ? round($totalPakan * 1000 / $totalBebek, 1)
                    : null,
                'nilai_jual' => $nilaiJual,
                'biaya_pakan' => $biayaPakan,
                'margin' => $margin,
                'margin_pct' => $nilaiJual > 0 ? round($margin / $nilaiJual * 100, 1) : 0.0,
            ],

            'grafik' => [
                'label' => $tren->map(fn ($r) => $r->tanggal_produksi?->format('d/m'))->all(),
                'layak_jual' => $tren->pluck('layak_jual')->map(fn ($v) => (int) $v)->all(),
                'rusak' => $tren->pluck('rusak')->map(fn ($v) => (int) $v)->all(),
                'hen_day' => $tren->map(
                    fn ($r) => PoultryFormulas::weightedHenDayPct((int) $r->dihasilkan, (int) $r->bebek)
                )->all(),
            ],

            'rata_harian' => $rataHarian,
            'proyeksi_mingguan' => $proyeksiMingguan,
            'proyeksi_nilai' => $proyeksiMingguan * $hargaButir,
            'rata_bebek' => $tren->count() > 0 ? (int) round($tren->sum('bebek') / $tren->count()) : 0,
        ];
    }
}; ?>

<div class="space-y-6"
     x-data="{
        konfirmasiHapus(id) {
            Grafik.konfirmasi({
                judul: 'Hapus data produksi?',
                teks: 'Baris ini akan hilang dari riwayat dan grafik produksi.',
                ikon: 'warning',
                warna: '#dc2626',
                tombol: 'Ya, hapus',
                aksi: () => $wire.hapus(id),
            });
        },
        konfirmasiSimpan() {
            Grafik.konfirmasi({
                judul: '{{ $form->isEdit() ? 'Simpan koreksi produksi?' : 'Simpan data produksi?' }}',
                teks: 'Pastikan jumlah bebek pengeram dan jumlah telur sudah sesuai catatan kandang.',
                tombol: 'Ya, simpan',
                aksi: () => $wire.save(),
            });
        }
     }">

    {{-- Header + filter --}}
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Produksi Telur</h1>
            <p class="text-sm text-gray-500 mt-1">
                Hen-day production, distribusi telur, konversi pakan, dan estimasi nilai produksi per kandang.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full xl:w-auto">
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <select wire:model.live="flock_id"
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Semua kandang</option>
                    @foreach ($flocks as $pilihan)
                        <option value="{{ $pilihan->id }}">{{ $pilihan->nama_kandang }}</option>
                    @endforeach
                </select>

                <input wire:model.live="start_date" type="date"
                       class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">

                <input wire:model.live="end_date" type="date"
                       class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <button wire:click="openModal" type="button"
                    class="flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm shrink-0">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Catat Produksi
            </button>
        </div>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card label="Telur Dihasilkan" ikon="egg" warna="blue"
                     nilai="{{ number_format($ringkasan['total_dihasilkan'], 0, ',', '.') }}"
                     satuan="butir"
                     bawah="{{ number_format($ringkasan['jumlah_catatan'], 0, ',', '.') }} pencatatan • {{ number_format($ringkasan['jumlah_hari'], 0, ',', '.') }} hari produksi" />

        <x-stat-card label="Hen-day Production" ikon="insights" warna="emerald"
                     nilai="{{ number_format($ringkasan['hen_day_pct'], 1, ',', '.') }}"
                     satuan="%"
                     bawah="{{ number_format($ringkasan['total_dihasilkan'], 0, ',', '.') }} telur / {{ number_format($ringkasan['total_bebek'], 0, ',', '.') }} ekor-hari pengeram" />

        <x-stat-card label="Telur Layak Jual" ikon="shopping_basket" warna="teal"
                     nilai="{{ number_format($ringkasan['total_layak_jual'], 0, ',', '.') }}"
                     satuan="butir">
            <x-slot:footer>
                <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: {{ min(100, $ringkasan['saleable_pct']) }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    {{ number_format($ringkasan['saleable_pct'], 1, ',', '.') }}% dari produksi
                    &bull; rusak {{ number_format($ringkasan['rusak_pct'], 1, ',', '.') }}%
                    ({{ number_format($ringkasan['total_rusak'], 0, ',', '.') }} butir)
                </p>
            </x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Konversi Pakan" ikon="scale" warna="amber"
                     :nilai="$ringkasan['feed_per_dozen'] !== null ? number_format($ringkasan['feed_per_dozen'], 2, ',', '.') : '—'"
                     :satuan="$ringkasan['feed_per_dozen'] !== null ? 'kg/lusin' : null">
            <x-slot:footer>
                <p class="text-xs text-gray-500">
                    @if ($ringkasan['total_pakan'] !== null)
                        Pakan tercatat {{ number_format($ringkasan['total_pakan'], 1, ',', '.') }} kg
                        @if ($ringkasan['konsumsi_ekor_hari'] !== null)
                            &bull; {{ number_format($ringkasan['konsumsi_ekor_hari'], 1, ',', '.') }} g/ekor/hari
                        @endif
                    @else
                        Isi kolom pakan saat mencatat untuk menghitung konversi pakan.
                    @endif
                </p>
            </x-slot:footer>
        </x-stat-card>
    </div>

    {{-- Asumsi harga + nilai produksi --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <div class="flex flex-col lg:flex-row lg:items-end gap-5">
            <div class="flex flex-col gap-1.5 w-full sm:w-48">
                <label class="text-xs font-semibold text-gray-700">Harga telur (Rp/butir)</label>
                <input wire:model.live.debounce.500ms="harga_butir" type="number" min="0" step="1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <div class="flex flex-col gap-1.5 w-full sm:w-48">
                <label class="text-xs font-semibold text-gray-700">Harga pakan (Rp/kg)</label>
                <input wire:model.live.debounce.500ms="harga_pakan_per_kg" type="number" min="0" step="1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1 w-full">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estimasi Nilai Jual</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">
                        Rp {{ number_format($ringkasan['nilai_jual'], 0, ',', '.') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estimasi Biaya Pakan</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">
                        Rp {{ number_format($ringkasan['biaya_pakan'], 0, ',', '.') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Margin</p>
                    <p class="text-lg font-bold {{ $ringkasan['margin'] >= 0 ? 'text-emerald-700' : 'text-red-600' }} mt-1">
                        Rp {{ number_format($ringkasan['margin'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ number_format($ringkasan['margin_pct'], 1, ',', '.') }}% dari nilai jual</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Proyeksi 7 Hari</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">
                        {{ number_format($proyeksi_mingguan, 0, ',', '.') }}
                        <span class="text-xs font-normal text-gray-500">butir</span>
                    </p>
                    <p class="text-xs text-gray-500">
                        &plusmn; {{ number_format($rata_harian, 0, ',', '.') }}/hari
                        &bull; Rp {{ number_format($proyeksi_nilai, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-4">
            Angka estimasi memakai harga di atas; data produksi dan pakan tetap diambil dari riwayat pencatatan.
        </p>
    </div>

    {{-- Grafik --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Produksi Telur Harian</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Telur layak jual dan telur rusak, 30 hari terakhir.</p>

            @if (count($grafik['label']) > 0)
                <div class="h-72" wire:ignore>
                    <canvas id="grafikProduksi"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">egg</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada data produksi 30 hari terakhir</p>
                    <p class="text-xs text-gray-500 mt-1">Catat produksi harian untuk membentuk grafik.</p>
                </div>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Hen-day Production</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Persentase produksi harian terhadap bebek pengeram.</p>

            @if (count($grafik['hen_day']) > 0)
                <div class="h-72" wire:ignore>
                    <canvas id="grafikHenDay"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">insights</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada data</p>
                    <p class="text-xs text-gray-500 mt-1">Grafik muncul setelah ada pencatatan produksi.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Riwayat produksi --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">Riwayat Produksi</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Hen-day %, persentase layak jual, dan konversi pakan dihitung otomatis dari kolom yang tercatat.
                </p>
            </div>
            <span class="text-xs text-gray-500 shrink-0">{{ $entries->total() }} baris</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Kandang</th>
                        <th class="p-4 text-right">Pengeram</th>
                        <th class="p-4 text-right">Dihasilkan</th>
                        <th class="p-4 text-right">Layak Jual</th>
                        <th class="p-4 text-right">Rusak</th>
                        <th class="p-4 text-center">Hen-day</th>
                        <th class="p-4 text-right">Pakan</th>
                        <th class="p-4 text-right">Feed/Lusin</th>
                        <th class="p-4 text-right">Nilai Jual</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($entries as $baris)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $baris->tanggal_produksi?->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $baris->tanggal_produksi?->translatedFormat('l') }}</p>
                                @if ($baris->catatan)
                                    <p class="text-xs text-gray-400 mt-1 italic">{{ $baris->catatan }}</p>
                                @endif
                            </td>
                            <td class="p-4">
                                <p class="text-sm text-gray-900">{{ $baris->kelompokBebek?->nama_kandang ?? '-' }}</p>
                                <p class="text-xs text-gray-500">{{ $baris->kelompokBebek?->jenis_bebek ?? '-' }}</p>
                            </td>
                            <td class="p-4 text-right text-sm text-gray-700">
                                {{ number_format($baris->jumlah_bebek, 0, ',', '.') }}
                            </td>
                            <td class="p-4 text-right text-sm text-gray-900">
                                {{ number_format($baris->telur_dihasilkan, 0, ',', '.') }}
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($baris->telur_layak_jual, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">{{ number_format($baris->saleable_pct, 1, ',', '.') }}%</p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm text-gray-900">{{ number_format($baris->telur_rusak, 0, ',', '.') }}</p>
                                <p class="text-xs {{ $baris->rusak_pct > 5 ? 'text-red-600' : 'text-gray-500' }}">
                                    {{ number_format($baris->rusak_pct, 1, ',', '.') }}%
                                </p>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium border
                                    {{ $baris->hen_day_pct >= 70
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : ($baris->hen_day_pct >= 50
                                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                                            : 'bg-red-50 text-red-700 border-red-200') }}">
                                    {{ number_format($baris->hen_day_pct, 1, ',', '.') }}%
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm text-gray-700">
                                    {{ $baris->pakan_kg !== null ? number_format($baris->pakan_kg, 1, ',', '.') . ' kg' : '—' }}
                                </p>
                                @if ($baris->feed_per_kg_egg !== null)
                                    <p class="text-xs text-gray-500">
                                        {{ number_format($baris->feed_per_kg_egg, 2, ',', '.') }} kg/kg telur
                                    </p>
                                @endif
                            </td>
                            <td class="p-4 text-right text-sm text-gray-700">
                                {{ $baris->feed_per_dozen !== null ? number_format($baris->feed_per_dozen, 2, ',', '.') : '—' }}
                            </td>
                            <td class="p-4 text-right text-sm text-gray-900">
                                {{ $baris->nilai_jual > 0 ? 'Rp ' . number_format($baris->nilai_jual, 0, ',', '.') : '—' }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="edit({{ $baris->id }})" type="button"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                            title="Koreksi baris produksi">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button type="button" @click="konfirmasiHapus({{ $baris->id }})"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                            title="Hapus baris produksi">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">egg</span>
                                    <p class="font-medium text-gray-900">Belum ada catatan produksi pada rentang ini</p>
                                    <p class="text-sm mt-1">Catat hasil panen telur harian untuk melihat performa produksi.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($entries->total() > 0)
                    <tfoot class="bg-gray-50 border-t border-gray-200 text-xs">
                        <tr>
                            <td colspan="2" class="p-4 font-semibold uppercase tracking-wider text-gray-500">Total rentang</td>
                            <td class="p-4 text-right text-gray-700">{{ number_format($ringkasan['total_bebek'], 0, ',', '.') }}</td>
                            <td class="p-4 text-right text-gray-700">{{ number_format($ringkasan['total_dihasilkan'], 0, ',', '.') }}</td>
                            <td class="p-4 text-right font-semibold text-gray-900">{{ number_format($ringkasan['total_layak_jual'], 0, ',', '.') }}</td>
                            <td class="p-4 text-right text-gray-700">{{ number_format($ringkasan['total_rusak'], 0, ',', '.') }}</td>
                            <td class="p-4 text-center font-semibold text-gray-900">{{ number_format($ringkasan['hen_day_pct'], 1, ',', '.') }}%</td>
                            <td class="p-4 text-right text-gray-700">
                                {{ $ringkasan['total_pakan'] !== null ? number_format($ringkasan['total_pakan'], 1, ',', '.') . ' kg' : '—' }}
                            </td>
                            <td class="p-4 text-right text-gray-700">
                                {{ $ringkasan['feed_per_dozen'] !== null ? number_format($ringkasan['feed_per_dozen'], 2, ',', '.') : '—' }}
                            </td>
                            <td class="p-4 text-right font-semibold text-gray-900">Rp {{ number_format($ringkasan['nilai_jual'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($entries->hasPages())
            <div class="p-4 border-t border-gray-200 bg-white">
                {{ $entries->links() }}
            </div>
        @endif
    </div>

    {{-- Modal catat/koreksi produksi --}}
    <x-app-modal :judul="$form->isEdit() ? 'Koreksi Produksi Telur' : 'Catat Produksi Telur'"
                 :subjudul="$form->isEdit()
                     ? 'Perubahan langsung menghitung ulang hen-day, konversi pakan, dan nilai produksi.'
                     : 'Isi jumlah telur hasil panen dan pakan yang terpakai pada hari itu.'">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Kandang / Kelompok</label>
                        <select wire:model="form.kelompok_bebek_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <option value="">Pilih kandang</option>
                            @foreach ($flocks as $pilihan)
                                <option value="{{ $pilihan->id }}">
                                    {{ $pilihan->nama_kandang }} — {{ $pilihan->jenis_bebek }}
                                </option>
                            @endforeach
                        </select>
                        @error('form.kelompok_bebek_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Tanggal Produksi</label>
                        <input wire:model="form.tanggal_produksi" type="date"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('form.tanggal_produksi') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Bebek Pengeram (ekor)</label>
                        <input wire:model="form.jumlah_bebek" type="number" min="1" placeholder="Misal: 480"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <span class="text-xs text-gray-500">Dasar hitung hen-day %.</span>
                        @error('form.jumlah_bebek') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Telur Dihasilkan (butir)</label>
                        <input wire:model="form.telur_dihasilkan" type="number" min="0" placeholder="Misal: 420"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <span class="text-xs text-gray-500">Total hasil panen hari itu.</span>
                        @error('form.telur_dihasilkan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Telur Rusak / Cacat</label>
                        <input wire:model="form.telur_rusak" type="number" min="0" placeholder="Misal: 12"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <span class="text-xs text-gray-500">Pecah, retak, atau tidak layak.</span>
                        @error('form.telur_rusak') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Telur Layak Jual (butir)</label>
                        <input wire:model="form.telur_layak_jual" type="number" min="0" placeholder="Misal: 408"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('form.telur_layak_jual') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Pakan Terpakai (kg)</label>
                        <input wire:model="form.pakan_kg" type="number" step="0.01" min="0" placeholder="Opsional"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <span class="text-xs text-gray-500">Boleh dikosongkan.</span>
                        @error('form.pakan_kg') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Berat Butir Rata-rata (g)</label>
                        <input wire:model="form.berat_butir_g" type="number" step="0.1" min="0" placeholder="Opsional"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <span class="text-xs text-gray-500">Dari sampel penimbangan.</span>
                        @error('form.berat_butir_g') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Catatan</label>
                    <textarea wire:model="form.catatan" rows="3"
                              placeholder="Misal: panen dua kali sehari, telur kecil karena cuaca panas."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none"></textarea>
                    @error('form.catatan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
        <x-slot:aksi>
            <button wire:click="tutupModal" type="button"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors">
                Batal
            </button>
            <button type="button" @click="konfirmasiSimpan()"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors">
                {{ $form->isEdit() ? 'Simpan Koreksi' : 'Simpan Data' }}
            </button>
        </x-slot:aksi>
    </x-app-modal>

    {{-- Data grafik dikirim lewat atribut data supaya canvas tidak ikut ter-morph. --}}
    <div id="dataGrafikProduksi" class="hidden" data-grafik="{{ json_encode($grafik) }}"></div>

    @script
        <script>
            const gambarGrafikProduksi = () => {
                const data = Grafik.bacaDataGrafik('dataGrafikProduksi');

                if (!data || !Array.isArray(data.label) || data.label.length === 0) {
                    return;
                }

                Grafik.gambarGrafik('grafikProduksi', {
                    type: 'bar',
                    data: {
                        labels: data.label,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Layak Jual (butir)',
                                data: data.layak_jual,
                                backgroundColor: '#059669',
                                borderRadius: 4
                            },
                            {
                                type: 'line',
                                label: 'Rusak (butir)',
                                data: data.rusak,
                                borderColor: '#ef4444',
                                backgroundColor: '#ef4444',
                                tension: 0.3,
                                pointRadius: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: (nilai) => nilai + ' btr' } }
                        }
                    }
                });

                Grafik.gambarGrafik('grafikHenDay', {
                    type: 'line',
                    data: {
                        labels: data.label,
                        datasets: [
                            {
                                label: 'Hen-day (%)',
                                data: data.hen_day,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99, 102, 241, 0.12)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: (nilai) => nilai + '%' } }
                        }
                    }
                });
            };

            gambarGrafikProduksi();
            Grafik.pantauGrafik('produksi-chart-refresh', gambarGrafikProduksi);
            Grafik.pasangPeringatanSwal();
        </script>
    @endscript
</div>
