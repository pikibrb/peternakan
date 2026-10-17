<?php

namespace Tests\Feature;

use App\Models\KelompokBebek;
use App\Models\MonitoringPertumbuhan;
use App\Models\ProduksiTelur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrudRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function kelompok(string $nama, array $ganti = []): KelompokBebek
    {
        return KelompokBebek::create(array_merge([
            'nama_kandang' => $nama,
            'jenis_bebek' => 'Mojosari',
            'fase' => 'Grower',
            'tanggal_masuk' => '2026-09-01',
            'umur_awal_minggu' => 0,
            'populasi_awal' => 500,
            'populasi_aktif' => 480,
            'status' => 'Aktif',
        ], $ganti));
    }

    private function isiMonitoring(Testable $komponen, string $tanggal, int $berat): void
    {
        $komponen
            ->call('openModal')
            ->set('form.tanggal', $tanggal)
            ->set('form.jumlah_sampel', 30)
            ->set('form.berat_rata_rata', $berat)
            ->set('form.pakan_kg', 50)
            ->call('save');
    }

    private function isiProduksi(Testable $komponen, string $tanggal, int $layak = 100): void
    {
        $komponen
            ->call('openModal')
            ->set('form.tanggal_produksi', $tanggal)
            ->set('form.jumlah_bebek', 400)
            ->set('form.telur_dihasilkan', $layak)
            ->set('form.telur_layak_jual', $layak)
            ->call('save');
    }

    public function test_monitoring_pertumbuhan_mencatat_mengoreksi_dan_menghapus(): void
    {
        $this->masuk();
        $kandang = $this->kelompok('Audit-Kandang');

        $komponen = Volt::test('monitoring-pertumbuhan.index')->set('kelompokId', $kandang->id);

        $this->isiMonitoring($komponen, '2026-09-08', 300);
        $komponen->assertHasNoErrors();

        $this->isiMonitoring($komponen, '2026-09-15', 500);
        $komponen->assertHasNoErrors();

        $tersimpan = MonitoringPertumbuhan::query()->orderBy('tanggal')->get();

        $this->assertSame(2, $tersimpan->count());
        // Umur dihitung ulang dari tanggal masuk (2026-09-01).
        $this->assertSame([7, 14], $tersimpan->pluck('umur_hari')->all());

        // Duplikat tanggal pada kandang yang sama ditolak.
        $this->isiMonitoring($komponen, '2026-09-15', 510);
        $komponen->assertHasErrors('form.tanggal');

        // Koreksi baris pertama tanpa mengubah tanggal.
        $id = MonitoringPertumbuhan::query()->whereDate('tanggal', '2026-09-08')->value('id');
        $komponen->call('edit', $id)->set('form.berat_rata_rata', 350)->call('save');
        $komponen->assertHasNoErrors();
        $this->assertSame(350.0, MonitoringPertumbuhan::query()->find($id)->berat_rata_rata);

        // Koreksi tanggal ke tanggal yang sudah dipakai baris lain ditolak.
        $komponen->call('edit', $id)->set('form.tanggal', '2026-09-15')->call('save');
        $komponen->assertHasErrors('form.tanggal');
        $this->assertSame('2026-09-08', MonitoringPertumbuhan::query()->find($id)->tanggal->toDateString());

        // Hapus.
        $komponen->call('hapus', $id);
        $this->assertSame(1, MonitoringPertumbuhan::count());
        $this->assertSame('2026-09-15', MonitoringPertumbuhan::query()->first()->tanggal->toDateString());
    }

    public function test_monitoring_pertumbuhan_dua_kandang_tanggal_sama_dan_koreksi_tanggal(): void
    {
        $this->masuk();
        $a = $this->kelompok('Probe-A');
        $b = $this->kelompok('Probe-B');

        $komponen = Volt::test('monitoring-pertumbuhan.index')->set('kelompokId', $a->id);

        $this->isiMonitoring($komponen, '2026-09-08', 300);
        $komponen->assertHasNoErrors();

        // Pindah kandang lewat select: modal tertutup dan form direset.
        $komponen->set('kelompokId', $b->id);
        $this->assertFalse($komponen->get('isModalOpen'));

        // Tanggal sama untuk kandang berbeda harus boleh.
        $this->isiMonitoring($komponen, '2026-09-08', 320);
        $komponen->assertHasNoErrors();
        $this->assertSame(2, MonitoringPertumbuhan::count());

        // Balik ke A, koreksi baris A tanpa perubahan.
        $komponen->set('kelompokId', $a->id);
        $idA = MonitoringPertumbuhan::query()->where('kelompok_bebek_id', $a->id)->value('id');

        $komponen->call('edit', $idA);
        $this->assertTrue($komponen->get('isModalOpen'));
        $this->assertSame($idA, (int) $komponen->get('form.id'));
        $this->assertSame($a->id, (int) $komponen->get('form.kelompok_bebek_id'));

        $komponen->call('save');
        $komponen->assertHasNoErrors();

        // Ubah tanggal ke tanggal baru -> sukses dan umur dihitung ulang (9 hari).
        $komponen->call('edit', $idA)->set('form.tanggal', '2026-09-10')->call('save');
        $komponen->assertHasNoErrors();

        $baris = MonitoringPertumbuhan::query()->find($idA);

        $this->assertSame('2026-09-10', $baris->tanggal->toDateString());
        $this->assertSame(9, $baris->umur_hari);
        $this->assertSame(2, MonitoringPertumbuhan::count());
    }

    public function test_monitoring_pertumbuhan_pulih_saat_kandang_terpilih_dihapus(): void
    {
        $this->masuk();
        $a = $this->kelompok('Probe-Hapus');
        $b = $this->kelompok('Probe-Sisa');

        $komponen = Volt::test('monitoring-pertumbuhan.index')->set('kelompokId', $a->id);

        // Hapus kandang terpilih lewat model (setara dihapus dari menu Data Bebek).
        KelompokBebek::query()->whereKey($a->id)->delete();

        // Kandang pertama yang kini ditampilkan halaman.
        $pertama = KelompokBebek::query()
            ->orderBy('status')
            ->orderBy('nama_kandang')
            ->value('id');

        $this->assertSame($b->id, $pertama);

        // Aksi harus pulih ke kandang yang sedang ditampilkan, bukan diam.
        $komponen->call('openModal');

        $this->assertTrue($komponen->get('isModalOpen'));
        $this->assertSame($pertama, (int) $komponen->get('kelompokId'));
        $this->assertSame($pertama, (int) $komponen->get('form.kelompok_bebek_id'));

        // Simpan tetap berjalan dan tercatat pada kandang fallback.
        $this->isiMonitoring($komponen, '2026-09-08', 300);
        $komponen->assertHasNoErrors();

        $this->assertSame($pertama, (int) MonitoringPertumbuhan::query()->value('kelompok_bebek_id'));
    }

    public function test_produksi_telur_mencatat_mengoreksi_dan_menghapus(): void
    {
        $this->masuk();
        $kandang = $this->kelompok('Audit-Kandang');

        $komponen = Volt::test('produksi-telur.index')->set('flock_id', $kandang->id);

        $komponen->call('openModal')
            ->set('form.tanggal_produksi', '2026-09-08')
            ->set('form.jumlah_bebek', 470)
            ->set('form.telur_dihasilkan', 400)
            ->set('form.telur_rusak', 10)
            ->set('form.telur_layak_jual', 390)
            ->set('form.pakan_kg', 60)
            ->call('save');
        $komponen->assertHasNoErrors();

        // Duplikat tanggal pada kandang yang sama ditolak.
        $komponen->call('openModal')
            ->set('form.tanggal_produksi', '2026-09-08')
            ->set('form.jumlah_bebek', 470)
            ->set('form.telur_dihasilkan', 100)
            ->set('form.telur_layak_jual', 100)
            ->call('save');
        $komponen->assertHasErrors('form.tanggal_produksi');

        // Koreksi.
        $baris = ProduksiTelur::query()->first();

        $komponen->call('edit', $baris->id)
            ->set('form.telur_dihasilkan', 410)
            ->set('form.telur_rusak', 10)
            ->set('form.telur_layak_jual', 400)
            ->call('save');
        $komponen->assertHasNoErrors();

        $baris->refresh();
        $this->assertSame(410, $baris->telur_dihasilkan);
        $this->assertSame(400, $baris->telur_layak_jual);

        // Hapus.
        $komponen->call('hapus', $baris->id);

        $this->assertSame(0, ProduksiTelur::count());
    }

    public function test_produksi_telur_open_modal_memilih_kandang_sesuai_filter(): void
    {
        $this->masuk();
        $aktif = $this->kelompok('Probe-Aktif', ['status' => 'Aktif']);
        $this->kelompok('Probe-Kosong', ['status' => 'Kosong']);

        // Tanpa sentuh filter (null) -> kandang aktif pertama.
        $k1 = Volt::test('produksi-telur.index');
        $k1->call('openModal');
        $this->assertSame($aktif->id, (int) $k1->get('form.kelompok_bebek_id'));

        // Filter "Semua kandang" ('') -> tetap memilih kandang aktif pertama.
        $k2 = Volt::test('produksi-telur.index')->set('flock_id', '');
        $k2->call('openModal');
        $this->assertSame($aktif->id, (int) $k2->get('form.kelompok_bebek_id'));

        // Filter kandang nonaktif -> dipakai apa adanya.
        $kosong = KelompokBebek::query()->where('status', 'Kosong')->first();
        $k3 = Volt::test('produksi-telur.index')->set('flock_id', $kosong->id);
        $k3->call('openModal');
        $this->assertSame($kosong->id, (int) $k3->get('form.kelompok_bebek_id'));

        // Tidak ada kandang aktif -> fallback string kosong (pilih manual).
        KelompokBebek::query()->update(['status' => 'Kosong']);
        $k4 = Volt::test('produksi-telur.index')->set('flock_id', '');
        $k4->call('openModal');
        $this->assertSame('', $k4->get('form.kelompok_bebek_id'));
    }

    public function test_produksi_telur_pindah_kandang_saat_koreksi(): void
    {
        $this->masuk();
        $a = $this->kelompok('Probe-A');
        $b = $this->kelompok('Probe-B');

        $komponen = Volt::test('produksi-telur.index')->set('flock_id', $a->id);

        $this->isiProduksi($komponen, '2026-09-08', 100);
        $komponen->assertHasNoErrors();

        // Baris kedua untuk kandang B tanggal sama.
        $komponen->set('flock_id', $b->id);
        $this->isiProduksi($komponen, '2026-09-08', 200);
        $komponen->assertHasNoErrors();

        // Koreksi baris B pindah ke kandang A pada tanggal sama -> bentrok dengan baris A.
        $idB = ProduksiTelur::query()->where('kelompok_bebek_id', $b->id)->value('id');
        $komponen->call('edit', $idB)->set('form.kelompok_bebek_id', $a->id)->call('save');
        $komponen->assertHasErrors('form.tanggal_produksi');

        // Pindah ke kandang A dengan tanggal berbeda -> sukses.
        $komponen->set('form.tanggal_produksi', '2026-09-09')->call('save');
        $komponen->assertHasNoErrors();

        $baris = ProduksiTelur::query()->find($idB);

        $this->assertSame($a->id, (int) $baris->kelompok_bebek_id);
        $this->assertSame('2026-09-09', $baris->tanggal_produksi->toDateString());
        $this->assertSame(2, ProduksiTelur::count());
    }
}
