<?php

use App\Models\KelompokBebek;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = 'Aktif';

    // Properti Modal Form
    public $isModalOpen = false;
    public $nama_kandang = '';
    public $jenis_bebek = '';
    public $fase = 'Layer';
    public $umur_awal_minggu = 0;
    public $tanggal_masuk = '';
    public $populasi_awal = '';

    public function mount()
    {
        $this->tanggal_masuk = date('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->reset(['nama_kandang', 'jenis_bebek', 'fase', 'populasi_awal']);
        $this->tanggal_masuk = date('Y-m-d');
        $this->fase = 'Layer';
        $this->umur_awal_minggu = 0;
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function save()
    {
        $this->validate([
            'nama_kandang' => 'required|string|max:255',
            'jenis_bebek' => 'required|string|max:255',
            'fase' => 'required|in:Starter,Grower,Layer,Afkir',
            'tanggal_masuk' => 'required|date',
            'umur_awal_minggu' => 'required|integer|min:0',
            'populasi_awal' => 'required|integer|min:1',
        ]);

        KelompokBebek::create([
            'nama_kandang' => $this->nama_kandang,
            'jenis_bebek' => $this->jenis_bebek,
            'fase' => $this->fase,
            'tanggal_masuk' => $this->tanggal_masuk,
            'umur_awal_minggu' => $this->umur_awal_minggu,
            'populasi_awal' => $this->populasi_awal,
            'populasi_aktif' => $this->populasi_awal,
            'status' => 'Aktif',
        ]);

        $this->closeModal();

        $this->dispatch('swal:alert', [
            'title' => 'Berhasil!',
            'text' => 'Data kelompok (flock) baru berhasil ditambahkan.',
            'icon' => 'success',
        ]);
    }

    public function with(): array
    {
        $query = KelompokBebek::query()
            ->when($this->search, function ($q) {
                $q->where('nama_kandang', 'like', '%' . $this->search . '%')->orWhere('jenis_bebek', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter !== 'Semua', function ($q) {
                $q->where('status', $this->statusFilter);
            });

        $totalPopulasi = KelompokBebek::where('status', 'Aktif')->sum('populasi_aktif');
        $kelompokAktif = KelompokBebek::where('status', 'Aktif')->count();
        $populasiAwal = KelompokBebek::where('status', 'Aktif')->sum('populasi_awal');

        $tingkatDeplesi = 0;
        if ($populasiAwal > 0) {
            $tingkatDeplesi = round((($populasiAwal - $totalPopulasi) / $populasiAwal) * 100, 1);
        }

        return [
            'kelompok_bebek' => $query->orderBy('created_at', 'desc')->paginate(10),
            'stats' => [
                'total_populasi' => $totalPopulasi,
                'kelompok_aktif' => $kelompokAktif,
                'tingkat_deplesi' => $tingkatDeplesi,
            ],
        ];
    }
}; ?>

<div class="space-y-6" x-data="bebekManager">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Populasi Bebek</h1>
            <p class="text-sm text-gray-500 mt-1">Manajemen kelompok bebek (flock), umur, dan status populasi kandang.
            </p>
        </div>
        <button wire:click="openModal"
            class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Kelompok
        </button>
    </div>

    <!-- Statistik Cards (Tetap Sama) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">cruelty_free</span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Total Populasi Aktif</p>
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_populasi'], 0, ',', '.') }}
                    <span class="text-sm font-normal text-gray-500">ekor</span></h3>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
            <div
                class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">warehouse</span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Kandang/Kelompok Aktif</p>
                <h3 class="text-2xl font-bold text-gray-900">{{ $stats['kelompok_aktif'] }} <span
                        class="text-sm font-normal text-gray-500">flock</span></h3>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">trending_down</span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Tingkat Deplesi (Kematian)</p>
                <h3 class="text-2xl font-bold text-gray-900">{{ $stats['tingkat_deplesi'] }}%</h3>
            </div>
        </div>
    </div>

    <!-- Filter & Table (Tetap Sama) -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between gap-4 bg-gray-50/50">
            <div class="relative w-full sm:w-72">
                <span
                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Cari nama kandang atau jenis..."
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
                    <tr
                        class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider font-semibold text-gray-500">
                        <th class="p-4">Kandang / Jenis</th>
                        <th class="p-4 text-center">Fase</th>
                        <th class="p-4 text-center">Umur</th>
                        <th class="p-4 text-right">Populasi</th>
                        <th class="p-4 text-center">Daya Hidup</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($kelompok_bebek as $kelompok)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <p class="font-bold text-sm text-gray-900">{{ $kelompok->nama_kandang }}</p>
                                <p class="text-xs text-gray-500">{{ $kelompok->jenis_bebek }} • Masuk:
                                    {{ \Carbon\Carbon::parse($kelompok->tanggal_masuk)->format('d/m/Y') }}</p>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium 
                                    {{ $kelompok->fase === 'Layer' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                    {{ $kelompok->fase }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <p class="text-sm font-semibold text-gray-900">{{ $kelompok->umur_minggu }} <span
                                        class="text-xs font-normal text-gray-500">mgg</span></p>
                            </td>
                            <td class="p-4 text-right">
                                <p class="text-sm font-bold text-gray-900">
                                    {{ number_format($kelompok->populasi_aktif, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">Awal:
                                    {{ number_format($kelompok->populasi_awal, 0, ',', '.') }}</p>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full {{ $kelompok->daya_hidup >= 95 ? 'bg-emerald-500' : ($kelompok->daya_hidup >= 90 ? 'bg-amber-500' : 'bg-red-500') }}"
                                            style="width: {{ $kelompok->daya_hidup }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700">{{ $kelompok->daya_hidup }}%</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                @if ($kelompok->status === 'Aktif')
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Kosong
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-md transition-colors"
                                        title="Catat Deplesi (Kematian/Afkir)">
                                        <span class="material-symbols-outlined text-[20px]">heart_broken</span>
                                    </button>
                                    <button
                                        class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                        title="Edit Data">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span
                                        class="material-symbols-outlined text-4xl text-gray-300 mb-2">cruelty_free</span>
                                    <p class="font-medium text-gray-900">Belum ada data kelompok bebek</p>
                                    <p class="text-sm mt-1">Tambahkan data kandang/flock pertama Anda.</p>
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

    <!-- Modal Form -->
   <div x-show="$wire.isModalOpen" 
         style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 overflow-y-auto">
        
        <div @click.outside="$wire.closeModal()"
             x-show="$wire.isModalOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto flex flex-col my-auto">
            
            <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-gray-50/50 sticky top-0 z-10">
                <h3 class="text-lg font-bold text-gray-900">Registrasi Kelompok Baru</h3>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="p-6 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Nama Kandang / ID Flock</label>
                    <input wire:model="nama_kandang" type="text" placeholder="Misal: Kandang A1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    @error('nama_kandang') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Jenis / Strain Bebek</label>
                    <input wire:model="jenis_bebek" type="text" placeholder="Misal: Petelur Mojosari" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    @error('jenis_bebek') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Fase Pemeliharaan</label>
                        <select wire:model="fase" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="Starter">Starter (Anakan)</option>
                            <option value="Grower">Grower (Pertumbuhan)</option>
                            <option value="Layer">Layer (Bertelur)</option>
                            <option value="Afkir">Afkir</option>
                        </select>
                        @error('fase') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Tanggal Masuk</label>
                        <input wire:model="tanggal_masuk" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('tanggal_masuk') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Umur Awal (Minggu)</label>
                        <input wire:model="umur_awal_minggu" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <span class="text-xs text-gray-500">Ketik 0 jika anakan (DOD).</span>
                        @error('umur_awal_minggu') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Populasi Awal (Ekor)</label>
                        <input wire:model="populasi_awal" type="number" placeholder="Jumlah saat check-in" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @error('populasi_awal') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="p-5 border-t border-gray-200 bg-gray-50/50 flex justify-end gap-3 sticky bottom-0 z-10">
                <button wire:click="closeModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors">
                    Batal
                </button>
                <button @click="confirmSave" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors">
                    Simpan Data
                </button>
            </div>
        </div>
    </div>

    @script
        <script>
            // Listener Global untuk Event SweetAlert dari PHP
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('swal:alert', (data) => {
                    Swal.fire({
                        title: data[0].title,
                        text: data[0].text,
                        icon: data[0].icon,
                        confirmButtonColor: '#059669'
                    });
                });
            });

            // Alpine JS Component untuk Konfirmasi Form
            Alpine.data('bebekManager', () => ({
                confirmSave() {
                    Swal.fire({
                        title: 'Konfirmasi Data',
                        text: "Pastikan data populasi dan fase awal sudah benar. Apakah Anda yakin?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#059669', // Warna Emerald 600
                        cancelButtonColor: '#ef4444', // Warna Red 500
                        confirmButtonText: 'Ya, Simpan!',
                        cancelButtonText: 'Periksa Kembali'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $wire.save();
                        }
                    });
                }
            }));
        </script>
    @endscript
</div>
