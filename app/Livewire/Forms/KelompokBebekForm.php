<?php

namespace App\Livewire\Forms;

use App\Models\KelompokBebek;
use Livewire\Form;

class KelompokBebekForm extends Form
{
    public string $nama_kandang = '';

    public string $jenis_bebek = '';

    public string $fase = 'Layer';

    public $umur_awal_minggu = 0;

    public string $tanggal_masuk = '';

    public $populasi_awal = '';

    public function rules(): array
    {
        return [
            'nama_kandang' => 'required|string|max:255',
            'jenis_bebek' => 'required|string|max:255',
            'fase' => 'required|in:Starter,Grower,Layer,Afkir',
            'tanggal_masuk' => 'required|date',
            'umur_awal_minggu' => 'required|integer|min:0',
            'populasi_awal' => 'required|integer|min:1',
        ];
    }

    public function store(): void
    {
        $this->validate();

        KelompokBebek::create([
            'nama_kandang' => $this->nama_kandang,
            'jenis_bebek' => $this->jenis_bebek,
            'fase' => $this->fase,
            'tanggal_masuk' => $this->tanggal_masuk,
            'umur_awal_minggu' => $this->umur_awal_minggu,
            'populasi_awal' => (int) $this->populasi_awal,
            'populasi_aktif' => (int) $this->populasi_awal,
            'status' => 'Aktif',
        ]);
    }
}
