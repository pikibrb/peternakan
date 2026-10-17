<?php

namespace App\Livewire\Forms;

use App\Models\KelompokBebek;
use App\Models\PenguranganPopulasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class KelompokBebekForm extends Form
{
    public const FASE = ['Starter', 'Grower', 'Layer', 'Afkir'];

    public const STATUS = ['Aktif', 'Kosong'];

    /** ID kandang yang sedang dikoreksi (null = daftarkan kandang baru). */
    public $id = null;

    public string $nama_kandang = '';

    public string $jenis_bebek = '';

    public string $fase = 'Layer';

    public $umur_awal_minggu = 0;

    public string $tanggal_masuk = '';

    public $populasi_awal = '';

    public string $status = 'Aktif';

    public function rules(): array
    {
        return [
            'nama_kandang' => [
                'required',
                'string',
                'max:255',
                // Nama kandang adalah identitas flock: tidak boleh ganda.
                Rule::unique('kelompok_bebek', 'nama_kandang')->ignore($this->id),
            ],
            'jenis_bebek' => ['required', 'string', 'max:255'],
            'fase' => ['required', 'in:'.implode(',', self::FASE)],
            'tanggal_masuk' => ['required', 'date', 'before_or_equal:today'],
            'umur_awal_minggu' => ['required', 'integer', 'min:0', 'max:200'],
            'populasi_awal' => ['required', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', 'in:'.implode(',', self::STATUS)],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'nama_kandang' => 'nama kandang',
            'jenis_bebek' => 'jenis / strain bebek',
            'fase' => 'fase pemeliharaan',
            'tanggal_masuk' => 'tanggal masuk (check-in)',
            'umur_awal_minggu' => 'umur awal',
            'populasi_awal' => 'populasi awal',
            'status' => 'status kandang',
        ];
    }

    public function isEdit(): bool
    {
        return $this->id !== null;
    }

    public function setRekaman(KelompokBebek $kelompok): void
    {
        $this->id = $kelompok->id;
        $this->nama_kandang = $kelompok->nama_kandang;
        $this->jenis_bebek = $kelompok->jenis_bebek;
        $this->fase = $kelompok->fase;
        $this->umur_awal_minggu = (int) $kelompok->umur_awal_minggu;
        $this->tanggal_masuk = $kelompok->tanggal_masuk?->toDateString() ?? '';
        $this->populasi_awal = (int) $kelompok->populasi_awal;
        $this->status = $kelompok->status;
    }

    /** Total ekor yang sudah tercatat hilang (deplesi) pada sebuah kandang. */
    public function deplesiTercatat(int $kelompokId): int
    {
        return (int) PenguranganPopulasi::query()
            ->where('kelompok_bebek_id', $kelompokId)
            ->sum('jumlah');
    }

    public function store(): KelompokBebek
    {
        $this->validate();

        return KelompokBebek::create([
            'nama_kandang' => $this->nama_kandang,
            'jenis_bebek' => $this->jenis_bebek,
            'fase' => $this->fase,
            'tanggal_masuk' => $this->tanggal_masuk,
            'umur_awal_minggu' => (int) $this->umur_awal_minggu,
            'populasi_awal' => (int) $this->populasi_awal,
            // Kandang baru selalu mulai penuh.
            'populasi_aktif' => (int) $this->populasi_awal,
            'status' => $this->status,
        ]);
    }

    /**
     * Koreksi data kandang. Populasi aktif tidak diisi manual: selalu dihitung
     * ulang sebagai populasi awal dikurangi seluruh catatan deplesi, sehingga
     * buku populasi tetap konsisten dengan menu Kematian & Deplesi. Menambah
     * populasi awal otomatis menambah populasi aktif dengan selisih yang sama
     * (memasukkan bebek baru), menurunkannya berarti mengoreksi data.
     */
    public function update(KelompokBebek $kelompok): KelompokBebek
    {
        $this->validate();

        $deplesi = $this->deplesiTercatat($kelompok->id);
        $populasiAwal = (int) $this->populasi_awal;

        if ($populasiAwal < $deplesi) {
            throw ValidationException::withMessages([
                'form.populasi_awal' => 'Populasi awal minimal '.number_format($deplesi, 0, ',', '.')
                    .' ekor karena deplesi tercatat sudah sebanyak itu. Koreksi catatan deplesi dulu bila populasi awal memang lebih kecil.',
            ]);
        }

        DB::transaction(function () use ($kelompok, $populasiAwal, $deplesi) {
            $kelompok->update([
                'nama_kandang' => $this->nama_kandang,
                'jenis_bebek' => $this->jenis_bebek,
                'fase' => $this->fase,
                'tanggal_masuk' => $this->tanggal_masuk,
                'umur_awal_minggu' => (int) $this->umur_awal_minggu,
                'populasi_awal' => $populasiAwal,
                'populasi_aktif' => $populasiAwal - $deplesi,
                'status' => $this->status,
            ]);
        });

        return $kelompok->refresh();
    }
}
