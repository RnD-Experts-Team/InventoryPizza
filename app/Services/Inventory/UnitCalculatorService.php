<?php

namespace App\Services\Inventory;

class UnitCalculatorService
{
    public function calculate(
        float $countU1,
        float $countU2,
        float $countU3,
        float $u2PerU1,
        float $u3PerU2
    ): float {
        $totalU2 = $countU2 + ($u3PerU2 > 0 ? $countU3 / $u3PerU2 : 0);
        $totalU1 = $countU1 + ($totalU2 / $u2PerU1);

        return round($totalU1, 4);
    }
}
