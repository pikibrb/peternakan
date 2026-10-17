@props([
    'label' => '',
    'nilai' => '',
    'satuan' => null,
    'prefiks' => null,
    'ikon' => 'insights',
    'warna' => 'emerald',
    'bawah' => null,
])

@php
    $palet = [
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'teal' => 'bg-teal-50 text-teal-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red' => 'bg-red-50 text-red-600',
    ][$warna] ?? 'bg-gray-50 text-gray-600';
@endphp

<div {{ $attributes->merge(['class' => 'bg-white p-5 rounded-xl border border-gray-200 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                @if ($prefiks)
                    <span class="text-sm font-normal text-gray-500">{{ $prefiks }}</span>
                @endif
                {{ $nilai }}
                @if ($satuan)
                    <span class="text-sm font-normal text-gray-500">{{ $satuan }}</span>
                @endif
            </p>
        </div>
        <div class="p-2.5 {{ $palet }} rounded-lg shrink-0">
            <span class="material-symbols-outlined">{{ $ikon }}</span>
        </div>
    </div>

    @isset($footer)
        <div class="mt-3">{{ $footer }}</div>
    @endisset

    @if ($bawah)
        <p class="text-xs text-gray-500 mt-3">{{ $bawah }}</p>
    @endif
</div>
