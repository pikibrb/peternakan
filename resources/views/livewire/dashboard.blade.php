<div class="p-6 bg-gray-50 min-h-screen">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Dashboard Peternakan</h1>
        <p class="text-sm text-gray-600">Ringkasan performa populasi, produksi telur, dan penjualan.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Populasi Bebek</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($totalPopulasi) }} ekor</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Produksi Hari Ini</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($produksiHariIni) }} butir</p>
                </div>
                <div class="p-3 bg-emerald-50 rounded-lg text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok Telur Layak Jual</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($stokTelur) }} butir</p>
                </div>
                <div class="p-3 bg-amber-50 rounded-lg text-amber-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Penjualan</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">Rp {{ number_format($totalPenjualan, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 bg-indigo-50 rounded-lg text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Grafik Produksi Telur (7 Hari Terakhir)</h3>
            <canvas id="chartProduksi"></canvas>
        </div>

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Grafik Penjualan (7 Hari Terakhir)</h3>
            <canvas id="chartPenjualan"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctxProduksi = document.getElementById('chartProduksi').getContext('2d');
            new Chart(ctxProduksi, {
                type: 'line',
                data: {
                    labels: @json($chartProduksiData['labels']),
                    datasets: [{
                        label: 'Telur Layak Jual',
                        data: @json($chartProduksiData['values']),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            const ctxPenjualan = document.getElementById('chartPenjualan').getContext('2d');
            new Chart(ctxPenjualan, {
                type: 'bar',
                data: {
                    labels: @json($chartPenjualanData['labels']),
                    datasets: [{
                        label: 'Total Penjualan (Rp)',
                        data: @json($chartPenjualanData['values']),
                        backgroundColor: '#6366f1'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        });
    </script>
</div>