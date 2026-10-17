<?php

use App\Livewire\Forms\KelompokBebekForm;
use App\Models\KelompokBebek;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public KelompokBebekForm $form;

    public $search = '';

    public $statusFilter = 'Aktif';

    public $isModalOpen = false;

    public function mount(): void
    {
        $this->form->tanggal_masuk = today()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->resetValidation();
        $this->form->reset();
        $this->form->tanggal_masuk = today()->toDateString();
        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $this->form->reset();
        $this->form->setRekaman(KelompokBebek::findOrFail($id));
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
            $this->form->update(KelompokBebek::findOrFail($this->form->id));
        } else {
            $this->form->store();
        }

        $this->isModalOpen = false;
        $this->form->reset();
        $this->resetPage();

        $this->pesanSukses(
            $dikoreksi
                ? 'Data kandang dikoreksi dan populasi aktif dihitung ulang dari deplesi tercatat.'
                : 'Kelompok (flock) baru berhasil didaftarkan.'
        );
    }

    /**
     * Ubah status kandang. Menandai kandang Kosong tidak menghapus riwayat:
     * kandang hanya keluar dari perhitungan populasi aktif.
     */
    public function ubahStatus(int $id): void
    {
        $kelompok = KelompokBebek::findOrFail($id);

        $kelompok->status = $kelompok->status === 'Aktif'
            ? KelompokBebekForm::STATUS[1]
            : KelompokBebekForm::STATUS[0];

        $kelompok->save();

        $this->pesanSukses('Kandang '.$kelompok->nama_kandang.' ditandai '.$kelompok->status.'.');
    }

    /**
     * Kandang hanya boleh dihapus bila belum menyimpan riwayat. Catatan
     * produksi/sampling/deplesi adalah bukti operasional peternakan sehingga
     * kandang yang sudah berjalan cukup ditandai Kosong.
     */
    public function hapus(int $id): void
    {
        $kelompok = KelompokBebek::findOrFail($id);
        $riwayat = $this->hitungRiwayat($kelompok);

        if ($riwayat['total'] > 0) {
            $this->dispatch('swal:alert', [
                'title' => 'Kandang tidak bisa dihapus',
                'text' => 'Masih tersimpan '.number_format($riwayat['total'], 0, ',', '.')
                    .' catatan (produksi '.number_format($riwayat['produksi'], 0, ',', '.')
                    .', sampling '.number_format($riwayat['sampling'], 0, ',', '.')
                    .', deplesi '.number_format($riwayat['deplesi'], 0, ',', '.').').'
                    .' Tandai kandang sebagai Kosong agar riwayat tetap utuh.',
                'icon' => 'warning',
            ]);

            return;
        }

        $kelompok->delete();
        $this->resetPage();

        $this->pesanSukses('Kandang '.$kelompok->nama_kandang.' dihapus.');
    }

    private function pesanSukses(string $teks): void
    {
        $this->dispatch('swal:alert', [
            'title' => 'Berhasil',
            'text' => $teks,
            'icon' => 'success',
        ]);
    }

    /**
     * Jumlah riwayat sebuah kandang dalam satu query (withCount), bukan tiga
     * query terpisah dan bukan query per baris tabel.
     *
     * @return array{total: int, produksi: int, sampling: int, deplesi: int}
     */
    private function hitungRiwayat(KelompokBebek $kelompok): array
    {
        $terhitung = KelompokBebek::query()
            ->whereKey($kelompok->id)
            ->withCount(['produksiTelur', 'monitoringPertumbuhan', 'penguranganPopulasi'])
            ->first();

        $produksi = (int) $terhitung->produksi_telur_count;
        $sampling = (int) $terhitung->monitoring_pertumbuhan_count;
        $deplesi = (int) $terhitung->pengurangan_populasi_count;

        return [
            'total' => $produksi + $sampling + $deplesi,
            'produksi' => $produksi,
            'sampling' => $sampling,
            'deplesi' => $deplesi,
        ];
    }

    public function with(): array
    {
        $query = KelompokBebek::query()
            // Satu query tambahan untuk semua baris (menghindari query per baris).
            ->withCount(['produksiTelur', 'monitoringPertumbuhan', 'penguranganPopulasi'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama_kandang', 'like', '%'.$this->search.'%')
                        ->orWhere('jenis_bebek', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== 'Semua', function ($q) {
                $q->where('status', $this->statusFilter);
            });

        return [
            'kelompok_bebek' => $query->orderBy('created_at', 'desc')->paginate(10),
            'stats' => KelompokBebek::statistikAktif(),
            'opsi_fase' => KelompokBebekForm::FASE,
            'opsi_status' => KelompokBebekForm::STATUS,
            // Hanya dihitung saat modal terbuka.
            'deplesi_tercatat' => $this->isModalOpen && $this->form->isEdit()
                ? $this->form->deplesiTercatat((int) $this->form->id)
                : 0,
        ];
    }
}; ?>

<div class="space-y-6"
     x-data="{
        konfirmasiSimpan() {
            Grafik.konfirmasi({
                judul: '{{ $form->isEdit() ? 'Simpan koreksi data kandang?' : 'Daftarkan kelompok baru?' }}',
                teks: 'Populasi aktif akan dihitung ulang dari populasi awal dikurangi seluruh deplesi tercatat.',
                tombol: 'Ya, simpan',
                aksi: () => $wire.save(),
            });
        },
        konfirmasiHapus(id, nama) {
            Grafik.konfirmasi({
                judul: 'Hapus kandang ' + nama + '?',
                teks: 'Kandang tanpa riwayat boleh dihapus.',
                ikon: 'warning',
                warna: '#dc2626',
                tombol: 'Ya, hapus',
                aksi: () => $wire.hapus(id),
            });
        },
        konfirmasiStatus(id, aktif) {
            Grafik.konfirmasi({
                judul: aktif ? 'Aktifkan kandang kembali?' : 'Tandai kandang Kosong?',
                teks: aktif
                    ? 'Kandang akan dihitung lagi dalam total populasi aktif.'
                    : 'Riwayat produksi, sampling, dan deplesi tetap tersimpan; kandang hanya keluar dari populasi aktif.',
                tombol: aktif ? 'Ya, aktifkan' : 'Ya, tandai kosong',
                aksi: () => $wire.ubahStatus(id),
            });
        }
     }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Populasi Bebek</h1>
            <p class="text-sm text-gray-500 mt-1">
                Manajemen kandang/flock: identitas, fase pemeliharaan, dan status populasi. Populasi aktif mengikuti
                catatan deplesi.
            </p>
        </div>
        <button wire:click="openModal" type="button"
                class="flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Kandang
        </button>
    </div>

    {{-- Statistik --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-stat-card label="Total Populasi Aktif" :nilai="number_format($stats['total_populasi'], 0, ',', '.')"
                     satuan="ekor" ikon="cruelty_free" warna="blue" />

        <x-stat-card label="Kandang Aktif" :nilai="$stats['kelompok_aktif']" satuan="flock"
                     ikon="warehouse" warna="emerald" />

        <x-stat-card label="Tingkat Deplesi (Kematian)" :nilai="$stats['tingkat_deplesi']" satuan="%"
                     ikon="trending_down" warna="red"
                     bawah="Deplesi kumulatif seluruh kandang aktif terhadap populasi awal." />
    </div>

    {{-- Filter & tabel --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between gap-4 bg-gray-50/50">
            <div class="relative w-full sm:w-72">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama kandang atau jenis..."
                       class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
            </div>
            <select wire:model.live="statusFilter"
                    class="w-full sm:w-48 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white cursor-pointer">
                <option value="Aktif">Status: Aktif</option>
                <option value="Kosong">Status: Kosong</option>
                <option value="Semua">Semua Status</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                        <th class="p-4">Kandang / Jenis</th>
                        <th class="p-4 text-center">Fase</th>
                        <th class="p-4 text-center">Umur</th>
                        <th class="p-4 text-right">Populasi</th>
                        <th class="p-4 text-center">Daya Hidup</th>
                        <th class="p-4 text-center">Riwayat</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($kelompok_bebek as $kelompok)
                        @php
                            $riwayat = $kelompok->produksi_telur_count + $kelompok->monitoring_pertumbuhan_count + $kelompok->pengurangan_populasi_count;
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <p class="font-bold text-sm text-gray-900">{{ $kelompok->nama_kandang }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $kelompok->jenis_bebek }} &bull; Masuk: {{ $kelompok->tanggal_masuk?->format('d/m/Y') ?? '-' }}
                                </p>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium
                                    {{ $kelompok->fase === 'Layer' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                    {{ $kelompok->fase }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $kelompok->umur_minggu }} <span class="text-xs font-normal text-gray-500">mgg</span>
                                </p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-bold text-gray-900">{{ number_format($kelompok->populasi_aktif, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">Awal: {{ number_format($kelompok->populasi_awal, 0, ',', '.') }}</p>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full {{ $kelompok->daya_hidup >= 95 ? 'bg-emerald-500' : ($kelompok->daya_hidup >= 90 ? 'bg-amber-500' : 'bg-red-500') }}"
                                             style="width: {{ min(100, $kelompok->daya_hidup) }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700">{{ $kelompok->daya_hidup }}%</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                @if ($riwayat > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700"
                                          title="Produksi: {{ $kelompok->produksi_telur_count }} &bull; Sampling: {{ $kelompok->monitoring_pertumbuhan_count }} &bull; Deplesi: {{ $kelompok->pengurangan_populasi_count }}">
                                        <span class="material-symbols-outlined text-[16px]">history</span>
                                        {{ number_format($riwayat, 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if ($kelompok->status === 'Aktif')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Kosong
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('pengurangan-populasi', ['kelompok' => $kelompok->id]) }}" wire:navigate
                                       class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-md transition-colors"
                                       title="Catat deplesi (kematian/afkir)">
                                        <span class="material-symbols-outlined text-[20px]">heart_broken</span>
                                    </a>
                                    <button wire:click="edit({{ $kelompok->id }})" type="button"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                            title="Koreksi data kandang">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button type="button" @click="konfirmasiStatus({{ $kelompok->id }}, {{ $kelompok->status === 'Aktif' ? 'false' : 'true' }})"
                                            class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                                            title="{{ $kelompok->status === 'Aktif' ? 'Tandai kandang Kosong' : 'Aktifkan kembali kandang' }}">
                                        <span class="material-symbols-outlined text-[20px]">{{ $kelompok->status === 'Aktif' ? 'block' : 'play_arrow' }}</span>
                                    </button>
                                    @if ($riwayat === 0)
                                        <button type="button" @click="konfirmasiHapus({{ $kelompok->id }}, @js($kelompok->nama_kandang))"
                                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                                title="Hapus kandang (belum ada riwayat)">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    @else
                                        <span class="p-1.5 text-gray-300 cursor-not-allowed" title="Kandang dengan riwayat tidak bisa dihapus — tandai Kosong sebagai gantinya">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">cruelty_free</span>
                                    <p class="font-medium text-gray-900">Belum ada data kelompok bebek</p>
                                    <p class="text-sm mt-1">Tambahkan kandang/flock pertama Anda.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($kelompok_bebek->hasPages())
            <div class="p-4 border-t border-gray-200 bg-white">
                {{ $kelompok_bebek->links() }}
            </div>
        @endif
    </div>

    {{-- Modal tambah/koreksi kandang --}}
    <x-app-modal lebar="max-w-lg"
                 :judul="$form->isEdit() ? 'Koreksi Data Kandang' : 'Registrasi Kelompok Baru'"
                 :subjudul="$form->isEdit()
                     ? 'Populasi aktif dihitung otomatis dari populasi awal dikurangi deplesi tercatat.'
                     : 'Data identitas flock menentukan dasar semua perhitungan produksi.'">

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-gray-700">Nama Kandang / ID Flock</label>
            <input wire:model="form.nama_kandang" type="text" placeholder="Misal: Kandang A1"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            @error('form.nama_kandang') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-gray-700">Jenis / Strain Bebek</label>
            <input wire:model="form.jenis_bebek" type="text" placeholder="Misal: Petelur Mojosari"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            @error('form.jenis_bebek') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold text-gray-700">Fase Pemeliharaan</label>
                <select wire:model="form.fase"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                    @foreach ($opsi_fase as $fase)
                        <option value="{{ $fase }}">{{ $fase }}</option>
                    @endforeach
                </select>
                @error('form.fase') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold text-gray-700">Status Kandang</label>
                <select wire:model="form.status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                    @foreach ($opsi_status as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-gray-500">Kosong = kandang sudah tidak diisi, riwayat tetap tersimpan.</span>
                @error('form.status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold text-gray-700">Tanggal Masuk (Check-in)</label>
                <input wire:model="form.tanggal_masuk" type="date" max="{{ today()->toDateString() }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                @error('form.tanggal_masuk') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold text-gray-700">Umur Awal (Minggu)</label>
                <input wire:model="form.umur_awal_minggu" type="number" min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <span class="text-xs text-gray-500">0 untuk anakan (DOD).</span>
                @error('form.umur_awal_minggu') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-gray-700">Populasi Awal (Ekor)</label>
            <input wire:model="form.populasi_awal" type="number" min="1" placeholder="Jumlah saat check-in"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            @if ($form->isEdit())
                <span class="text-xs text-gray-500">
                    Deplesi tercatat {{ number_format($deplesi_tercatat, 0, ',', '.') }} ekor &bull;
                    populasi aktif akan menjadi
                    {{ number_format(max(0, (int) $form->populasi_awal - $deplesi_tercatat), 0, ',', '.') }} ekor
                    (menambah populasi awal = memasukkan bebek baru).
                </span>
            @else
                <span class="text-xs text-gray-500">Populasi aktif dimulai sama dengan populasi awal.</span>
            @endif
            @error('form.populasi_awal') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <x-slot:aksi>
            <button wire:click="tutupModal" type="button"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors">
                Batal
            </button>
            <button @click="konfirmasiSimpan()" type="button"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors">
                {{ $form->isEdit() ? 'Simpan Koreksi' : 'Simpan Data' }}
            </button>
        </x-slot:aksi>
    </x-app-modal>

    @script
        <script>
            Grafik.pasangPeringatanSwal();
        </script>
    @endscript
</div>
