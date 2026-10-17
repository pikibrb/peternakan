// Helper grafik & dialog bersama.
//
// Semua halaman laporan memakai pola yang sama: data grafik dikirim server lewat
// atribut data (canvas tidak ikut ter-morph), lalu digambar ulang saat ada
// perubahan filter. Helper ini menghapus duplikasi kode di setiap @script.

const contoh = {};

/** Baca payload grafik dari atribut data; null bila belum ada data. */
export function bacaDataGrafik(idElemen) {
    const elemen = document.getElementById(idElemen);

    if (!elemen) {
        return null;
    }

    try {
        return JSON.parse(elemen.dataset.grafik || '{}');
    } catch (kesalahan) {
        return null;
    }
}

/** Gambar (atau gambar ulang) satu canvas, instance lama dihancurkan dulu. */
export function gambarGrafik(idCanvas, konfigurasi) {
    const canvas = document.getElementById(idCanvas);

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    if (contoh[idCanvas]) {
        contoh[idCanvas].destroy();
    }

    contoh[idCanvas] = new Chart(canvas, konfigurasi);
}

/**
 * Dengarkan event refresh dari server dan gambar ulang grafik.
 * Terdaftar sekali saja per nama event walau @script dieksekusi berulang.
 */
export function pantauGrafik(namaEvent, gambarUlang) {
    window.__pantauGrafik = window.__pantauGrafik || {};

    if (window.__pantauGrafik[namaEvent]) {
        return;
    }

    window.__pantauGrafik[namaEvent] = true;

    Livewire.on(namaEvent, () => setTimeout(gambarUlang, 0));
}

/** Tampilkan notifikasi SweetAlert dari event server (sekali terdaftar saja). */
export function pasangPeringatanSwal() {
    if (window.__peringatanSwalSiap) {
        return;
    }

    window.__peringatanSwalSiap = true;

    Livewire.on('swal:alert', (payload) => {
        const pesan = Array.isArray(payload) ? payload[0] : payload;

        Swal.fire({
            title: pesan.title,
            text: pesan.text,
            icon: pesan.icon,
            confirmButtonColor: '#059669',
        });
    });
}

/** Dialog konfirmasi seragam untuk aksi simpan/hapus/tandai. */
export function konfirmasi({ judul, teks, ikon = 'question', tombol = 'Ya, lanjutkan', warna = '#059669', aksi }) {
    Swal.fire({
        title: judul,
        text: teks,
        icon: ikon,
        showCancelButton: true,
        confirmButtonColor: warna,
        cancelButtonColor: '#6b7280',
        confirmButtonText: tombol,
        cancelButtonText: 'Batal',
    }).then((hasil) => {
        if (hasil.isConfirmed && typeof aksi === 'function') {
            aksi();
        }
    });
}
