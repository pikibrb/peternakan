<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use function Livewire\Volt\form;
use function Livewire\Volt\layout;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <div>
        <h1 class="text-xl font-bold tracking-tight text-gray-900">Masuk</h1>
        <p class="text-sm text-gray-500 mt-1">Kelola kandang, produksi telur, dan penjualan.</p>
    </div>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-6 space-y-5">
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
            <input wire:model="form.username" id="username" type="text" name="username" required autofocus autocomplete="username"
                   class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
            <x-input-error :messages="$errors->get('form.username')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Kata Sandi</label>
            <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <label for="remember" class="flex items-center gap-2">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                   class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500" />
            <span class="text-sm text-gray-600">Ingat saya</span>
        </label>

        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            <span class="material-symbols-outlined text-[18px]">login</span>
            Masuk
        </button>
    </form>
</div>
