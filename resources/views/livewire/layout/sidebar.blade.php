<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<aside class="fixed left-0 top-0 h-full w-64 bg-white border-r border-gray-200 z-50 flex flex-col justify-between transition-transform duration-300 ease-in-out"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <div class="flex flex-col">
        <!-- Header Sidebar -->
        <div class="p-4 border-b border-gray-200 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-600 flex items-center justify-center text-white shrink-0">
                    <span class="material-symbols-outlined text-xl">cruelty_free</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-sm text-gray-900 tracking-tight truncate">TernakBebek OS</span>
                    <span class="text-xs text-gray-500 truncate">Kandang Sumber Rezeki</span>
                </div>
            </div>
            <button @click="sidebarOpen = false" class="lg:hidden p-1 rounded-md text-gray-500 hover:bg-gray-100 transition-colors" type="button">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>
        
        <!-- Status User -->
        <div class="p-3 mx-3 my-3 rounded-xl bg-gray-50 border border-gray-200 flex items-center gap-3">
            <div class="relative shrink-0">
                <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">
                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                </div>
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
            </div>
            <div class="flex flex-col min-w-0 flex-1">
                <span class="text-sm font-semibold text-gray-800 truncate">{{ Auth::user()->name ?? '-' }}</span>
                <span class="text-xs text-emerald-600 flex items-center gap-1 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>Online
                </span>
            </div>
        </div>

        <!-- Menu Navigasi -->
        <nav class="px-3 space-y-1">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">dashboard</span>
                <span class="truncate">Dashboard</span>
            </a>
            
            <a href="{{ route('data-bebek') }}" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('data-bebek*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">cruelty_free</span>
                <span class="truncate">Data Bebek</span>
            </a>

            <a href="#" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('monitoring*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">monitoring</span>
                <span class="truncate">Monitoring Pertumbuhan</span>
            </a>

            <a href="#" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('produksi-telur*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">egg</span>
                <span class="truncate">Produksi Telur</span>
            </a>

            <a href="#" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('penjualan*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">shopping_basket</span>
                <span class="truncate">Penjualan</span>
            </a>

            <a href="#" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('laporan*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">description</span>
                <span class="truncate">Laporan & Riwayat</span>
            </a>

            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 px-3 py-2 transition-colors {{ request()->routeIs('profile') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} text-sm rounded-lg">
                <span class="material-symbols-outlined text-xl shrink-0">settings</span>
                <span class="truncate">Pengaturan</span>
            </a>
        </nav>
    </div>

    <!-- Tombol Bawah -->
    <div class="p-3 border-t border-gray-200 bg-white flex flex-col gap-2">
        <button @click="$dispatch('open-modal-catat-telur')" class="w-full flex items-center justify-center gap-2 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors" type="button">
            <span class="material-symbols-outlined text-xl">add</span>
            <span>Catat Telur</span>
        </button>

        <button wire:click="logout" class="w-full flex items-center justify-center gap-2 py-2 border border-gray-200 text-red-600 hover:bg-red-50 rounded-lg text-sm font-medium transition-colors" type="button">
            <span class="material-symbols-outlined text-xl">logout</span>
            <span>Keluar</span>
        </button>
    </div>
</aside>