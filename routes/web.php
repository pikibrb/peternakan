<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('data-bebek', 'data-bebek.index')
    ->middleware(['auth', 'verified'])
    ->name('data-bebek');

Volt::route('monitoring-pertumbuhan', 'monitoring-pertumbuhan.index')
    ->middleware(['auth', 'verified'])
    ->name('monitoring-pertumbuhan');

Volt::route('produksi-telur', 'produksi-telur.index')
    ->middleware(['auth', 'verified'])
    ->name('produksi-telur');

Volt::route('pengurangan-populasi', 'pengurangan-populasi.index')
    ->middleware(['auth', 'verified'])
    ->name('pengurangan-populasi');

Volt::route('penjualan', 'penjualan.index')
    ->middleware(['auth', 'verified'])
    ->name('penjualan');

Volt::route('laporan', 'laporan.index')
    ->middleware(['auth', 'verified'])
    ->name('laporan');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
