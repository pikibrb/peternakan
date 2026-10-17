<?php

namespace Tests\Unit;

use App\Support\PoultryFormulas;
use PHPUnit\Framework\TestCase;

class PoultryFormulasTest extends TestCase
{
    public function test_hen_day_production_dari_jumlah_telur_dan_bebek_pengeram(): void
    {
        // 420 butir dari 480 ekor pengeram = 87,5%
        $this->assertSame(87.5, PoultryFormulas::henDayPct(420, 480));

        // Tidak ada bebek pengeram -> tidak ada pembagian nol.
        $this->assertSame(0.0, PoultryFormulas::henDayPct(120, 0));
    }

    public function test_persentase_distribusi_telur(): void
    {
        $this->assertSame(97.1, PoultryFormulas::saleablePct(408, 420));
        $this->assertSame(2.9, PoultryFormulas::rusakPct(12, 420));

        // Produksi nol tidak menghasilkan persentase.
        $this->assertSame(0.0, PoultryFormulas::saleablePct(0, 0));
        $this->assertSame(0.0, PoultryFormulas::rusakPct(5, 0));
    }

    public function test_konversi_pakan_per_lusin_dan_per_kg_telur(): void
    {
        // 84,5 kg pakan untuk 468 butir = 2,17 kg per lusin
        $this->assertSame(2.17, PoultryFormulas::feedPerDozenKilograms(84.5, 468));

        // 84,5 kg pakan untuk 468 butir @ 68 g = 31,824 kg telur -> 2,66
        $this->assertSame(2.66, PoultryFormulas::feedPerKgEgg(84.5, 468, 68.0));

        // Tanpa pakan / tanpa telur / tanpa berat butir: tidak dihitung.
        $this->assertNull(PoultryFormulas::feedPerDozenKilograms(null, 468));
        $this->assertNull(PoultryFormulas::feedPerDozenKilograms(0.0, 468));
        $this->assertNull(PoultryFormulas::feedPerDozenKilograms(84.5, 0));
        $this->assertNull(PoultryFormulas::feedPerKgEgg(84.5, 468, null));
        $this->assertNull(PoultryFormulas::feedPerKgEgg(84.5, 468, 0.0));
    }

    public function test_hen_day_tertimbang_memakai_total_bukan_rata_rata_baris(): void
    {
        // Baris A: 500 butir / 1.000 ekor (50%), Baris B: 10 butir / 10 ekor (100%).
        // Rata-rata sederhana = 75%, tertimbang = 510/1010 = 50,5%.
        $this->assertSame(50.5, PoultryFormulas::weightedHenDayPct(510, 1010));
    }
}
