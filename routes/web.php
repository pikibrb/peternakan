<?php
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;
use livewire\Volt\Volt;



Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('data-bebek', 'data-bebek.index')
    ->middleware(['auth', 'verified'])
    ->name('data-bebek');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
