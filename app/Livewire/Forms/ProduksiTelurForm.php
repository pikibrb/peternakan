<?php

namespace App\Livewire\Forms;

use App\Models\KelompokBebek;
use App\Models\ProduksiTelur;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ProduksiTelurForm extends Form
{
    /** ID baris yang sedang dikoreksi (null = catat produksi baru). */
    public $id = null;

    public $kelompok_bebek_id = null;

    public $tanggal_produksi = '';

    public $jumlah_bebek = '';

    public $telur_dihasilkan = '';

    public $telur_rusak = '';

    public $telur_layak_jual = '';

    public $pakan_kg = '';

    public $berat_butir_g = '';

    public $catatan = '';

    public function rules(): array
    {
        return [
            'kelompok_bebek_id' => ['required', 'integer', 'exists:kelompok_bebek,id'],
            'tanggal_produksi' => [
                'required',
                'date',
                'before_or_equal:today',
                // Satu kandang hanya boleh punya satu catatan produksi per hari
                // supaya produksi tidak terhitung dua kali.
                Rule::unique('produksi_telur', 'tanggal_produksi')
                    ->where('kelompok_bebek_id', $this->kelompok_bebek_id)
                    ->ignore($this->id),
            ],
            'jumlah_bebek' => ['required', 'integer', 'min:1', 'max:100000'],
            'telur_dihasilkan' => ['required', 'integer', 'min:0', 'max:1000000'],
            'telur_rusak' => ['nullable', 'integer', 'min:0', 'lte:telur_dihasilkan'],
            'telur_layak_jual' => ['required', 'integer', 'min:0', 'lte:telur_dihasilkan'],
            'pakan_kg' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'berat_butir_g' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'kelompok_bebek_id' => 'kandang / kelompok',
            'tanggal_produksi' => 'tanggal produksi',
            'jumlah_bebek' => 'jumlah bebek pengeram',
            'telur_dihasilkan' => 'telur dihasilkan',
            'telur_rusak' => 'telur rusak / cacat',
            'telur_layak_jual' => 'telur layak jual',
            'pakan_kg' => 'pakan (kg)',
            'berat_butir_g' => 'berat butir rata-rata (gram)',
            'catatan' => 'catatan',
        ];
    }

    public function isEdit(): bool
    {
        return $this->id !== null;
    }

    public function setRekaman(ProduksiTelur $produksi): void
    {
        $this->id = $produksi->id;
        $this->kelompok_bebek_id = $produksi->kelompok_bebek_id;
        $this->tanggal_produksi = $produksi->tanggal_produksi?->toDateString() ?? '';
        $this->jumlah_bebek = $produksi->jumlah_bebek;
        $this->telur_dihasilkan = $produksi->telur_dihasilkan;
        $this->telur_rusak = $produksi->telur_rusak ?? '';
        $this->telur_layak_jual = $produksi->telur_layak_jual;
        $this->pakan_kg = $produksi->pakan_kg ?? '';
        $this->berat_butir_g = $produksi->berat_butir_g ?? '';
        $this->catatan = $produksi->catatan ?? '';
    }

    public function store(): ProduksiTelur
    {
        $this->pastikanJumlahBebekWajar();

        $this->validate();

        return ProduksiTelur::create($this->payload());
    }

    public function update(ProduksiTelur $produksi): ProduksiTelur
    {
        $this->pastikanJumlahBebekWajar();

        $this->validate();

        $produksi->update($this->payload());

        return $produksi->refresh();
    }

    /**
     * Data siap simpan; dipakai bersama oleh tambah dan koreksi supaya aturan
     * pengisian hanya ada di satu tempat.
     */
    private function payload(): array
    {
        return [
            'kelompok_bebek_id' => (int) $this->kelompok_bebek_id,
            'tanggal_produksi' => $this->tanggal_produksi,
            'jumlah_bebek' => (int) $this->jumlah_bebek,
            'telur_dihasilkan' => (int) $this->telur_dihasilkan,
            'telur_rusak' => blank($this->telur_rusak) ? 0 : (int) $this->telur_rusak,
            'telur_layak_jual' => (int) $this->telur_layak_jual,
            'pakan_kg' => blank($this->pakan_kg) ? null : (float) $this->pakan_kg,
            'berat_butir_g' => blank($this->berat_butir_g) ? null : (float) $this->berat_butir_g,
            'catatan' => blank($this->catatan) ? null : $this->catatan,
        ];
    }

    /**
     * Dua aturan lintas kolom yang tidak bisa ditulis sebagai rule biasa:
     * telur rusak + layak jual tidak boleh melebihi total panen, dan bebek
     * pengeram tidak boleh melebihi populasi aktif kandang.
     */
    private function pastikanJumlahBebekWajar(): void
    {
        $kelompok = KelompokBebek::find($this->kelompok_bebek_id);

        if ($kelompok === null) {
            return; // ditangani rule exists.
        }

        if ((int) $this->jumlah_bebek > (int) $kelompok->populasi_aktif) {
            throw ValidationException::withMessages([
                'form.jumlah_bebek' => 'Bebek pengeram melebihi populasi aktif kandang '
                    .$kelompok->nama_kandang.' ('.number_format((int) $kelompok->populasi_aktif, 0, ',', '.').' ekor).',
            ]);
        }

        $rusak = blank($this->telur_rusak) ? 0 : (int) $this->telur_rusak;
        $selisih = (int) $this->telur_layak_jual + $rusak - (int) $this->telur_dihasilkan;

        if ($selisih > 0) {
            throw ValidationException::withMessages([
                'form.telur_layak_jual' => 'Telur layak jual + rusak melebihi total panen sebanyak '
                    .number_format($selisih, 0, ',', '.').' butir. Layak jual dihitung dari total panen dikurangi telur rusak.',
            ]);
        }
    }
}
