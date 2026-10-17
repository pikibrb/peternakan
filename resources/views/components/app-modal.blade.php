@props([
    'judul' => '',
    'subjudul' => null,
    'lebar' => 'max-w-2xl',
])

{{-- Cangkang modal bersama untuk semua halaman CRUD.
     Kontrol buka/tutup memakai properti Livewire $isModalOpen dan method tutupModal(). --}}
<div x-show="$wire.isModalOpen"
     style="display: none;"
     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 overflow-y-auto">

    <div @click.outside="$wire.tutupModal()"
         x-show="$wire.isModalOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-xl shadow-xl w-full {{ $lebar }} max-h-[90vh] overflow-y-auto flex flex-col my-auto">

        <div class="flex items-center justify-between gap-3 p-5 border-b border-gray-200 bg-gray-50/50 sticky top-0 z-10">
            <div class="min-w-0">
                <h3 class="text-lg font-bold text-gray-900">{{ $judul }}</h3>
                @if ($subjudul)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $subjudul }}</p>
                @endif
            </div>
            <button wire:click="tutupModal" type="button"
                    class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="p-6 flex flex-col gap-4">
            {{ $slot }}
        </div>

        <div class="p-5 border-t border-gray-200 bg-gray-50/50 flex justify-end gap-3 sticky bottom-0 z-10">
            {{ $aksi }}
        </div>
    </div>
</div>
