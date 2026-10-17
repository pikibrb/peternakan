<?php

use App\Livewire\Forms\MonitoringPertumbuhanForm;
use App\Models\KelompokBebek;
use App\Models\MonitoringPertumbuhan;
use App\Models\PenguranganPopulasi;
use App\Support\AnalisisPertumbuhan;
use Livewire\Volt\Component;

new class extends Component {
    public MonitoringPertumbuhanForm $form;

    public $kelompokId = null;

    public $isModalOpen = false;

    public function mount(): void
    {
        // Satu query skalar untuk memilih kelompok default (status 'Aktif' lebih dulu).
        $this->kelompokId = KelompokBebek::query()
            ->orderBy('status')
            ->orderBy('nama_kandang')
            ->value('id');
    }

    public function updatedKelompokId(): void
    {
        $this->tutupModal();
        $this->form->reset();
        $this->dispatch('pertumbuhan-chart-refresh');
    }

    public function openModal(): void
    {
        $kelompok = $this->kelompokAktif();

        if ($kelompok === null) {
            return;
        }

        $this->resetValidation();
        $this->form->reset();
        $this->form->setKelompok($kelompok);
        $this->form->tanggal = today()->toDateString();
        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $kelompok = $this->kelompokAktif();

        if ($kelompok === null) {
            return;
        }

        $rekaman = MonitoringPertumbuhan::query()
            ->where('kelompok_bebek_id', $kelompok->id)
            ->find($id);

        if ($rekaman === null) {
            return;
        }

        $this->resetValidation();
        $this->form->reset();
        $this->form->setKelompok($kelompok);
        $this->form->setRekaman($rekaman);
        $this->isModalOpen = true;
    }

    public function save(): void
    {
        $dikoreksi = $this->form->isEdit();

        if ($dikoreksi) {
            $this->form->update(MonitoringPertumbuhan::findOrFail($this->form->id));
        } else {
            $this->form->store();
        }

        $this->tutupModal();
        $this->form->reset();

        $this->dispatch('swal:alert', [
            'title' => 'Tersimpan!',
            'text' => $dikoreksi
                ? 'Catatan sampling dikoreksi dan kurva pertumbuhan dihitung ulang.'
                : 'Data sampling pertumbuhan berhasil disimpan.',
            'icon' => 'success',
        ]);

        $this->dispatch('pertumbuhan-chart-refresh');
    }

    public function hapus(int $id): void
    {
        $kelompok = $this->kelompokAktif();

        if ($kelompok === null) {
            return;
        }

        $rekaman = MonitoringPertumbuhan::query()
            ->where('kelompok_bebek_id', $kelompok->id)
            ->find($id);

        if ($rekaman === null) {
            return;
        }

        $rekaman->delete();

        $this->dispatch('swal:alert', [
            'title' => 'Terhapus!',
            'text' => 'Baris sampling sudah dihapus dari riwayat.',
            'icon' => 'success',
        ]);

        $this->dispatch('pertumbuhan-chart-refresh');
    }

    public function tutupModal(): void
    {
        $this->isModalOpen = false;
        $this->resetValidation();
    }

    /**
     * Kelompok terpilih untuk aksi tulis (tambah/edit/hapus).
     */
    private function kelompokAktif(): ?KelompokBebek
    {
        return $this->kelompokId === null
            ? null
            : KelompokBebek::find($this->kelompokId);
    }

    public function with(): array
    {
        // 1 query: seluruh kelompok, sekaligus menjadi sumber data pemilih kelompok.
        $kelompok = KelompokBebek::query()
            ->orderBy('status')
            ->orderBy('nama_kandang')
            ->get();

        $terpilih = ($this->kelompokId === null ? null : $kelompok->firstWhere('id', $this->kelompokId))
            ?? $kelompok->first();

        if ($terpilih === null) {
            return [
                'kelompok' => $kelompok,
                'kelompokTerpilih' => null,
                'analisis' => null,
                'grafik' => [],
                'opsi_kondisi' => MonitoringPertumbuhanForm::KONDISI,
            ];
        }

        // 1 query: riwayat sampling kelompok terpilih (terurut naik, dipakai
        // sekaligus untuk grafik, tabel, dan perhitungan kumulatif).
        $sampel = MonitoringPertumbuhan::query()
            ->where('kelompok_bebek_id', $terpilih->id)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        // 1 query agregat: total deplesi kelompok ini.
        $deplesi = PenguranganPopulasi::query()
            ->where('kelompok_bebek_id', $terpilih->id)
            ->sum('jumlah');

        // Seluruh metrik dihitung dari dua koleksi di atas tanpa query tambahan.
        $analisis = AnalisisPertumbuhan::hitung($terpilih, $sampel, $deplesi);

        return [
            'kelompok' => $kelompok,
            'kelompokTerpilih' => $terpilih,
            'analisis' => $analisis,
            'grafik' => $analisis['grafik'],
            'opsi_kondisi' => MonitoringPertumbuhanForm::KONDISI,
        ];
    }
}; ?>

