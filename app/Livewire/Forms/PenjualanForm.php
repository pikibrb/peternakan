<?php

namespace App\Livewire\Forms;

use App\Models\Pembeli;
use App\Models\Penjualan;
use App\Support\PenjualanTelur;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PenjualanForm extends Form
{
    public $id = null;

    /** Pembeli yang sudah terdaftar ('' = tanpa pembeli). */
    public $pembeli_id = '';

    /** Isi pembeli baru langsung dari nota (hanya untuk nota baru). */
    public $pembeli_baru = false;

    public $nama_pembeli_baru = '';

    public $kontak_pembeli_baru = '';

    public $tanggal_penjualan = '';

    public $status_pembayaran = 'Lunas';

    public $catatan = '';

    /**
     * Baris nota. Satu nota bisa berisi beberapa baris (mis. telur
     * grade A dan grade B dengan harga berbeda).
     *
     * @var array<int, array{jumlah_telur: mixed, harga_per_butir: mixed}>
     */
    public array $items = [
        ['jumlah_telur' => '', 'harga_per_butir' => ''],
    ];

    public static function itemKosong(): array
    {
        return ['jumlah_telur' => '', 'harga_per_butir' => ''];
    }

    public function rules(): array
    {
        return [
            'pembeli_id' => ['nullable', 'integer', 'exists:pembelis,id'],
            'nama_pembeli_baru' => [
                Rule::requiredIf(fn () => $this->pembeliBaruAktif()),
                'nullable',
                'string',
                'max:255',
            ],
            'kontak_pembeli_baru' => ['nullable', 'string', 'max:255'],
            'tanggal_penjualan' => ['required', 'date', 'before_or_equal:today'],
            'status_pembayaran' => ['required', 'in:'.implode(',', PenjualanTelur::STATUS)],
            'catatan' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.jumlah_telur' => ['required', 'integer', 'min:1', 'max:1000000'],
            // Harga 0 diizinkan untuk penjualan non-komersial (sumbangan).
            'items.*.harga_per_butir' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'pembeli_id' => 'pembeli',
            'nama_pembeli_baru' => 'nama pembeli baru',
            'kontak_pembeli_baru' => 'kontak pembeli baru',
            'tanggal_penjualan' => 'tanggal penjualan',
            'status_pembayaran' => 'status pembayaran',
            'catatan' => 'catatan',
            'items' => 'rincian telur',
            'items.*.jumlah_telur' => 'jumlah telur',
            'items.*.harga_per_butir' => 'harga per butir',
        ];
    }

    public function isEdit(): bool
    {
        return $this->id !== null;
    }

    /** Pembeli baru hanya bisa dibuat saat mencatat nota baru. */
    public function pembeliBaruAktif(): bool
    {
        return (bool) $this->pembeli_baru && $this->id === null;
    }

    public function tambahItem(): void
    {
        $this->items[] = static::itemKosong();
    }

    public function hapusItem(int $index): void
    {
        // Nota harus menyisakan minimal satu baris.
        if (count($this->items) <= 1 || ! array_key_exists($index, $this->items)) {
            return;
        }

        unset($this->items[$index]);

        // Kunci array harus rapat supaya wire:model dan aturan items.* tetap sinkron.
        $this->items = array_values($this->items);
    }

    /** Total butir telur pada nota. */
    public function totalEkor(): int
    {
        return (int) array_sum(array_map(
            fn ($item) => max(0, (int) ($item['jumlah_telur'] ?? 0)),
            $this->items
        ));
    }

    /** Nilai nota = jumlah tiap baris x harganya. */
    public function total(): float
    {
        return round(array_sum(array_map(
            fn ($item) => PenjualanTelur::subtotal(
                (int) ($item['jumlah_telur'] ?? 0),
                (float) ($item['harga_per_butir'] ?? 0)
            ),
            $this->items
        )), 2);
    }

    public function hargaRataRata(): ?float
    {
        return PenjualanTelur::hargaRataRata($this->total(), $this->totalEkor());
    }

    /** Muat sebuah nota ke dalam form untuk dikoreksi. */
    public function setRekaman(Penjualan $penjualan): void
    {
        $penjualan->loadMissing('detailPenjualans');

        $this->id = $penjualan->id;
        $this->pembeli_id = $penjualan->pembeli_id ?? '';
        $this->pembeli_baru = false;
        $this->nama_pembeli_baru = '';
        $this->kontak_pembeli_baru = '';
        $this->tanggal_penjualan = $penjualan->tanggal_penjualan?->toDateString() ?? '';
        $this->status_pembayaran = $penjualan->status_pembayaran;
        $this->catatan = $penjualan->catatan ?? '';

        $this->items = $penjualan->detailPenjualans
            ->map(fn ($detail) => [
                'jumlah_telur' => (int) $detail->jumlah_telur,
                'harga_per_butir' => (float) $detail->harga_per_butir,
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items = [static::itemKosong()];
        }
    }

    public function store(): Penjualan
    {
        $this->normalisasiPilihanKosong();
        $this->validate();

        return DB::transaction(function () {
            $penjualan = Penjualan::create([
                'pembeli_id' => $this->pembeliId(),
                'tanggal_penjualan' => $this->tanggal_penjualan,
                'total_penjualan' => $this->total(),
                'status_pembayaran' => $this->status_pembayaran,
                'catatan' => $this->catatan ?: null,
            ]);

            $penjualan->detailPenjualans()->createMany($this->detailData());

            return $penjualan;
        });
    }

    public function update(): Penjualan
    {
        $this->normalisasiPilihanKosong();
        $this->validate();

        return DB::transaction(function () {
            $penjualan = Penjualan::findOrFail($this->id);

            $penjualan->update([
                'pembeli_id' => $this->pembeliId(),
                'tanggal_penjualan' => $this->tanggal_penjualan,
                'total_penjualan' => $this->total(),
                'status_pembayaran' => $this->status_pembayaran,
                'catatan' => $this->catatan ?: null,
            ]);

            // Baris lama diganti seluruhnya supaya jumlah baris nota bisa berkurang.
            $penjualan->detailPenjualans()->delete();
            $penjualan->detailPenjualans()->createMany($this->detailData());

            return $penjualan->refresh();
        });
    }

    /**
     * Pilihan "-- tanpa pembeli --" dari <select> dikirim sebagai string kosong;
     * ubah ke null supaya aturan nullable|integer tidak menolaknya.
     */
    private function normalisasiPilihanKosong(): void
    {
        foreach (['pembeli_id', 'nama_pembeli_baru', 'kontak_pembeli_baru'] as $field) {
            if (is_string($this->{$field}) && trim($this->{$field}) === '') {
                $this->{$field} = null;
            }
        }
    }

    /** Baris detail siap disimpan; subtotal dihitung server, bukan dari klien. */
    private function detailData(): array
    {
        return array_map(fn ($item) => [
            'jumlah_telur' => (int) $item['jumlah_telur'],
            'harga_per_butir' => (float) $item['harga_per_butir'],
            'subtotal' => PenjualanTelur::subtotal(
                (int) $item['jumlah_telur'],
                (float) $item['harga_per_butir']
            ),
        ], array_values($this->items));
    }

    /** Pembeli yang dipakai nota; nama baru dibuat sekali saat menyimpan. */
    private function pembeliId(): ?int
    {
        if ($this->pembeliBaruAktif() && trim((string) $this->nama_pembeli_baru) !== '') {
            $kontak = trim((string) $this->kontak_pembeli_baru);

            return Pembeli::create([
                'nama_pembeli' => trim((string) $this->nama_pembeli_baru),
                'kontak' => $kontak !== '' ? $kontak : null,
            ])->id;
        }

        return $this->pembeli_id === '' || $this->pembeli_id === null
            ? null
            : (int) $this->pembeli_id;
    }
}
