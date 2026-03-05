<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * EnsureNumericJson
 *
 * WHY THIS EXISTS
 * ───────────────
 * Legacy api.php used json_encode($results, JSON_NUMERIC_CHECK) globally.
 * That flag coerces numeric strings (e.g. "85", "1.5") to native int/float
 * before serialization. Laravel's response()->json() does NOT apply this flag
 * by default, producing type drift that breaks Vue numeric comparisons and
 * parity:scan certification.
 *
 * WHY setEncodingOptions() ALONE FAILS (Laravel 12)
 * ───────────────────────────────────────────────────
 * JsonResponse serializes $this->data to a JSON string at construction time
 * or on first access. By the time middleware runs, $response->getContent()
 * already contains the encoded string. Calling setEncodingOptions() changes
 * the flag stored on the object but does NOT retroactively re-encode the
 * body that is already sitting in $this->content.
 *
 * Calling setData() after setEncodingOptions() re-encodes from $this->data,
 * but ONLY if $this->data was set — controllers that called setContent()
 * directly (including the exception handler) will have a null/missing
 * $this->data property, causing setData(null) to produce "null" as body.
 *
 * THE GUARANTEED PATH (this implementation)
 * ──────────────────────────────────────────
 * 1. Read the raw JSON string from getContent().
 * 2. json_decode it back to a PHP value.
 * 3. json_encode it fresh with JSON_NUMERIC_CHECK.
 * 4. Write the new string back via setContent().
 *
 * setContent() bypasses ALL of JsonResponse's lazy encoding machinery and
 * writes the string directly into Symfony's $this->content property.
 * The response then streams exactly what we put there — no further
 * re-encoding occurs.
 *
 * EDGE CASES HANDLED
 * ──────────────────
 * StreamedResponse     → skipped (body not buffered; cannot intercept)
 * BinaryFileResponse   → skipped (binary content; not JSON)
 * Non-JsonResponse     → skipped (HTML, plain text, redirects)
 * Empty body ("")      → skipped (no content to process)
 * Malformed JSON       → skipped silently (original body preserved, warning logged)
 * Large integers       → json_decode without JSON_BIGINT_AS_STRING: PHP's
 *                        native 64-bit int handles them. Values exceeding
 *                        PHP_INT_MAX become float — same as legacy behavior.
 * HTTP status code     → setContent() never touches status; fully preserved.
 * Headers              → setContent() never touches headers; all preserved.
 * Content-Type         → already "application/json; charset=UTF-8"; unchanged.
 * json_encode failure  → original body preserved, warning logged.
 */
final class EnsureNumericJson
{
    /**
     * Encoding flags applied during forced re-serialization.
     *
     * JSON_NUMERIC_CHECK        : coerce numeric strings → native int/float.
     * JSON_UNESCAPED_UNICODE    : preserve multi-byte characters. Matches the
     *                             JSON_UNESCAPED_UNICODE flag used by some
     *                             legacy actions.
     * JSON_UNESCAPED_SLASHES   : avoids unnecessary \/ escaping.
     * JSON_PRESERVE_ZERO_FRACTION : ensures 1.0 stays 1.0 (not 1), preserving
     *                             float-vs-int distinction where it matters.
     */
    private const ENCODE_FLAGS =
        JSON_NUMERIC_CHECK
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION;

    public function handle(Request $request, Closure $next): mixed
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // ── Guard 1: StreamedResponse ──────────────────────────────────────
        // getContent() returns false for streamed responses. We cannot buffer
        // the body, so skip entirely. This guard MUST precede the JsonResponse
        // check because StreamedJsonResponse extends StreamedResponse, not
        // JsonResponse — but future subclasses might differ.
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        // ── Guard 2: BinaryFileResponse ────────────────────────────────────
        // Binary file downloads — not JSON, skip unconditionally.
        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        // ── Guard 3: Non-JSON responses ────────────────────────────────────
        // Only intercept JsonResponse. Plain Response (HTML, text), redirects,
        // and other Symfony response types are left untouched.
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        // ── Step 1: Read raw serialized body ──────────────────────────────
        // getContent() on a JsonResponse always returns a string (it was
        // asserted non-streamed above). The string is whatever was last
        // written to $this->content — either by setData(), setContent(), or
        // the constructor. This is the ONLY reliable source of truth.
        $original = $response->getContent();

        // Guard: empty or falsy body — nothing to do.
        if ($original === false || $original === '') {
            return $response;
        }

        // ── Step 2: Decode the JSON string back to PHP ────────────────────
        // Flags: none (0).
        //   - Do NOT use JSON_BIGINT_AS_STRING: that would represent large
        //     integers as PHP strings, and JSON_NUMERIC_CHECK in Step 3 would
        //     then coerce them back, potentially losing precision.
        //   - PHP's native json_decode on 64-bit uses PHP_INT (64-bit signed)
        //     which safely holds up to 9,223,372,036,854,775,807. Values above
        //     that become float — identical to legacy PHP behavior.
        $decoded = json_decode($original, associative: true, depth: 512, flags: 0);

        // Distinguish between:
        //   (a) Malformed JSON → json_last_error() !== JSON_ERROR_NONE
        //   (b) Valid literal "null" JSON value → json_last_error() === JSON_ERROR_NONE
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // Malformed body — preserve as-is. Logging ensures visibility
            // without crashing the response pipeline.
            \Illuminate\Support\Facades\Log::warning(
                '[EnsureNumericJson] Malformed JSON in response — re-encoding skipped.',
                [
                    'url'   => $request->fullUrl(),
                    'error' => json_last_error_msg(),
                ]
            );
            return $response;
        }

        // ── Step 3: Re-encode with JSON_NUMERIC_CHECK ─────────────────────
        // This is the authoritative encoding step. The PHP value produced in
        // Step 2 is re-serialized with our flag set. Numeric strings that
        // survived as strings in the original response (because response()->json()
        // did not apply JSON_NUMERIC_CHECK) are now coerced to int/float here.
        $reencoded = json_encode($decoded, self::ENCODE_FLAGS);

        if ($reencoded === false) {
            // json_encode can fail on invalid UTF-8 sequences or recursion
            // depth exceeded. Preserve original body rather than send empty.
            \Illuminate\Support\Facades\Log::warning(
                '[EnsureNumericJson] json_encode failed — re-encoding skipped.',
                [
                    'url'   => $request->fullUrl(),
                    'error' => json_last_error_msg(),
                ]
            );
            return $response;
        }

        // ── Step 4: Force-write the corrected body ────────────────────────
        // setContent() writes directly into Symfony Response's $this->content.
        // It bypasses JsonResponse's $this->data and setEncodingOptions() path
        // entirely. After this call:
        //   - $response->getContent() === $reencoded        ✓
        //   - HTTP status code unchanged                     ✓
        //   - All headers unchanged                          ✓
        //   - Content-Type stays application/json            ✓
        //   - Content-Length is recalculated by setContent() ✓
        $response->setContent($reencoded);

        return $response;
    }
}