<div class="space-y-6"
     x-data="{
        konfirmasiSimpan() {
            Grafik.konfirmasi({
                judul: '{{ $form->isEdit() ? 'Simpan koreksi sampling?' : 'Simpan data sampling?' }}',
                teks: 'Pastikan tanggal, berat rata-rata, dan jumlah pakan sudah sesuai catatan kandang.',
                tombol: 'Ya, simpan',
                aksi: () => $wire.save(),
            });
        },
        konfirmasiHapus(id) {
            Grafik.konfirmasi({
                judul: 'Hapus data sampling?',
                teks: 'Baris ini akan hilang dari riwayat dan kurva pertumbuhan.',
                ikon: 'warning',
                warna: '#dc2626',
                tombol: 'Ya, hapus',
                aksi: () => $wire.hapus(id),
            });
        }
     }">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Monitoring Pertumbuhan</h1>
            <p class="text-sm text-gray-500 mt-1">
                Kurva pertumbuhan, ADG, FCR, konsumsi pakan, dan keseragaman populasi per kelompok (flock).
            </p>
        </div>

        @if ($kelompok->isNotEmpty())
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <div class="relative w-full sm:w-72">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">warehouse</span>
                    <select wire:model.live="kelompokId"
                            class="w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white cursor-pointer">
                        @foreach ($kelompok as $pilihan)
                            <option value="{{ $pilihan->id }}">
                                {{ $pilihan->nama_kandang }} — {{ $pilihan->jenis_bebek }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button wire:click="openModal" type="button"
                        class="flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Catat Sampling
                </button>
            </div>
        @endif
    </div>

    @if ($kelompokTerpilih === null)
        {{-- Belum ada kelompok sama sekali --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-10 text-center">
            <span class="material-symbols-outlined text-5xl text-gray-300">monitor_weight</span>
            <p class="font-semibold text-gray-900 mt-3">Belum ada kelompok bebek untuk dipantau</p>
            <p class="text-sm text-gray-500 mt-1">Daftarkan kandang/flock terlebih dahulu, lalu catat sampling pertumbuhannya di sini.</p>
            <a href="{{ route('data-bebek') }}" wire:navigate
               class="inline-flex items-center gap-2 mt-5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Buka Data Bebek
            </a>
        </div>
    @else
        @php
            $ringkasan = $analisis['ringkasan'];
            $adaSampling = $ringkasan['jumlah_sampling'] > 0;
        @endphp

        {{-- Identitas kelompok + jadwal sampling --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl">cruelty_free</span>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-gray-900">{{ $kelompokTerpilih->nama_kandang }}</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border
                                {{ $kelompokTerpilih->fase === 'Layer' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                {{ $kelompokTerpilih->fase }}
                            </span>
                            @if ($kelompokTerpilih->status === 'Aktif')
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Kosong
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $kelompokTerpilih->jenis_bebek }} &bull;
                            Masuk {{ $kelompokTerpilih->tanggal_masuk?->format('d/m/Y') }} &bull;
                            Umur {{ $kelompokTerpilih->umur_minggu }} minggu
                            ({{ $ringkasan['umur_awal_hari'] }} hari saat check-in)
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Populasi Aktif</p>
                        <p class="text-lg font-bold text-gray-900">
                            {{ number_format($ringkasan['populasi_aktif'], 0, ',', '.') }}
                            <span class="text-xs font-normal text-gray-500">ekor</span>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sampling Terakhir</p>
                        @if ($adaSampling)
                            <p class="text-lg font-bold {{ $ringkasan['sampling_terlambat'] ? 'text-amber-600' : 'text-gray-900' }}">
                                {{ $ringkasan['tanggal_terakhir']?->format('d/m/Y') }}
                            </p>
                            <p class="text-xs {{ $ringkasan['sampling_terlambat'] ? 'text-amber-600' : 'text-gray-500' }}">
                                {{ $ringkasan['hari_sejak_sampling'] }} hari lalu
                                @if ($ringkasan['sampling_terlambat'])
                                    &bull; terlambat dari jadwal {{ $ringkasan['selang_sampling_ideal'] }} hari
                                @endif
                            </p>
                        @else
                            <p class="text-lg font-bold text-gray-400">&mdash;</p>
                            <p class="text-xs text-gray-500">belum pernah sampling</p>
                        @endif
                    </div>

                    @if ($adaSampling)
                        <span class="material-symbols-outlined text-3xl {{ $ringkasan['sampling_terlambat'] ? 'text-amber-500' : 'text-emerald-500' }}">
                            {{ $ringkasan['sampling_terlambat'] ? 'event_busy' : 'event_available' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kartu performa --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {{-- Berat rata-rata --}}
            <x-stat-card label="Berat Rata-rata" ikon="monitor_weight" warna="blue"
                         :nilai="$ringkasan['berat_terakhir'] !== null ? number_format($ringkasan['berat_terakhir'], 1, ',', '.') : '—'"
                         :satuan="$ringkasan['berat_terakhir'] !== null ? 'gram/ekor' : null">
                <x-slot:footer>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($ringkasan['status_berat'] !== null)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md font-medium border
                                {{ $ringkasan['deviasi'] >= 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                {{ $ringkasan['status_berat'] }} ({{ $ringkasan['deviasi'] > 0 ? '+' : '' }}{{ $ringkasan['deviasi'] }}%)
                            </span>
                            <span class="text-gray-500">Target {{ number_format($ringkasan['target_berat'], 0, ',', '.') }} g</span>
                        @else
                            <span class="text-gray-500">Target standar tersedia mulai sampling pertama.</span>
                        @endif
                    </div>
                </x-slot:footer>
            </x-stat-card>

            {{-- ADG --}}
            <x-stat-card label="ADG / Pertambahan Harian" ikon="trending_up" warna="emerald"
                         :nilai="$ringkasan['adg'] !== null ? number_format($ringkasan['adg'], 1, ',', '.') : '—'"
                         :satuan="$ringkasan['adg'] !== null ? 'g/ekor/hari' : null">
                <x-slot:footer>
                    <p class="text-xs text-gray-500">
                        Pertambahan total
                        <span class="font-semibold text-gray-700">
                            {{ $ringkasan['pertambahan_total'] !== null ? number_format($ringkasan['pertambahan_total'], 1, ',', '.') . ' g/ekor' : '—' }}
                        </span>
                        sejak sampling pertama.
                    </p>
                </x-slot:footer>
            </x-stat-card>

            {{-- FCR --}}
            <x-stat-card label="FCR Kumulatif" ikon="scale" warna="indigo"
                         :nilai="$ringkasan['fcr'] !== null ? number_format($ringkasan['fcr'], 2, ',', '.') : '—'"
                         :satuan="$ringkasan['fcr'] !== null ? 'kg pakan/kg berat' : null">
                <x-slot:footer>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($ringkasan['predikat_fcr'] !== null)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md font-medium border
                                {{ $ringkasan['predikat_fcr'] === 'Efisien' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                {{ $ringkasan['predikat_fcr'] }}
                            </span>
                            <span class="text-gray-500">Target &le; {{ number_format($ringkasan['fcr_target'], 2, ',', '.') }}</span>
                        @elseif ($ringkasan['fcr'] === null)
                            <span class="text-gray-500">Butuh minimal dua kali sampling untuk menghitung FCR.</span>
                        @else
                            <span class="text-gray-500">Fase produksi: konversi pakan diukur per kg telur, bukan per pertambahan berat.</span>
                        @endif
                    </div>
                </x-slot:footer>
            </x-stat-card>

            {{-- Konsumsi pakan --}}
            <x-stat-card label="Konsumsi Pakan" ikon="restaurant" warna="amber"
                         :nilai="$ringkasan['konsumsi_ekor_hari'] !== null ? number_format($ringkasan['konsumsi_ekor_hari'], 1, ',', '.') : '—'"
                         :satuan="$ringkasan['konsumsi_ekor_hari'] !== null ? 'g/ekor/hari' : null">
                <x-slot:footer>
                    <p class="text-xs text-gray-500">
                        Total terpakai
                        <span class="font-semibold text-gray-700">{{ number_format($ringkasan['pakan_kumulatif_kg'], 2, ',', '.') }} kg</span>
                        @if ($ringkasan['konsumsi_target'] !== null)
                            &bull; target {{ number_format($ringkasan['konsumsi_target'], 0, ',', '.') }} g/ekor/hari
                        @endif
                    </p>
                </x-slot:footer>
            </x-stat-card>

            {{-- Keseragaman --}}
            <x-stat-card label="Keseragaman Populasi" ikon="diversity_3" warna="teal"
                         :nilai="$ringkasan['keseragaman'] !== null ? number_format($ringkasan['keseragaman'], 1, ',', '.') : '—'"
                         :satuan="$ringkasan['keseragaman'] !== null ? '%' : null">
                <x-slot:footer>
                    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full {{ ($ringkasan['keseragaman'] ?? 0) >= $ringkasan['ambang_keseragaman_baik'] ? 'bg-emerald-500' : (($ringkasan['keseragaman'] ?? 0) >= $ringkasan['ambang_keseragaman_cukup'] ? 'bg-amber-500' : 'bg-red-500') }}"
                             style="width: {{ min(100, max(0, $ringkasan['keseragaman'] ?? 0)) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        @if ($ringkasan['predikat_keseragaman'] !== null)
                            {{ $ringkasan['predikat_keseragaman'] }}
                        @else
                            Isi simpangan baku berat saat sampling untuk menghitung keseragaman.
                        @endif
                    </p>
                </x-slot:footer>
            </x-stat-card>

            {{-- Deplesi --}}
            <x-stat-card label="Deplesi Tercatat" ikon="heart_broken" warna="red"
                         :nilai="number_format($ringkasan['deplesi_ekor'], 0, ',', '.')"
                         satuan="ekor">
                <x-slot:footer>
                    <p class="text-xs text-gray-500">
                        {{ number_format($ringkasan['deplesi_persen'], 1, ',', '.') }}% dari populasi awal
                        ({{ number_format($kelompokTerpilih->populasi_awal, 0, ',', '.') }} ekor)
                        &bull; daya hidup kini
                        <span class="font-semibold text-gray-700">{{ number_format($kelompokTerpilih->daya_hidup, 1, ',', '.') }}%</span>
                    </p>
                </x-slot:footer>
            </x-stat-card>
        </div>

        {{-- Grafik --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">Kurva Pertumbuhan</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Berat rata-rata sampel dibandingkan target standar pada umur yang sama.</p>
                    </div>
                </div>
                @if ($adaSampling)
                    <div class="h-72" wire:ignore>
                        <canvas id="grafikBerat"></canvas>
                    </div>
                @else
                    <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                        <span class="material-symbols-outlined text-4xl text-gray-300">monitor_weight</span>
                        <p class="text-sm font-medium text-gray-900 mt-2">Belum ada data sampling</p>
                        <p class="text-xs text-gray-500 mt-1">Catat sampling pertama untuk membentuk kurva pertumbuhan.</p>
                    </div>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-gray-900 mb-4">Pakan &amp; FCR per Sampling</h3>
                @if ($adaSampling)
                    <div class="h-72" wire:ignore>
                        <canvas id="grafikPakanFcr"></canvas>
                    </div>
                @else
                    <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                        <span class="material-symbols-outlined text-4xl text-gray-300">restaurant</span>
                        <p class="text-sm font-medium text-gray-900 mt-2">Belum ada data pakan</p>
                        <p class="text-xs text-gray-500 mt-1">Konsumsi pakan dan FCR muncul setelah ada sampling.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat sampling --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-900">Riwayat Sampling</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $ringkasan['jumlah_sampling'] }} pencatatan &bull; umur dihitung otomatis dari tanggal masuk kelompok.
                    </p>
                </div>
                <button wire:click="openModal" type="button"
                        class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Catat Sampling
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                            <th class="p-4">Tanggal / Umur</th>
                            <th class="p-4 text-right">Sampel</th>
                            <th class="p-4 text-right">Berat Rata-rata</th>
                            <th class="p-4 text-right">ADG</th>
                            <th class="p-4 text-right">Pakan</th>
                            <th class="p-4 text-right">FCR</th>
                            <th class="p-4 text-center">Seragam</th>
                            <th class="p-4 text-center">Kondisi</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($analisis['baris_terbaru'] as $row)
                            <tr class="hover:bg-gray-50 transition-colors {{ $row['di_bawah_target'] ? 'bg-amber-50/40' : '' }}">
                                <td class="p-4">
                                    <p class="text-sm font-semibold text-gray-900">{{ $row['tanggal_label'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $row['umur_hari'] }} hari ({{ $row['umur_minggu'] }} mgg)
                                        @if ($row['delta_hari'] > 1)
                                            &bull; jeda {{ $row['delta_hari'] }} hari
                                        @endif
                                    </p>
                                    @if ($row['catatan'])
                                        <p class="text-xs text-gray-400 mt-1 italic">{{ $row['catatan'] }}</p>
                                    @endif
                                </td>
                                <td class="p-4 text-right text-sm text-gray-700">
                                    {{ number_format($row['jumlah_sampel'], 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-right">
                                    <p class="text-sm font-bold text-gray-900">{{ number_format($row['berat'], 1, ',', '.') }} g</p>
                                    @if ($row['target_berat'] !== null)
                                        <p class="text-xs {{ $row['di_bawah_target'] ? 'text-amber-600' : 'text-gray-500' }}">
                                            target {{ number_format($row['target_berat'], 0, ',', '.') }} g
                                            ({{ $row['deviasi'] > 0 ? '+' : '' }}{{ $row['deviasi'] }}%)
                                        </p>
                                    @endif
                                    @if ($row['berat_std_dev'] !== null)
                                        <p class="text-xs text-gray-400">&plusmn; {{ number_format($row['berat_std_dev'], 1, ',', '.') }} g</p>
                                    @endif
                                </td>
                                <td class="p-4 text-right text-sm text-gray-700">
                                    {{ $row['adg'] !== null ? number_format($row['adg'], 1, ',', '.') . ' g' : '—' }}
                                </td>
                                <td class="p-4 text-right">
                                    <p class="text-sm text-gray-900">{{ number_format($row['pakan_kg'], 2, ',', '.') }} kg</p>
                                    <p class="text-xs text-gray-500">{{ number_format($row['konsumsi_ekor_hari'], 1, ',', '.') }} g/ekor/hari</p>
                                </td>
                                <td class="p-4 text-right text-sm font-semibold text-gray-900">
                                    {{ $row['fcr'] !== null ? number_format($row['fcr'], 2, ',', '.') : '—' }}
                                </td>
                                <td class="p-4 text-center">
                                    @if ($row['keseragaman'] !== null)
                                        <span class="text-sm font-medium {{ $row['keseragaman'] >= $ringkasan['ambang_keseragaman_baik'] ? 'text-emerald-700' : ($row['keseragaman'] >= $ringkasan['ambang_keseragaman_cukup'] ? 'text-amber-700' : 'text-red-700') }}">
                                            {{ number_format($row['keseragaman'], 1, ',', '.') }}%
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium border
                                        {{ $row['kondisi_kesehatan'] === 'Sehat'
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : ($row['kondisi_kesehatan'] === 'Perlu Perhatian'
                                                ? 'bg-amber-50 text-amber-700 border-amber-200'
                                                : 'bg-red-50 text-red-700 border-red-200') }}">
                                        {{ $row['kondisi_kesehatan'] }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="edit({{ $row['id'] }})" type="button"
                                                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                                title="Edit sampling">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </button>
                                        <button type="button" @click="konfirmasiHapus({{ $row['id'] }})"
                                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                                title="Hapus sampling">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">monitor_weight</span>
                                        <p class="font-medium text-gray-900">Belum ada riwayat sampling</p>
                                        <p class="text-sm mt-1">Catat hasil penimbangan sampel secara rutin, minimal sekali seminggu.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($adaSampling)
                        <tfoot class="bg-gray-50 border-t border-gray-200 text-sm">
                            <tr>
                                <td colspan="2" class="p-4 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Total &amp; kumulatif
                                </td>
                                <td class="p-4 text-right text-xs text-gray-600">
                                    bertambah {{ $ringkasan['pertambahan_total'] !== null ? number_format($ringkasan['pertambahan_total'], 1, ',', '.') . ' g' : '—' }}/ekor
                                </td>
                                <td class="p-4 text-right text-xs text-gray-600">
                                    rata-rata {{ $ringkasan['adg'] !== null ? number_format($ringkasan['adg'], 1, ',', '.') . ' g' : '—' }}/hari
                                </td>
                                <td class="p-4 text-right text-xs font-semibold text-gray-900">
                                    {{ number_format($ringkasan['pakan_kumulatif_kg'], 2, ',', '.') }} kg
                                </td>
                                <td class="p-4 text-right text-xs font-semibold text-gray-900">
                                    {{ $ringkasan['fcr'] !== null ? number_format($ringkasan['fcr'], 2, ',', '.') : '—' }}
                                </td>
                                <td class="p-4 text-center text-xs text-gray-600">
                                    {{ $ringkasan['keseragaman'] !== null ? number_format($ringkasan['keseragaman'], 1, ',', '.') . '%' : '—' }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Modal catat/koreksi sampling --}}
        <x-app-modal :judul="$form->isEdit() ? 'Koreksi Sampling Pertumbuhan' : 'Catat Sampling Pertumbuhan'"
                     :subjudul="$kelompokTerpilih->nama_kandang.' • '.$kelompokTerpilih->jenis_bebek">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Tanggal Sampling</label>
                            <input wire:model.live="form.tanggal" type="date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            @error('form.tanggal') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Umur Saat Sampling</label>
                            <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-700">
                                {{ $form->umurHari() }} hari
                                <span class="text-gray-500">({{ intdiv($form->umurHari(), 7) }} minggu)</span>
                            </div>
                            <span class="text-xs text-gray-500">Dihitung otomatis dari tanggal masuk kelompok.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Jumlah Sampel (ekor)</label>
                            <input wire:model="form.jumlah_sampel" type="number" min="1" placeholder="Misal: 30"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <span class="text-xs text-gray-500">5-10% populasi per kandang.</span>
                            @error('form.jumlah_sampel') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Berat Rata-rata (gram)</label>
                            <input wire:model="form.berat_rata_rata" type="number" step="0.1" min="1" placeholder="Misal: 1180"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            @error('form.berat_rata_rata') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Simpangan Baku (gram)</label>
                            <input wire:model="form.berat_std_dev" type="number" step="0.1" min="0" placeholder="Opsional"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <span class="text-xs text-gray-500">Dasar hitung keseragaman.</span>
                            @error('form.berat_std_dev') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Konsumsi Pakan (kg)</label>
                            <input wire:model="form.pakan_kg" type="number" step="0.01" min="0" placeholder="Misal: 84.5"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <span class="text-xs text-gray-500">Total pakan sejak pencatatan sebelumnya.</span>
                            @error('form.pakan_kg') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-semibold text-gray-700">Kondisi Kesehatan</label>
                            <select wire:model="form.kondisi_kesehatan"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                                @foreach ($opsi_kondisi as $kondisi)
                                    <option value="{{ $kondisi }}">{{ $kondisi }}</option>
                                @endforeach
                            </select>
                            @error('form.kondisi_kesehatan') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Catatan</label>
                        <textarea wire:model="form.catatan" rows="3" placeholder="Misal: vaksin ND hari ke-3, sampel diambil dari 3 titik kandang."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none"></textarea>
                        @error('form.catatan') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
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
    @endif

    {{-- Data grafik dikirim lewat atribut data agar canvas tidak ikut ter-morph Livewire. --}}
    <div id="dataGrafikPertumbuhan" class="hidden" data-grafik="{{ json_encode($grafik) }}"></div>

    @script
        <script>
            const gambarGrafikPertumbuhan = () => {
                const data = Grafik.bacaDataGrafik('dataGrafikPertumbuhan');

                if (!data || !Array.isArray(data.label) || data.label.length === 0) {
                    return;
                }

                Grafik.gambarGrafik('grafikBerat', {
                    type: 'line',
                    data: {
                        labels: data.label,
                        datasets: [
                            {
                                label: 'Berat Rata-rata (g)',
                                data: data.berat,
                                borderColor: '#059669',
                                backgroundColor: 'rgba(5, 150, 105, 0.12)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: '#059669'
                            },
                            {
                                label: 'Target Standar (g)',
                                data: data.target,
                                borderColor: '#94a3b8',
                                borderDash: [6, 4],
                                fill: false,
                                tension: 0.3,
                                pointRadius: 0
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
                            y: { beginAtZero: false, ticks: { callback: (nilai) => nilai + ' g' } }
                        }
                    }
                });

                Grafik.gambarGrafik('grafikPakanFcr', {
                    type: 'bar',
                    data: {
                        labels: data.label,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Pakan (g/ekor/hari)',
                                data: data.konsumsi,
                                backgroundColor: '#f59e0b',
                                borderRadius: 4,
                                yAxisID: 'y'
                            },
                            {
                                type: 'line',
                                label: 'FCR Kumulatif',
                                data: data.fcr,
                                borderColor: '#6366f1',
                                backgroundColor: '#6366f1',
                                tension: 0.3,
                                pointRadius: 3,
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
                            y: { beginAtZero: true, position: 'left', ticks: { callback: (nilai) => nilai + ' g' } },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });
            };

            gambarGrafikPertumbuhan();
            Grafik.pantauGrafik('pertumbuhan-chart-refresh', gambarGrafikPertumbuhan);
            Grafik.pasangPeringatanSwal();
        </script>
    @endscript
</div>
