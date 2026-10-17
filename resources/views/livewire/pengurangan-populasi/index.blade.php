<?php

use App\Livewire\Forms\PenguranganPopulasiForm;
use App\Models\KelompokBebek;
use App\Models\PenguranganPopulasi;
use App\Support\PopulasiBebek;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public PenguranganPopulasiForm $form;

    /** Filter kandang; bisa ditautkan dari halaman Data Bebek (?kelompok=ID). */
    #[Url(as: 'kelompok', except: '')]
    public $kelompokFilter = '';

    public $startDate = '';

    public $endDate = '';

    public $kategoriFilter = 'Semua';

    /** Asumsi harga beli per ekor untuk menaksir nilai kerugian (per tampilan, tidak disimpan). */
    public $hargaPerEkor = 55000;

    public $isModalOpen = false;

    public function mount(): void
    {
        // Nilai bawaan hanya dipakai bila tidak ada parameter di URL.
        $this->startDate = $this->startDate ?: today()->subDays(29)->toDateString();
        $this->endDate = $this->endDate ?: today()->toDateString();
        $this->form->tanggal = today()->toDateString();
    }

    public function updatedKelompokFilter(): void
    {
        $this->resetPage();
        $this->segarkanGrafik();
    }

    public function updatedKategoriFilter(): void
    {
        $this->resetPage();
        $this->segarkanGrafik();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    /** Canvas tidak ikut ter-morph, jadi sisi klien diminta menggambar ulang. */
    private function segarkanGrafik(): void
    {
        $this->dispatch('deplesi-chart-refresh');
    }

    public function openModal(): void
    {
        $this->resetValidation();
        $this->form->reset();
        $this->form->tanggal = today()->toDateString();
        $this->form->kelompok_bebek_id = $this->kelompokFilter !== ''
            ? $this->kelompokFilter
            : (KelompokBebek::query()->aktif()->orderBy('nama_kandang')->value('id') ?? '');

        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $this->form->setRekaman(PenguranganPopulasi::findOrFail($id));
        $this->isModalOpen = true;
    }

    public function tutupModal(): void
    {
        $this->isModalOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->form->validate();

        $dikoreksi = $this->form->isEdit();

        if ($dikoreksi) {
            $this->perbaruiRekaman();
        } else {
            $this->simpanRekaman();
        }

        $this->isModalOpen = false;
        $this->form->reset();
        $this->form->tanggal = today()->toDateString();
        $this->resetPage();

        $this->dispatch('swal:alert', [
            'title' => 'Tersimpan!',
            'text' => $dikoreksi
                ? 'Catatan deplesi dikoreksi dan populasi kandang disesuaikan.'
                : 'Kejadian deplesi tercatat dan populasi aktif kandang diperbarui.',
            'icon' => 'success',
        ]);

        $this->segarkanGrafik();
    }

    public function hapus(int $id): void
    {
        DB::transaction(function () use ($id) {
            $rekaman = PenguranganPopulasi::findOrFail($id);
            $kelompok = $this->kandangTerkunci((int) $rekaman->kelompok_bebek_id);

            // Batalkan efek catatan: populasi aktif dikembalikan lebih dulu.
            $this->sesuaikanPopulasi($kelompok, (int) $rekaman->jumlah);

            $rekaman->delete();
        });

        $this->resetPage();

        $this->dispatch('swal:alert', [
            'title' => 'Terhapus!',
            'text' => 'Catatan deplesi dihapus dan populasi kandang dikembalikan.',
            'icon' => 'success',
        ]);

        $this->segarkanGrafik();
    }

    private function simpanRekaman(): void
    {
        DB::transaction(function () {
            $kelompok = $this->kandangTerkunci((int) $this->form->kelompok_bebek_id);

            $this->pastikanJumlahDiizinkan($kelompok);

            PenguranganPopulasi::create($this->form->payload());

            $this->sesuaikanPopulasi($kelompok, -(int) $this->form->jumlah);
        });
    }

    private function perbaruiRekaman(): void
    {
        DB::transaction(function () {
            $rekaman = PenguranganPopulasi::findOrFail($this->form->id);
            $kandangSebelumnya = (int) $rekaman->kelompok_bebek_id;
            $jumlahSebelumnya = (int) $rekaman->jumlah;

            $kelompok = $this->kandangTerkunci((int) $this->form->kelompok_bebek_id);

            $this->pastikanJumlahDiizinkan($kelompok);

            $rekaman->update($this->form->payload());

            if ($kandangSebelumnya === (int) $kelompok->id) {
                // Kandang sama: cukup sesuaikan selisih jumlahnya.
                $this->sesuaikanPopulasi($kelompok, $jumlahSebelumnya - (int) $this->form->jumlah);

                return;
            }

            // Pindah kandang: kembalikan ke kandang lama, kurangi kandang baru.
            $this->sesuaikanPopulasi($this->kandangTerkunci($kandangSebelumnya), $jumlahSebelumnya);
            $this->sesuaikanPopulasi($kelompok, -(int) $this->form->jumlah);
        });
    }

    /** Baca kandang di dalam transaksi supaya dua pencatatan bersamaan tidak saling menimpa. */
    private function kandangTerkunci(int $id): KelompokBebek
    {
        return KelompokBebek::query()->lockForUpdate()->findOrFail($id);
    }

    private function pastikanJumlahDiizinkan(KelompokBebek $kelompok): void
    {
        $batas = PopulasiBebek::batasPencatatan(
            (int) $kelompok->populasi_aktif,
            $this->form->jumlahDikembalikan()
        );

        if ((int) $this->form->jumlah <= $batas) {
            return;
        }

        throw ValidationException::withMessages([
            'form.jumlah' => 'Jumlah melebihi populasi aktif '.$kelompok->nama_kandang
                .' (maksimal '.number_format($batas, 0, ',', '.').' ekor).',
        ]);
    }

    /** delta positif = populasi dikembalikan, negatif = populasi dikurangi. */
    private function sesuaikanPopulasi(KelompokBebek $kelompok, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $aktif = (int) $kelompok->populasi_aktif;

        // Pengembalian tidak pernah melebihi populasi awal — dan tidak pernah
        // menurunkan populasi yang sudah di atas populasi awal.
        $plafon = max($aktif, (int) $kelompok->populasi_awal);

        $kelompok->populasi_aktif = $delta > 0
            ? min($plafon, PopulasiBebek::setelahPemulihan($aktif, $delta))
            : PopulasiBebek::setelahPengurangan($aktif, abs($delta));

        $kelompok->save();
    }

    /**
     * Builder dasar laporan; selalu instance baru supaya agregat dan paginator
     * tidak saling mewarisi limit/order.
     */
    private function riwayatQuery()
    {
        return PenguranganPopulasi::query()
            ->when($this->kelompokFilter !== '', fn ($q) => $q->where('kelompok_bebek_id', (int) $this->kelompokFilter))
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->when($this->kategoriFilter !== 'Semua', fn ($q) => $q->where('kategori', $this->kategoriFilter));
    }

    /**
     * Cakupan kandang untuk kartu populasi: kandang terpilih, atau seluruh
     * kandang aktif bila tidak ada filter.
     */
    private function cakupanKandang(Collection $kandang): Collection
    {
        return $this->kelompokFilter !== ''
            ? $kandang->where('id', (int) $this->kelompokFilter)->values()
            : $kandang->where('status', 'Aktif')->values();
    }

    public function with(): array
    {
        $kandang = KelompokBebek::query()
            ->orderBy('nama_kandang')
            ->get(['id', 'nama_kandang', 'jenis_bebek', 'status', 'populasi_awal', 'populasi_aktif']);

        // Satu query agregat untuk seluruh kartu ringkasan.
        $agg = $this->riwayatQuery()->selectRaw('
            COALESCE(SUM(jumlah), 0)          AS total_ekor,
            COUNT(*)                          AS total_catatan,
            COUNT(DISTINCT kelompok_bebek_id) AS kandang_terdampak,
            COUNT(DISTINCT tanggal)           AS jumlah_hari
        ')->first();

        $perKategori = $this->riwayatQuery()
            ->groupBy('kategori')
            ->orderByDesc('ekor')
            ->selectRaw('kategori, COALESCE(SUM(jumlah), 0) AS ekor, COUNT(*) AS catatan')
            ->get();

        $perPenyebab = $this->riwayatQuery()
            ->groupBy('penyebab')
            ->orderByDesc('ekor')
            ->selectRaw('penyebab, COALESCE(SUM(jumlah), 0) AS ekor, COUNT(*) AS catatan')
            ->get();

        $riwayat = $this->riwayatQuery()
            ->with('kelompokBebek:id,nama_kandang,jenis_bebek,populasi_awal')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (PenguranganPopulasi $baris) {
                $baris->persen_kandang = PopulasiBebek::deplesiPersen(
                    (int) $baris->jumlah,
                    (int) ($baris->kelompokBebek?->populasi_awal ?? 0)
                );

                return $baris;
            });

        // Tren harian 30 hari terakhir mengikuti filter kandang + kategori.
        $harian = PenguranganPopulasi::query()
            ->when($this->kelompokFilter !== '', fn ($q) => $q->where('kelompok_bebek_id', (int) $this->kelompokFilter))
            ->when($this->kategoriFilter !== 'Semua', fn ($q) => $q->where('kategori', $this->kategoriFilter))
            ->whereDate('tanggal', '>=', today()->subDays(29)->toDateString())
            ->groupBy('tanggal')
            ->selectRaw('tanggal, COALESCE(SUM(jumlah), 0) AS ekor')
            ->get();

        $petaHarian = $harian->mapWithKeys(
            fn (PenguranganPopulasi $baris) => [$baris->tanggal?->toDateString() => (int) $baris->ekor]
        )->all();

        $label = [];
        $perHari = [];
        $kumulatif = [];
        $berjalan = 0;

        for ($selisih = 29; $selisih >= 0; $selisih--) {
            $tanggal = today()->subDays($selisih);
            $ekor = $petaHarian[$tanggal->toDateString()] ?? 0;

            $berjalan += $ekor;

            $label[] = $tanggal->format('d/m');
            $perHari[] = $ekor;
            $kumulatif[] = $berjalan;
        }

        $cakupan = $this->cakupanKandang($kandang);
        $populasiAwal = (int) $cakupan->sum('populasi_awal');
        $populasiAktif = (int) $cakupan->sum('populasi_aktif');
        $deplesiKumulatif = max(0, $populasiAwal - $populasiAktif);
        $deplesiPersen = PopulasiBebek::deplesiPersen($deplesiKumulatif, $populasiAwal);

        $totalEkor = (int) $agg->total_ekor;

        return [
            'kandang' => $kandang,

            'opsi_kategori' => PopulasiBebek::KATEGORI,
            'opsi_penyebab' => PopulasiBebek::PENYEBAB,

            'riwayat' => $riwayat,

            'ringkasan' => [
                'total_ekor' => $totalEkor,
                'total_catatan' => (int) $agg->total_catatan,
                'kandang_terdampak' => (int) $agg->kandang_terdampak,
                'jumlah_hari' => (int) $agg->jumlah_hari,
                'rata_per_catatan' => (int) $agg->total_catatan > 0
                    ? round($totalEkor / (int) $agg->total_catatan, 1)
                    : 0.0,
            ],

            // Deplesi kumulatif seumur pemeliharaan (dari populasi awal kandang).
            'populasi' => [
                'jumlah_kandang' => $cakupan->count(),
                'nama_kandang' => $cakupan->count() === 1 ? $cakupan->first()->nama_kandang : null,
                'populasi_awal' => $populasiAwal,
                'populasi_aktif' => $populasiAktif,
                'deplesi_ekor' => $deplesiKumulatif,
                'deplesi_persen' => $deplesiPersen,
                'daya_hidup_persen' => PopulasiBebek::dayaHidupPersen($populasiAktif, $populasiAwal),
                'predikat' => PopulasiBebek::predikatDeplesi($deplesiPersen),
            ],

            'kategori' => $perKategori,
            'penyebab' => $perPenyebab,
            'penyebab_dominan' => $perPenyebab->first(),

            'estimasi_kerugian' => PopulasiBebek::estimasiKerugian($totalEkor, (float) $this->hargaPerEkor),

            'grafik' => [
                'label' => $label,
                'ekor' => $perHari,
                'kumulatif' => $kumulatif,
                'penyebab_label' => $perPenyebab->pluck('penyebab')->all(),
                'penyebab_ekor' => $perPenyebab->map(fn ($baris) => (int) $baris->ekor)->all(),
                'ada_data' => $harian->count() > 0,
            ],

            // Pratinjau populasi hanya dihitung saat modal terbuka.
            'konteks' => $this->isModalOpen ? $this->form->konteks() : null,
        ];
    }
}; ?>

