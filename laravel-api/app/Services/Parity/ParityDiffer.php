<?php

declare(strict_types=1);

namespace App\Services\Parity;

/**
 * ParityDiffer
 *
 * Performs a deep recursive diff of two normalized PHP structures produced
 * by ParityNormalizer. Returns a flat list of DriftItem arrays describing
 * every divergence found.
 *
 * DriftItem shape:
 * [
 *   'path'     => string,       // dot-notation path to the differing value
 *   'type'     => string,       // one of: missing_key|extra_key|type_mismatch|value_mismatch|float_drift|null_vs_absent
 *   'legacy'   => mixed,        // legacy value (or ABSENT sentinel)
 *   'laravel'  => mixed,        // laravel value (or ABSENT sentinel)
 *   'detail'   => string,       // human-readable explanation
 * ]
 */
final class ParityDiffer
{
    private const ABSENT = '__ABSENT__';

    private FloatComparator $floatComparator;
    private bool $checkKeyOrder;

    public function __construct(
        FloatComparator $floatComparator,
        bool $checkKeyOrder = false,
    ) {
        $this->floatComparator = $floatComparator;
        $this->checkKeyOrder   = $checkKeyOrder;
    }

    /**
     * Compare two normalized structures and return all drift items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function diff(mixed $legacy, mixed $laravel, string $path = ''): array
    {
        $drifts = [];
        $this->compare($legacy, $laravel, $path, $drifts);
        return $drifts;
    }

    // -----------------------------------------------------------------------
    // Core recursive comparison
    // -----------------------------------------------------------------------

    private function compare(mixed $legacy, mixed $laravel, string $path, array &$drifts): void
    {
        // Both null — identical
        if ($legacy === null && $laravel === null) {
            return;
        }

        // Type check (handles null vs non-null, array vs scalar, etc.)
        $legacyType  = $this->typeOf($legacy);
        $laravelType = $this->typeOf($laravel);

        if ($legacyType !== $laravelType) {
            // Special case: null vs absent key is reported differently
            // (detected at caller level when key is missing)
            $drifts[] = $this->item($path, 'type_mismatch', $legacy, $laravel,
                "Type changed from {$legacyType} to {$laravelType}");
            return;
        }

        if (is_array($legacy)) {
            $this->compareArrays($legacy, $laravel, $path, $drifts);
            return;
        }

        // Scalar comparison
        $this->compareScalars($legacy, $laravel, $path, $drifts);
    }

    private function compareArrays(array $legacy, array $laravel, string $path, array &$drifts): void
    {
        $legacyIsIndexed  = array_is_list($legacy);
        $laravelIsIndexed = array_is_list($laravel);

        if ($legacyIsIndexed && $laravelIsIndexed) {
            $this->compareIndexedArrays($legacy, $laravel, $path, $drifts);
            return;
        }

        // Associative / object comparison
        $this->compareObjects($legacy, $laravel, $path, $drifts);
    }

    private function compareIndexedArrays(array $legacy, array $laravel, string $path, array &$drifts): void
    {
        $legacyCount  = count($legacy);
        $laravelCount = count($laravel);

        if ($legacyCount !== $laravelCount) {
            $drifts[] = $this->item($path, 'value_mismatch', $legacyCount, $laravelCount,
                "Array length mismatch: legacy={$legacyCount} laravel={$laravelCount}");
            // Still diff the overlapping items
        }

        $limit = min($legacyCount, $laravelCount);
        for ($i = 0; $i < $limit; $i++) {
            $childPath = $path !== '' ? "{$path}[{$i}]" : "[{$i}]";
            $this->compare($legacy[$i], $laravel[$i], $childPath, $drifts);
        }

        // Extra items
        for ($i = $limit; $i < $laravelCount; $i++) {
            $childPath = $path !== '' ? "{$path}[{$i}]" : "[{$i}]";
            $drifts[] = $this->item($childPath, 'extra_key', self::ABSENT, $laravel[$i],
                'Extra item present in Laravel response');
        }
        for ($i = $limit; $i < $legacyCount; $i++) {
            $childPath = $path !== '' ? "{$path}[{$i}]" : "[{$i}]";
            $drifts[] = $this->item($childPath, 'missing_key', $legacy[$i], self::ABSENT,
                'Item present in legacy but absent in Laravel response');
        }
    }

    private function compareObjects(array $legacy, array $laravel, string $path, array &$drifts): void
    {
        $legacyKeys  = array_keys($legacy);
        $laravelKeys = array_keys($laravel);

        // Missing keys (in legacy but not Laravel)
        $missing = array_diff($legacyKeys, $laravelKeys);
        foreach ($missing as $key) {
            $childPath = $this->childPath($path, $key);
            // Distinguish null vs truly absent
            if ($legacy[$key] === null) {
                $drifts[] = $this->item($childPath, 'null_vs_absent', null, self::ABSENT,
                    'Key has null value in legacy but is completely absent in Laravel');
            } else {
                $drifts[] = $this->item($childPath, 'missing_key', $legacy[$key], self::ABSENT,
                    'Key present in legacy but absent in Laravel');
            }
        }

        // Extra keys (in Laravel but not legacy)
        $extra = array_diff($laravelKeys, $legacyKeys);
        foreach ($extra as $key) {
            $childPath = $this->childPath($path, $key);
            if ($laravel[$key] === null) {
                $drifts[] = $this->item($childPath, 'null_vs_absent', self::ABSENT, null,
                    'Key is completely absent in legacy but has null value in Laravel');
            } else {
                $drifts[] = $this->item($childPath, 'extra_key', self::ABSENT, $laravel[$key],
                    'Key absent in legacy but present in Laravel');
            }
        }

        // Key order check (optional)
        if ($this->checkKeyOrder) {
            $commonLegacyOrder  = array_values(array_intersect($legacyKeys, $laravelKeys));
            $commonLaravelOrder = array_values(array_intersect($laravelKeys, $legacyKeys));
            if ($commonLegacyOrder !== $commonLaravelOrder) {
                $drifts[] = $this->item($path, 'key_order',
                    implode(', ', $commonLegacyOrder),
                    implode(', ', $commonLaravelOrder),
                    'Key ordering differs for common keys');
            }
        }

        // Recurse into common keys
        $common = array_intersect($legacyKeys, $laravelKeys);
        foreach ($common as $key) {
            $childPath = $this->childPath($path, $key);
            $this->compare($legacy[$key], $laravel[$key], $childPath, $drifts);
        }
    }

    private function compareScalars(mixed $legacy, mixed $laravel, string $path, array &$drifts): void
    {
        // Float comparison with tolerance
        if (is_float($legacy) || is_float($laravel)) {
            $a = (float) $legacy;
            $b = (float) $laravel;
            if (!$this->floatComparator->equal($a, $b)) {
                $drifts[] = $this->item($path, 'float_drift', $legacy, $laravel,
                    'Float value outside tolerance: ' . $this->floatComparator->delta($a, $b));
            }
            return;
        }

        // Integer comparison
        if (is_int($legacy) && is_int($laravel)) {
            if ($legacy !== $laravel) {
                $drifts[] = $this->item($path, 'value_mismatch', $legacy, $laravel,
                    "Integer mismatch: legacy={$legacy} laravel={$laravel}");
            }
            return;
        }

        // String / bool / null comparison (after type check passed above)
        if ($legacy !== $laravel) {
            $drifts[] = $this->item($path, 'value_mismatch', $legacy, $laravel,
                'Value mismatch');
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function typeOf(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool',
            is_int($value)  => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'list',
            is_array($value) => 'object',
            default => gettype($value),
        };
    }

    private function childPath(string $parent, int|string $key): string
    {
        return $parent !== '' ? "{$parent}.{$key}" : (string)$key;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $path, string $type, mixed $legacy, mixed $laravel, string $detail): array
    {
        return [
            'path'   => $path,
            'type'   => $type,
            'legacy' => $legacy === self::ABSENT ? null : $legacy,
            'laravel'=> $laravel === self::ABSENT ? null : $laravel,
            'absent' => [
                'legacy'  => $legacy === self::ABSENT,
                'laravel' => $laravel === self::ABSENT,
            ],
            'detail' => $detail,
        ];
    }
}
