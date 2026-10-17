<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TernakBebek OS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gradient-to-b from-emerald-50 to-gray-100">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
            <div class="w-full sm:max-w-sm">
                {{-- Identitas aplikasi --}}
                <a href="/" wire:navigate class="flex flex-col items-center text-center">
                    <span class="w-12 h-12 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-sm">
                        <span class="material-symbols-outlined text-2xl">cruelty_free</span>
                    </span>
                    <span class="mt-3 text-base font-bold tracking-tight text-gray-900">TernakBebek OS</span>
                    <span class="text-sm text-gray-500">Kandang Sumber Rezeki</span>
                </a>

                {{-- Kartu formulir --}}
                <div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm p-6 sm:p-7">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