<div class="space-y-6"
     x-data="{
        konfirmasiSimpan() {
            Grafik.konfirmasi({
                judul: '{{ $form->isEdit() ? 'Simpan koreksi catatan deplesi?' : 'Simpan catatan deplesi?' }}',
                teks: 'Populasi aktif kandang akan langsung disesuaikan sebanyak jumlah ekor yang dicatat.',
                tombol: 'Ya, simpan',
                aksi: () => $wire.save(),
            });
        },
        konfirmasiHapus(id) {
            Grafik.konfirmasi({
                judul: 'Hapus catatan deplesi?',
                teks: 'Jumlah ekor akan dikembalikan ke populasi aktif kandang.',
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
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kematian &amp; Deplesi</h1>
            <p class="text-sm text-gray-500 mt-1">
                Catat bebek mati, afkir, atau hilang. Populasi aktif kandang dan tingkat deplesi diperbarui otomatis.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full xl:w-auto">
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <select wire:model.live="kelompokFilter"
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Semua kandang</option>
                    @foreach ($kandang as $pilihan)
                        <option value="{{ $pilihan->id }}">
                            {{ $pilihan->nama_kandang }} &bull; {{ number_format($pilihan->populasi_aktif, 0, ',', '.') }} ekor
                        </option>
                    @endforeach
                </select>

                <select wire:model.live="kategoriFilter"
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white cursor-pointer focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="Semua">Semua kategori</option>
                    @foreach ($opsi_kategori as $pilihanKategori)
                        <option value="{{ $pilihanKategori }}">{{ $pilihanKategori }}</option>
                    @endforeach
                </select>

                <input wire:model.live="startDate" type="date"
                       class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">

                <input wire:model.live="endDate" type="date"
                       class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>

            <button wire:click="openModal" type="button"
                    class="flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm shrink-0">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Catat Kematian
            </button>
        </div>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Daya Hidup</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($populasi['daya_hidup_persen'], 1, ',', '.') }}<span class="text-sm font-normal text-gray-500">%</span>
                    </p>
                </div>
                <div class="p-2.5 {{ $populasi['daya_hidup_persen'] >= 95 ? 'bg-emerald-50 text-emerald-600' : ($populasi['daya_hidup_persen'] >= 90 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600') }} rounded-lg shrink-0">
                    <span class="material-symbols-outlined">cruelty_free</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                {{ $populasi['nama_kandang'] ?? $populasi['jumlah_kandang'].' kandang aktif' }}
                &bull; {{ number_format($populasi['populasi_aktif'], 0, ',', '.') }} dari
                {{ number_format($populasi['populasi_awal'], 0, ',', '.') }} ekor
            </p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Deplesi Kumulatif</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($populasi['deplesi_persen'], 1, ',', '.') }}<span class="text-sm font-normal text-gray-500">%</span>
                    </p>
                </div>
                <div class="p-2.5 bg-red-50 text-red-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">trending_down</span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full {{ $populasi['deplesi_persen'] <= 3 ? 'bg-emerald-500' : ($populasi['deplesi_persen'] <= 6 ? 'bg-amber-500' : 'bg-red-500') }}"
                         style="width: {{ min(100, $populasi['deplesi_persen']) }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    {{ number_format($populasi['deplesi_ekor'], 0, ',', '.') }} ekor hilang dari populasi awal
                    &bull; {{ $populasi['predikat'] }}
                </p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tercatat di Rentang</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ number_format($ringkasan['total_ekor'], 0, ',', '.') }}
                        <span class="text-sm font-normal text-gray-500">ekor</span>
                    </p>
                </div>
                <div class="p-2.5 bg-amber-50 text-amber-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">event_note</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                {{ number_format($ringkasan['total_catatan'], 0, ',', '.') }} catatan
                &bull; {{ number_format($ringkasan['jumlah_hari'], 0, ',', '.') }} hari
                &bull; {{ number_format($ringkasan['kandang_terdampak'], 0, ',', '.') }} kandang
            </p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estimasi Kerugian</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        <span class="text-sm font-normal text-gray-500">Rp</span>
                        {{ number_format($estimasi_kerugian, 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-lg shrink-0">
                    <span class="material-symbols-outlined">payments</span>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <label class="text-xs text-gray-500 shrink-0">Harga/ekor (Rp)</label>
                <input wire:model.live.debounce.500ms="hargaPerEkor" type="number" min="0" step="1000"
                       class="w-full px-2 py-1 border border-gray-200 rounded-lg text-xs focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>
    </div>

    {{-- Grafik --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Deplesi Harian</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Ekor yang hilang per hari dan akumulasinya selama 30 hari terakhir.
            </p>

            @if ($grafik['ada_data'])
                <div class="h-72" wire:ignore>
                    <canvas id="grafikDeplesi"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">heart_broken</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Tidak ada deplesi tercatat 30 hari terakhir</p>
                    <p class="text-xs text-gray-500 mt-1">Kabar baik — atau catatan belum diisi.</p>
                </div>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Komposisi Penyebab</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Proporsi ekor hilang per penyebab pada rentang terpilih.</p>

            @if ($penyebab->isNotEmpty())
                <div class="h-72" wire:ignore>
                    <canvas id="grafikPenyebab"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">pie_chart</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada data penyebab</p>
                    <p class="text-xs text-gray-500 mt-1">Grafik terbentuk setelah ada pencatatan.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Rekap kategori + penyebab --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-900">Rekap per Kategori</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Kematian asli dipisahkan dari afkir dan kehilangan.</p>

            @forelse ($kategori as $rekap)
                <div class="mb-3 last:mb-0">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-800">{{ $rekap->kategori }}</span>
                        <span class="text-gray-600">
                            {{ number_format($rekap->ekor, 0, ',', '.') }} ekor
                            <span class="text-xs text-gray-400">({{ number_format($rekap->catatan, 0, ',', '.') }} catatan)</span>
                        </span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden mt-1.5">
                        <div class="h-full bg-emerald-500"
                             style="width: {{ $ringkasan['total_ekor'] > 0 ? round($rekap->ekor / $ringkasan['total_ekor'] * 100, 1) : 0 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 py-6 text-center">Belum ada catatan pada rentang ini.</p>
            @endforelse
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-900">Rekap per Penyebab</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Penyebab terbanyak jadi prioritas penanganan kandang.</p>
                </div>
                @if ($penyebab_dominan)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 shrink-0">
                        <span class="material-symbols-outlined text-[16px]">warning</span>
                        {{ $penyebab_dominan->penyebab }}
                    </span>
                @endif
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase tracking-wider font-semibold text-gray-500 border-b border-gray-200">
                            <th class="pb-2">Penyebab</th>
                            <th class="pb-2 text-right">Ekor</th>
                            <th class="pb-2 text-right">Porsi</th>
                            <th class="pb-2 text-right">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($penyebab as $rekap)
                            <tr>
                                <td class="py-2 text-sm text-gray-800">{{ $rekap->penyebab }}</td>
                                <td class="py-2 text-sm text-right font-semibold text-gray-900">{{ number_format($rekap->ekor, 0, ',', '.') }}</td>
                                <td class="py-2 text-sm text-right text-gray-600">
                                    {{ $ringkasan['total_ekor'] > 0 ? number_format($rekap->ekor / $ringkasan['total_ekor'] * 100, 1, ',', '.') : '0' }}%
                                </td>
                                <td class="py-2 text-sm text-right text-gray-500">{{ number_format($rekap->catatan, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-sm text-gray-500">Belum ada catatan pada rentang ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Riwayat --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">Riwayat Kematian &amp; Deplesi</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Persentase dihitung terhadap populasi awal kandang. Koreksi catatan ikut menyesuaikan populasi aktif.
                </p>
            </div>
            <span class="text-xs text-gray-500 shrink-0">{{ $riwayat->total() }} baris</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Kandang</th>
                        <th class="p-4 text-center">Kategori</th>
                        <th class="p-4">Penyebab</th>
                        <th class="p-4 text-right">Jumlah</th>
                        <th class="p-4 text-right">% Kandang</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($riwayat as $baris)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $baris->tanggal?->translatedFormat('d M Y') ?? '-' }}</p>
                                <p class="text-xs text-gray-500">{{ $baris->tanggal?->translatedFormat('l') }}</p>
                            </td>
                            <td class="p-4">
                                <p class="text-sm font-medium text-gray-900">{{ $baris->kelompokBebek?->nama_kandang ?? 'Kandang dihapus' }}</p>
                                <p class="text-xs text-gray-500">{{ $baris->kelompokBebek?->jenis_bebek ?? '-' }}</p>
                            </td>
                            <td class="p-4 text-center">
                                @php
                                    $warnaKategori = match ($baris->kategori) {
                                        'Mati' => 'bg-red-50 text-red-700 border-red-200',
                                        'Afkir' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium border {{ $warnaKategori }}">
                                    {{ $baris->kategori ?? 'Mati' }}
                                </span>
                            </td>
                            <td class="p-4">
                                <p class="text-sm text-gray-800">{{ $baris->penyebab }}</p>
                                @if ($baris->catatan)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ \Illuminate\Support\Str::limit($baris->catatan, 60) }}</p>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-bold text-red-600">-{{ number_format($baris->jumlah, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">ekor</p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-medium text-gray-800">{{ number_format($baris->persen_kandang, 1, ',', '.') }}%</p>
                                <p class="text-xs text-gray-500">dari {{ number_format($baris->kelompokBebek?->populasi_awal ?? 0, 0, ',', '.') }}</p>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="edit({{ $baris->id }})"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                            title="Koreksi catatan">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button @click="konfirmasiHapus({{ $baris->id }})"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                            title="Hapus catatan">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">heart_broken</span>
                                    <p class="font-medium text-gray-900">Tidak ada catatan deplesi</p>
                                    <p class="text-sm mt-1">Ubah rentang tanggal atau catat kejadian baru.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($riwayat->hasPages())
            <div class="p-4 border-t border-gray-200 bg-white">
                {{ $riwayat->links() }}
            </div>
        @endif
    </div>

    {{-- Modal pencatatan / koreksi --}}
    <x-app-modal lebar="max-w-lg"
                 :judul="$form->isEdit() ? 'Koreksi Catatan Deplesi' : 'Catat Kematian / Deplesi'"
                 subjudul="Populasi aktif kandang ikut disesuaikan saat disimpan.">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Kandang / Kelompok</label>
                    <select wire:model.live="form.kelompok_bebek_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                        <option value="">-- Pilih kandang --</option>
                        @foreach ($kandang as $pilihan)
                            <option value="{{ $pilihan->id }}">
                                {{ $pilihan->nama_kandang }} &bull; {{ number_format($pilihan->populasi_aktif, 0, ',', '.') }} ekor{{ $pilihan->status !== 'Aktif' ? ' (kosong)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('form.kelompok_bebek_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Tanggal Kejadian</label>
                        <input wire:model="form.tanggal" type="date" max="{{ today()->toDateString() }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('form.tanggal') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Kategori</label>
                        <select wire:model="form.kategori"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            @foreach ($opsi_kategori as $pilihanKategori)
                                <option value="{{ $pilihanKategori }}">{{ $pilihanKategori }}</option>
                            @endforeach
                        </select>
                        @error('form.kategori') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Penyebab</label>
                        <select wire:model="form.penyebab"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            @foreach ($opsi_penyebab as $pilihanPenyebab)
                                <option value="{{ $pilihanPenyebab }}">{{ $pilihanPenyebab }}</option>
                            @endforeach
                        </select>
                        @error('form.penyebab') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Jumlah (Ekor)</label>
                        <input wire:model.live="form.jumlah" type="number" min="1"
                               placeholder="Misal: 3"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('form.jumlah') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if ($konteks && $konteks['ada'])
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
                        <p class="font-semibold text-gray-700 mb-1.5">Dampak pada {{ $konteks['nama_kandang'] }}</p>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-white rounded-md border border-gray-200 py-2">
                                <p class="text-gray-500">Populasi aktif</p>
                                <p class="font-bold text-gray-900 mt-0.5">{{ number_format($konteks['populasi_aktif'], 0, ',', '.') }} ekor</p>
                            </div>
                            <div class="bg-white rounded-md border border-gray-200 py-2">
                                <p class="text-gray-500">Sisa setelah dicatat</p>
                                <p class="font-bold {{ $konteks['sisa'] > 0 ? 'text-emerald-700' : 'text-red-600' }} mt-0.5">
                                    {{ number_format($konteks['sisa'], 0, ',', '.') }} ekor
                                </p>
                            </div>
                            <div class="bg-white rounded-md border border-gray-200 py-2">
                                <p class="text-gray-500">Deplesi kandang</p>
                                <p class="font-bold text-gray-900 mt-0.5">{{ number_format($konteks['deplesi_persen'], 1, ',', '.') }}%</p>
                            </div>
                        </div>
                        <p class="mt-2 text-center">
                            Maksimal yang bisa dicatat: <span class="font-semibold text-gray-700">{{ number_format($konteks['batas'], 0, ',', '.') }}</span> ekor.
                        </p>
                    </div>
                @endif

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Catatan Tambahan <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <textarea wire:model="form.catatan" rows="2" placeholder="Misal: gejala ngorok sejak 2 hari, sudah dipisah ke kandang karantina"
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
                    {{ $form->isEdit() ? 'Simpan Koreksi' : 'Simpan Catatan' }}
                </button>
            </x-slot:aksi>
        </x-app-modal>

    {{-- Data grafik dikirim lewat atribut data supaya canvas tidak ikut ter-morph. --}}
    <div id="dataGrafikDeplesi" class="hidden" data-grafik="{{ json_encode($grafik) }}"></div>

    @script
        <script>
            const gambarGrafikDeplesi = () => {
                const data = Grafik.bacaDataGrafik('dataGrafikDeplesi');

                if (!data || !Array.isArray(data.label) || data.label.length === 0) {
                    return;
                }

                Grafik.gambarGrafik('grafikDeplesi', {
                    data: {
                        labels: data.label,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Ekor hilang',
                                data: data.ekor,
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                                yAxisID: 'y'
                            },
                            {
                                type: 'line',
                                label: 'Akumulasi 30 hari',
                                data: data.kumulatif,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99, 102, 241, 0.12)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2,
                                yAxisID: 'y1'
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
                            y: { beginAtZero: true, position: 'left', ticks: { callback: (nilai) => nilai + ' ekor' } },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                ticks: { callback: (nilai) => nilai + ' ekor' }
                            }
                        }
                    }
                });

                if (Array.isArray(data.penyebab_label) && data.penyebab_label.length > 0) {
                    Grafik.gambarGrafik('grafikPenyebab', {
                        type: 'doughnut',
                        data: {
                            labels: data.penyebab_label,
                            datasets: [
                                {
                                    data: data.penyebab_ekor,
                                    backgroundColor: [
                                        '#ef4444', '#f97316', '#f59e0b', '#10b981',
                                        '#0ea5e9', '#6366f1', '#a855f7'
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
                                        label: (konteks) => konteks.label + ': ' + konteks.parsed + ' ekor'
                                    }
                                }
                            }
                        }
                    });
                }
            };

            gambarGrafikDeplesi();
            Grafik.pantauGrafik('deplesi-chart-refresh', gambarGrafikDeplesi);
            Grafik.pasangPeringatanSwal();
        </script>
    @endscript
</div>
