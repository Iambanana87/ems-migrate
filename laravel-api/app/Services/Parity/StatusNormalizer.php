<?php

declare(strict_types=1);

namespace App\Services\Parity;

/**
 * StatusNormalizer
 *
 * Enforces the uppercase status string contract defined in CONTRACT.md v1.0.
 *
 * Contract:
 *   DISCONNECTED — device has not sent data within the heartbeat window
 *   BREACHED     — device output has exceeded a threshold breach condition
 *   NORMAL       — device is connected and operating within all thresholds
 *
 * USAGE:
 *   // In any controller response assembly loop:
 *   $row['status'] = StatusNormalizer::normalize($row['status'] ?? '');
 *
 * RULES:
 *   - Applied at the RESPONSE TRANSFORMATION LAYER only.
 *   - No DB writes. No business logic mutation.
 *   - Unknown values are preserved as-is to avoid silent data loss.
 *   - Input is trimmed and uppercased before lookup.
 *
 * PHASE A: Static normalization map covers all known variants
 * observed in legacy api.php output during Phase B parity scan.
 */
final class StatusNormalizer
{
    /**
     * Map of all known casing variants → canonical uppercase contract value.
     *
     * DO NOT add new logical states here without a CONTRACT.md amendment.
     */
    private const CANONICAL_MAP = [
        // DISCONNECTED variants
        'disconnected'      => 'DISCONNECTED',
        'Disconnected'      => 'DISCONNECTED',
        'DISCONNECTED'      => 'DISCONNECTED',

        // BREACHED variants
        'breached'          => 'BREACHED',
        'Breached'          => 'BREACHED',
        'BREACHED'          => 'BREACHED',

        // NORMAL variants (legacy also sometimes used 'connected' implicitly)
        'normal'            => 'NORMAL',
        'Normal'            => 'NORMAL',
        'NORMAL'            => 'NORMAL',
        'connected'         => 'NORMAL',
        'Connected'         => 'NORMAL',
        'CONNECTED'         => 'NORMAL',
        'ok'                => 'NORMAL',
        'OK'                => 'NORMAL',
    ];

    /**
     * Normalize a raw status string to its canonical uppercase contract value.
     *
     * @param  string $status  Raw status string from DB or legacy logic
     * @return string          Canonical uppercase contract value, or the
     *                         trimmed+uppercased original if not in the map
     *                         (preserves forward-compatibility for new states).
     */
    public static function normalize(string $status): string
    {
        $trimmed = trim($status);

        // Exact-match lookup first (avoids aggressive strtoupper coercion)
        if (isset(self::CANONICAL_MAP[$trimmed])) {
            return self::CANONICAL_MAP[$trimmed];
        }

        // Case-insensitive fallback: if the trimmed upper version is a
        // canonical value, return that. Otherwise return as-is to avoid
        // silent data loss for genuinely unknown states.
        $upper = strtoupper($trimmed);
        return in_array($upper, ['DISCONNECTED', 'BREACHED', 'NORMAL'], true)
            ? $upper
            : $trimmed; // Unknown status: preserve for logging/investigation
    }

    /**
     * Normalize status on a full response array in-place.
     * Applies to any key named 'status' that is a string, recursively.
     *
     * IMPORTANT: This also normalizes the top-level error envelope 'status'
     * key (e.g. 'error', 'ok'). To avoid that, use normalize() per-key
     * instead of this bulk method.
     *
     * @param  array<string,mixed> $row  Single device row from a response list
     * @return array<string,mixed>
     */
    public static function normalizeDeviceRow(array $row): array
    {
        if (isset($row['status']) && is_string($row['status'])) {
            $row['status'] = self::normalize($row['status']);
        }

        return $row;
    }
}
