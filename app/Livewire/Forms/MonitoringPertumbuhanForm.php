<?php

namespace App\Livewire\Forms;

use App\Models\KelompokBebek;
use App\Models\MonitoringPertumbuhan;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class MonitoringPertumbuhanForm extends Form
{
    public const KONDISI = ['Sehat', 'Perlu Perhatian', 'Sakit'];

    /** ID baris yang sedang dikoreksi (null = catat sampling baru). */
    public $id = null;

    public $tanggal = '';

    public $jumlah_sampel = '';

    public $berat_rata_rata = '';

    public $berat_std_dev = '';

    public $pakan_kg = '';

    public $kondisi_kesehatan = 'Sehat';

    public $catatan = '';

    /**
     * Konteks kelompok; dipakai untuk menghitung umur sampling secara otomatis
     * tanpa perlu query tambahan setiap kali form dirender.
     */
    public $kelompok_bebek_id = null;

    public $tanggal_masuk = '';

    public $umur_awal_minggu = 0;

    public function rules(): array
    {
        return [
            'kelompok_bebek_id' => ['required', 'integer', 'exists:kelompok_bebek,id'],
            'tanggal' => [
                'required',
                'date',
                'before_or_equal:today',
                // Satu kandang disampling sekali per hari; sampling ganda membuat
                // ADG dan FCR terhitung dua kali.
                Rule::unique('monitoring_pertumbuhans', 'tanggal')
                    ->where('kelompok_bebek_id', $this->kelompok_bebek_id)
                    ->ignore($this->id),
            ],
            'jumlah_sampel' => ['required', 'integer', 'min:1', 'max:100000'],
            'berat_rata_rata' => ['required', 'numeric', 'min:1', 'max:20000'],
            'berat_std_dev' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'pakan_kg' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'kondisi_kesehatan' => ['required', 'in:'.implode(',', self::KONDISI)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'kelompok_bebek_id' => 'kandang / kelompok',
            'tanggal' => 'tanggal sampling',
            'jumlah_sampel' => 'jumlah sampel',
            'berat_rata_rata' => 'berat rata-rata',
            'berat_std_dev' => 'simpangan baku berat',
            'pakan_kg' => 'konsumsi pakan',
            'kondisi_kesehatan' => 'kondisi kesehatan',
            'catatan' => 'catatan',
        ];
    }

    public function isEdit(): bool
    {
        return $this->id !== null;
    }

    /**
     * Simpan konteks kelompok (dipanggil saat modal dibuka).
     */
    public function setKelompok(KelompokBebek $kelompok): void
    {
        $this->kelompok_bebek_id = $kelompok->id;
        $this->tanggal_masuk = $kelompok->tanggal_masuk?->toDateString() ?? '';
        $this->umur_awal_minggu = (int) $kelompok->umur_awal_minggu;
    }

    /**
     * Umur sampling (hari) dihitung dari tanggal masuk + umur awal kelompok,
     * supaya peternak tidak perlu menghitung dan memasukkannya manual.
     */
    public function umurHari(): int
    {
        if (blank($this->tanggal) || blank($this->tanggal_masuk)) {
            return 0;
        }

        $hariSejakMasuk = (int) Carbon::parse($this->tanggal_masuk)
            ->diffInDays(Carbon::parse($this->tanggal), false);

        return max(0, ((int) $this->umur_awal_minggu * 7) + $hariSejakMasuk);
    }

    public function setRekaman(MonitoringPertumbuhan $rekaman): void
    {
        $this->id = $rekaman->id;
        $this->kelompok_bebek_id = $rekaman->kelompok_bebek_id;
        $this->tanggal = $rekaman->tanggal?->toDateString() ?? '';
        $this->jumlah_sampel = $rekaman->jumlah_sampel;
        $this->berat_rata_rata = $rekaman->berat_rata_rata;
        $this->berat_std_dev = $rekaman->berat_std_dev ?? '';
        $this->pakan_kg = $rekaman->pakan_kg;
        $this->kondisi_kesehatan = $rekaman->kondisi_kesehatan;
        $this->catatan = $rekaman->catatan ?? '';
    }

    public function store(): MonitoringPertumbuhan
    {
        $this->pastikanSampelWajar();
        $this->validate();

        return MonitoringPertumbuhan::create($this->payload());
    }

    public function update(MonitoringPertumbuhan $rekaman): MonitoringPertumbuhan
    {
        $this->pastikanSampelWajar();
        $this->validate();

        $rekaman->update($this->payload());

        return $rekaman->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'kelompok_bebek_id' => $this->kelompok_bebek_id,
            'tanggal' => $this->tanggal,
            'umur_hari' => $this->umurHari(),
            'jumlah_sampel' => (int) $this->jumlah_sampel,
            'berat_rata_rata' => (float) $this->berat_rata_rata,
            'berat_std_dev' => blank($this->berat_std_dev) ? null : (float) $this->berat_std_dev,
            'pakan_kg' => (float) $this->pakan_kg,
            'kondisi_kesehatan' => $this->kondisi_kesehatan,
            'catatan' => blank($this->catatan) ? null : $this->catatan,
        ];
    }

    /**
     * Sampel ditimbang dari populasi yang hidup, jadi jumlahnya tidak mungkin
     * melebihi populasi aktif kandang.
     */
    private function pastikanSampelWajar(): void
    {
        $kelompok = $this->kelompok_bebek_id === null
            ? null
            : KelompokBebek::find($this->kelompok_bebek_id);

        if ($kelompok === null) {
            return; // ditangani rule required/exists pada kelompok.
        }

        if ((int) $this->jumlah_sampel > (int) $kelompok->populasi_aktif) {
            throw ValidationException::withMessages([
                'form.jumlah_sampel' => 'Jumlah sampel melebihi populasi aktif kandang '
                    .$kelompok->nama_kandang.' ('.number_format((int) $kelompok->populasi_aktif, 0, ',', '.').' ekor).',
            ]);
        }
    }
}
