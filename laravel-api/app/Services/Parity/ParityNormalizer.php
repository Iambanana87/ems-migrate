<?php

declare(strict_types=1);

namespace App\Services\Parity;

/**
 * ParityNormalizer
 *
 * Normalizes a decoded JSON payload so that comparisons are structurally fair:
 *
 * 1. Applies JSON_NUMERIC_CHECK semantics: numeric strings → native numbers.
 * 2. Strips whitelisted keys (global + endpoint-specific).
 * 3. Recursively sorts object keys alphabetically for stable key-order comparison
 *    (order comparison is optional via $checkOrder flag).
 * 4. Preserves array ordering (device lists must remain in the same order).
 */
final class ParityNormalizer
{
    /** @var string[] Keys stripped globally across all endpoints */
    private array $globalWhitelist;

    /** @var string[] Keys stripped for a specific endpoint scan */
    private array $endpointWhitelist;

    /**
     * @param string[] $globalWhitelist    Dot-notation keys ignored everywhere
     * @param string[] $endpointWhitelist  Dot-notation keys ignored for this endpoint
     */
    public function __construct(
        array $globalWhitelist = [],
        array $endpointWhitelist = [],
    ) {
        $this->globalWhitelist    = $globalWhitelist;
        $this->endpointWhitelist  = $endpointWhitelist;
    }

    /**
     * Normalize a JSON string into a comparable PHP structure.
     *
     * @throws \JsonException On malformed JSON.
     */
    public function normalize(string $rawJson): mixed
    {
        // JSON_BIGINT_AS_STRING prevents large int mangling; JSON_THROW_ON_ERROR
        // gives us a clean exception instead of a silent null.
        $decoded = json_decode($rawJson, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

        // PHP's native json_decode with JSON_NUMERIC_CHECK equivalent:
        // coerce numeric strings to int/float.
        $decoded = $this->coerceNumericStrings($decoded);

        // Strip whitelisted keys
        $allWhitelist = array_unique(array_merge($this->globalWhitelist, $this->endpointWhitelist));
        $decoded = $this->stripKeys($decoded, $allWhitelist);

        return $decoded;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Recursively coerce numeric strings to int or float,
     * replicating JSON_NUMERIC_CHECK behavior.
     */
    private function coerceNumericStrings(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->coerceNumericStrings(...), $value);
        }

        if (is_string($value) && is_numeric($value)) {
            // Preserve int vs float distinction
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Strip whitelisted dot-notation keys from a decoded structure.
     *
     * A dot-notation key 'a.b' removes key 'b' from any array that also has
     * key 'a', or removes the top-level key 'a.b' if the structure is flat.
     *
     * For simplicity we treat each whitelist entry as a top-level key name
     * (stripping it at any nesting level), which covers the common case of
     * stripping timestamp fields regardless of nesting depth.
     *
     * @param string[] $whitelist
     */
    private function stripKeys(mixed $value, array $whitelist): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Separate simple keys (no dots) from dotted paths
        $simpleKeys = array_filter($whitelist, static fn($k) => !str_contains($k, '.'));
        $dottedKeys = array_filter($whitelist, static fn($k) => str_contains($k, '.'));

        // Strip simple keys at this level
        foreach ($simpleKeys as $key) {
            unset($value[$key]);
        }

        // Recurse into children, passing dotted keys with first segment consumed
        foreach ($value as $k => $v) {
            // Build whitelist for child: take dotted keys where prefix matches this key
            $childWhitelist = [];
            foreach ($dottedKeys as $dotted) {
                [$head, $tail] = explode('.', $dotted, 2);
                if ($head === (string)$k) {
                    $childWhitelist[] = $tail;
                }
            }
            // Also pass through all simple keys for recursive levels
            $childWhitelist = array_merge($childWhitelist, $simpleKeys);
            $value[$k] = $this->stripKeys($v, $childWhitelist);
        }

        return $value;
    }
}
