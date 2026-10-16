<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TernakCihuy') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900"
      x-data="{ sidebarOpen: window.innerWidth >= 1024 }" 
      @resize.window="sidebarOpen = window.innerWidth >= 1024">

    <div x-show="sidebarOpen" 
         @click="sidebarOpen = false" 
         class="fixed inset-0 z-40 bg-gray-900/40 backdrop-blur-sm lg:hidden" 
         x-transition.opacity 
         style="display: none;"></div>

    <livewire:layout.sidebar />

    <div class="min-h-screen flex flex-col transition-all duration-300 ease-in-out" 
         :class="sidebarOpen ? 'lg:pl-64' : 'pl-0'">
        
        <header class="sticky top-0 z-30 h-16 bg-white border-b border-gray-200 px-4 lg:px-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" 
                        class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 border border-gray-200 transition-colors flex items-center justify-center" 
                        type="button">
                    <span class="material-symbols-outlined text-xl block" x-text="sidebarOpen ? 'menu_open' : 'menu'">menu</span>
                </button>

                <div class="hidden sm:flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-symbols-outlined text-lg">calendar_today</span>
                    <span>{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>
            </div>

            {{-- <div class="flex items-center gap-3">
                <button @click="$dispatch('open-modal-catat-telur')" 
                        class="flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors">
                    <span class="material-symbols-outlined text-lg">add_circle</span>
                    <span>Catat Telur</span>
                </button>
            </div> --}}
        </header>

        <main class="flex-1 p-4 lg:p-6">
            {{ $slot }}
        </main>
    </div>

    {{-- <livewire:catat-telur-modal /> --}}
</body>
</html>