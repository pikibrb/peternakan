<?php

namespace App\Support;

class PoultryFormulas
{
    /**
     * Hen-day production (%): eggs produced divided by laying hens that day.
     * Uses 1 day, so = (total eggs) / (laying ducks) * 100.
     */
    public static function henDayPct(int $telurDihasilkan, int $jumlahBebek): float
    {
        if ($jumlahBebek <= 0) {
            return 0.0;
        }

        return round($telurDihasilkan / $jumlahBebek * 100.0, 1);
    }

    /**
     * Saleable % of eggs produced.
     */
    public static function saleablePct(int $telurLayakJual, int $telurDihasilkan): float
    {
        if ($telurDihasilkan <= 0) {
            return 0.0;
        }

        return round($telurLayakJual / $telurDihasilkan * 100.0, 1);
    }

    /**
     * Cracked/defective % of eggs produced.
     */
    public static function rusakPct(int $telurRusak, int $telurDihasilkan): float
    {
        if ($telurDihasilkan <= 0) {
            return 0.0;
        }

        return round($telurRusak / $telurDihasilkan * 100.0, 1);
    }

    /**
     * Feed per dozen saleable eggs (kg, by count), if feed recorded.
     */
    public static function feedPerDozenKilograms(?float $pakanKg, int $telurLayakJual): ?float
    {
        if ($pakanKg === null || $pakanKg <= 0 || $telurLayakJual <= 0) {
            return null;
        }

        return round($pakanKg * 12.0 / $telurLayakJual, 2);
    }

    /**
     * Feed per kg of saleable eggs, if egg weight known.
     *
     * total_egg_mass_kg = telurLayakJual * beratButirG / 1000
     * feed_per_kg_egg = pakanKg / total_egg_mass_kg
     */
    public static function feedPerKgEgg(?float $pakanKg, int $telurLayakJual, ?float $beratButirG): ?float
    {
        if ($pakanKg === null || $pakanKg <= 0 || $telurLayakJual <= 0 || $beratButirG === null || $beratButirG <= 0) {
            return null;
        }

        $massKg = $telurLayakJual * $beratButirG / 1000.0;

        if ($massKg <= 0) {
            return null;
        }

        return round($pakanKg / $massKg, 2);
    }

    /**
     * Weighted hen-day % across a set of entries (avoid per-row PHP loop bias
     * by using totals rather than averaging per-entry percentages).
     */
    public static function weightedHenDayPct(int $totalTelur, int $totalHens): float
    {
        return self::henDayPct($totalTelur, $totalHens);
    }
}
