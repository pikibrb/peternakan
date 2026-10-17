<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard Peternakan</h1>
            <p class="text-sm text-gray-500 mt-1">
                Ringkasan populasi, produksi telur, dan penjualan. Data dihitung saat halaman dibuka.
            </p>
        </div>
        <div class="flex flex-wrap gap-2 print:hidden">
            <a href="{{ route('produksi-telur') }}" wire:navigate
               class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[20px]">egg_alt</span>
                Catat Telur
            </a>
            <a href="{{ route('laporan') }}" wire:navigate
               class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[20px]">description</span>
                Laporan
            </a>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card label="Populasi Aktif" ikon="cruelty_free" warna="blue"
                     :nilai="number_format($populasi['total_populasi'], 0, ',', '.')" satuan="ekor"
                     :bawah="number_format($populasi['kelompok_aktif'], 0, ',', '.').' kandang aktif • deplesi '
                        .number_format($populasi['tingkat_deplesi'], 1, ',', '.').'% dari '
                        .number_format($populasi['populasi_awal'], 0, ',', '.').' ekor awal'" />

        <x-stat-card label="Produksi Hari Ini" ikon="egg" warna="emerald"
                     :nilai="number_format($produksiHariIni, 0, ',', '.')" satuan="butir"
                     :bawah="$produksiHariIni > 0
                        ? 'Hen-day '.number_format($henDayHariIni, 1, ',', '.').'% dari bebek pengeram hari ini'
                        : 'Belum ada pencatatan produksi hari ini'" />

        <x-stat-card label="Stok Telur Siap Jual" ikon="inventory_2" warna="amber"
                     :nilai="number_format($stokTelur, 0, ',', '.')" satuan="butir"
                     bawah="Telur layak jual hasil produksi dikurangi telur yang sudah tercatat terjual." />

        <x-stat-card label="Penjualan Bulan Ini" ikon="payments" warna="indigo"
                     :nilai="number_format($omzetBulanIni, 0, ',', '.')" prefiks="Rp"
                     :bawah="$piutang > 0
                        ? 'Piutang belum lunas Rp '.number_format($piutang, 0, ',', '.')
                        : 'Seluruh nota bulan ini sudah lunas'" />
    </div>

    {{-- Grafik --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900">Produksi Telur (7 Hari Terakhir)</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Telur layak jual per hari; hari tanpa catatan dihitung nol.</p>

            @if (array_sum($chartProduksiData['values']) > 0)
                <div class="h-72" wire:ignore>
                    <canvas id="grafikDashboardProduksi"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">egg</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada produksi 7 hari terakhir</p>
                    <p class="text-xs text-gray-500 mt-1">Catat produksi harian untuk membentuk grafik.</p>
                </div>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900">Penjualan Lunas (7 Hari Terakhir)</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">Nilai nota berstatus lunas; nota belum lunas masuk piutang.</p>

            @if (array_sum($chartPenjualanData['values']) > 0)
                <div class="h-72" wire:ignore>
                    <canvas id="grafikDashboardPenjualan"></canvas>
                </div>
            @else
                <div class="h-72 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <span class="material-symbols-outlined text-4xl text-gray-300">shopping_basket</span>
                    <p class="text-sm font-medium text-gray-900 mt-2">Belum ada penjualan lunas 7 hari terakhir</p>
                    <p class="text-xs text-gray-500 mt-1">Catat penjualan telur di menu Penjualan.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Data grafik dikirim lewat atribut data supaya canvas tidak ikut ter-morph Livewire. --}}
    <div id="dataGrafikDashboard" class="hidden"
         data-grafik="{{ json_encode(['produksi' => $chartProduksiData, 'penjualan' => $chartPenjualanData]) }}"></div>

    @script
        <script>
            const gambarGrafikDashboard = () => {
                const data = Grafik.bacaDataGrafik('dataGrafikDashboard');

                if (!data) {
                    return;
                }

                if (Array.isArray(data.produksi?.labels) && data.produksi.labels.length > 0) {
                    Grafik.gambarGrafik('grafikDashboardProduksi', {
                        type: 'line',
                        data: {
                            labels: data.produksi.labels,
                            datasets: [
                                {
                                    label: 'Telur Layak Jual (butir)',
                                    data: data.produksi.values,
                                    borderColor: '#059669',
                                    backgroundColor: 'rgba(5, 150, 105, 0.12)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { callback: (nilai) => nilai + ' btr' } }
                            }
                        }
                    });
                }

                if (Array.isArray(data.penjualan?.labels) && data.penjualan.labels.length > 0) {
                    Grafik.gambarGrafik('grafikDashboardPenjualan', {
                        type: 'bar',
                        data: {
                            labels: data.penjualan.labels,
                            datasets: [
                                {
                                    label: 'Penjualan Lunas (Rp)',
                                    data: data.penjualan.values,
                                    backgroundColor: '#6366f1',
                                    borderRadius: 4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (konteks) => 'Rp ' + Number(konteks.raw).toLocaleString('id-ID')
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            };

            gambarGrafikDashboard();
        </script>
    @endscript
</div>
