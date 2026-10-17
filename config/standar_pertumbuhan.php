<?php

/*
|--------------------------------------------------------------------------
| Standar Referensi Pertumbuhan Bebek Petelur
|--------------------------------------------------------------------------
|
| Angka pada berkas ini dipakai sebagai pembanding (benchmark) pada halaman
| Monitoring Pertumbuhan: berat badan, FCR, dan konsumsi pakan.
|
| Nilai di bawah adalah referensi umum untuk bebek petelur tipe lokal
| (Mojosari/Tegal). Sesuaikan dengan buku standar (breed standard) dari
| pembibit yang Anda pakai, atau hasil recording peternakan Anda sendiri.
|
| Kunci tabel = umur dalam minggu. Nilai antar minggu diinterpolasi linear
| oleh App\Support\StandarPertumbuhan.
|
*/

return [

    // Berat badan target (gram/ekor). FCR dihitung dari minggu ke-0 (DOD).
    'berat_target' => [
        0 => 45,
        2 => 145,
        4 => 380,
        6 => 650,
        8 => 850,
        10 => 1050,
        12 => 1200,
        14 => 1320,
        16 => 1420,
        18 => 1500,
        20 => 1550,
        24 => 1600,
        28 => 1650,
        32 => 1700,
    ],

    // FCR kumulatif target = kg pakan / kg pertambahan berat badan sejak DOD.
    // Setelah fase grower (22 minggu) feed conversion diukur per kg telur,
    // sehingga target FCR berat tidak lagi relevan.
    'fcr_target' => [
        2 => 2.8,
        4 => 3.2,
        6 => 3.6,
        8 => 4.0,
        10 => 4.5,
        12 => 5.1,
        14 => 5.9,
        16 => 6.8,
        18 => 7.8,
        20 => 8.9,
        22 => 10.1,
    ],

    // Konsumsi pakan target (gram/ekor/hari).
    'konsumsi_harian_target' => [
        0 => 8,
        2 => 25,
        4 => 45,
        6 => 65,
        8 => 80,
        10 => 95,
        12 => 110,
        14 => 120,
        16 => 130,
        18 => 140,
        20 => 150,
        24 => 160,
    ],

    // Ambang batas penilaian.
    'ambang' => [
        // Keseragaman (%) populasi.
        'keseragaman_baik' => 80,
        'keseragaman_cukup' => 70,

        // Toleransi simpangan berat terhadap target (%).
        'deviasi_toleransi' => 5,

        // Jarak ideal antar pengambilan sampel (hari).
        'selang_sampling_ideal' => 7,
    ],

];
