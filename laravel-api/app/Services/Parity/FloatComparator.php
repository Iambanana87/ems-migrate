<?php

declare(strict_types=1);

namespace App\Services\Parity;

/**
 * FloatComparator
 *
 * Encapsulates the tolerance-aware float comparison logic used by ParityDiffer.
 * The tolerance value is read from config('parity.float_tolerance').
 *
 * Strict mode (--strict flag on the command) sets tolerance to 0.0,
 * forcing exact binary equality after json_decode.
 */
final class FloatComparator
{
    private float $tolerance;

    public function __construct(float $tolerance)
    {
        $this->tolerance = $tolerance;
    }

    /**
     * Returns true when the two float values are considered equal within tolerance.
     */
    public function equal(float $a, float $b): bool
    {
        if ($this->tolerance === 0.0) {
            // Strict mode: exact comparison
            return $a === $b;
        }

        return abs($a - $b) <= $this->tolerance;
    }

    /**
     * Formats the delta for reporting.
     */
    public function delta(float $a, float $b): string
    {
        $delta = abs($a - $b);
        return sprintf('Δ=%.10f (tolerance=%.6f)', $delta, $this->tolerance);
    }

    public function getTolerance(): float
    {
        return $this->tolerance;
    }
}
