<?php

namespace App\Livewire\Forms;

use App\Models\KelompokBebek;
use App\Models\PenguranganPopulasi;
use App\Support\PopulasiBebek;
use Livewire\Form;

class PenguranganPopulasiForm extends Form
{
    public $id = null;

    public $kelompok_bebek_id = '';

    public $tanggal = '';

    public $kategori = 'Mati';

    public $penyebab = 'Penyakit';

    public $jumlah = '';

    public $catatan = '';

    public function rules(): array
    {
        return [
            'kelompok_bebek_id' => ['required', 'integer', 'exists:kelompok_bebek,id'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'kategori' => ['required', 'in:'.implode(',', PopulasiBebek::KATEGORI)],
            'penyebab' => ['required', 'in:'.implode(',', PopulasiBebek::PENYEBAB)],
            'jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'kelompok_bebek_id' => 'kandang / kelompok',
            'tanggal' => 'tanggal kejadian',
            'kategori' => 'kategori pengurangan',
            'penyebab' => 'penyebab',
            'jumlah' => 'jumlah ekor',
            'catatan' => 'catatan',
        ];
    }

    public function isEdit(): bool
    {
        return $this->id !== null;
    }

    /** Muat sebuah catatan ke dalam form untuk dikoreksi. */
    public function setRekaman(PenguranganPopulasi $rekaman): void
    {
        $this->id = $rekaman->id;
        $this->kelompok_bebek_id = $rekaman->kelompok_bebek_id;
        $this->tanggal = $rekaman->tanggal?->toDateString() ?? '';
        $this->kategori = $rekaman->kategori ?? PopulasiBebek::KATEGORI[0];
        $this->penyebab = $rekaman->penyebab;
        $this->jumlah = $rekaman->jumlah;
        $this->catatan = $rekaman->catatan ?? '';
    }

    /** Data siap simpan sesuai kolom tabel. */
    public function payload(): array
    {
        return [
            'kelompok_bebek_id' => (int) $this->kelompok_bebek_id,
            'tanggal' => $this->tanggal,
            'kategori' => $this->kategori,
            'penyebab' => $this->penyebab,
            'jumlah' => (int) $this->jumlah,
            'catatan' => $this->catatan ?: null,
        ];
    }

    /** Kandang terpilih dibaca dari basis data, bukan dari state yang bisa diubah klien. */
    public function kelompokTerpilih(): ?KelompokBebek
    {
        if ($this->kelompok_bebek_id === '' || $this->kelompok_bebek_id === null) {
            return null;
        }

        return KelompokBebek::find($this->kelompok_bebek_id);
    }

    /**
     * Jumlah catatan lama yang harus "dikembalikan" dulu sebelum menghitung
     * sisa populasi. Hanya berlaku bila kandangnya tidak dipindah.
     */
    public function jumlahDikembalikan(): int
    {
        if ($this->id === null) {
            return 0;
        }

        $lama = PenguranganPopulasi::find($this->id);

        if ($lama === null || (int) $lama->kelompok_bebek_id !== (int) $this->kelompok_bebek_id) {
            return 0;
        }

        return (int) $lama->jumlah;
    }

    /** Batas ekor yang masih boleh dicatat untuk kandang terpilih. */
    public function batas(): int
    {
        $kelompok = $this->kelompokTerpilih();

        if ($kelompok === null) {
            return 0;
        }

        return PopulasiBebek::batasPencatatan((int) $kelompok->populasi_aktif, $this->jumlahDikembalikan());
    }

    /**
     * Konteks kandang untuk pratinjau di modal: populasi aktif sekarang dan
     * sisa populasi bila catatan ini disimpan.
     */
    public function konteks(): array
    {
        $kelompok = $this->kelompokTerpilih();

        if ($kelompok === null) {
            return [
                'ada' => false,
                'nama_kandang' => null,
                'populasi_aktif' => 0,
                'populasi_awal' => 0,
                'batas' => 0,
                'sisa' => 0,
                'deplesi_persen' => 0.0,
            ];
        }

        $dikembalikan = $this->jumlahDikembalikan();
        $aktif = (int) $kelompok->populasi_aktif;
        $sisa = PopulasiBebek::setelahPengurangan($aktif + $dikembalikan, (int) $this->jumlah);
        $populasiAwal = (int) $kelompok->populasi_awal;

        return [
            'ada' => true,
            'nama_kandang' => $kelompok->nama_kandang,
            'populasi_aktif' => $aktif,
            'populasi_awal' => $populasiAwal,
            'batas' => PopulasiBebek::batasPencatatan($aktif, $dikembalikan),
            'sisa' => $sisa,
            'deplesi_persen' => PopulasiBebek::deplesiPersen(max(0, $populasiAwal - $sisa), $populasiAwal),
        ];
    }
}
