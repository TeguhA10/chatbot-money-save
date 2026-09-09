<?php

namespace App\Services;

/**
 * TransactionParserService
 *
 * High-performance deterministic regex parser for Indonesian financial transaction messages.
 *
 * Spec: FR-003, FR-004, FR-014
 * Target: <50ms per parse (zero external API calls, no ML)
 *
 * Supported input formats:
 *   - "keluar 25000 makan siang"         → EXPENSE 25000
 *   - "beli nasi padang 25.000"          → EXPENSE 25000
 *   - "-15k bensin"                      → EXPENSE 15000
 *   - "masuk 1.5jt gaji"                 → INCOME  1500000
 *   - "+250000 bonus proyek"             → INCOME  250000
 *   - "terima 2,5jt freelance"           → INCOME  2500000
 *   - "bayar listrik 150rb"              → EXPENSE 150000
 */
class TransactionParserService
{
    // ---------------------------------------------------------------------------
    // Expense / Income keywords
    // ---------------------------------------------------------------------------

    /** Keywords that indicate an EXPENSE transaction */
    private const EXPENSE_KEYWORDS = [
        'keluar', 'beli', 'bayar', 'pengeluaran', 'spend', 'habis',
        'belanja', 'jajan', 'bayarin', 'transfer keluar', 'kirim',
    ];

    /** Keywords that indicate an INCOME transaction */
    private const INCOME_KEYWORDS = [
        'masuk', 'terima', 'dapat', 'dapet', 'pemasukan', 'income',
        'honor', 'trf masuk', 'transfer masuk', 'nerima',
    ];

    // ---------------------------------------------------------------------------
    // Category hint keyword maps (best-effort, not required)
    // ---------------------------------------------------------------------------

    private const CATEGORY_HINTS = [
        'makan'      => 'makan',
        'minum'      => 'makan',
        'kopi'       => 'makan',
        'nasi'       => 'makan',
        'lunch'      => 'makan',
        'dinner'     => 'makan',
        'sarapan'    => 'makan',
        'siang'      => 'makan',
        'bensin'     => 'transport',
        'parkir'     => 'transport',
        'ojek'       => 'transport',
        'grab'       => 'transport',
        'gojek'      => 'transport',
        'taxi'       => 'transport',
        'bus'        => 'transport',
        'kereta'     => 'transport',
        'listrik'    => 'tagihan',
        'air'        => 'tagihan',
        'internet'   => 'tagihan',
        'pulsa'      => 'tagihan',
        'token'      => 'tagihan',
        'film'       => 'hiburan',
        'netflix'    => 'hiburan',
        'game'       => 'hiburan',
        'baju'       => 'belanja',
        'celana'     => 'belanja',
        'sepatu'     => 'belanja',
        'obat'       => 'kesehatan',
        'dokter'     => 'kesehatan',
        'klinik'     => 'kesehatan',
        'gaji'       => 'gaji',
        'salary'     => 'gaji',
        'bonus'      => 'gaji',
        'freelance'  => 'bisnis',
        'proyek'     => 'bisnis',
    ];

    // ---------------------------------------------------------------------------
    // Amount normalization regex tokens
    // ---------------------------------------------------------------------------

    /**
     * Master regex pattern that captures:
     * Group 1 (optional): sign prefix (+ or -)
     * Group 2: numeric amount (may have dots or commas as separators)
     * Group 3 (optional): multiplier abbreviation (k, rb, ribu, jt, juta)
     */
    private const AMOUNT_REGEX = '/([+-])?(\d+(?:[.,]\d+)?)(juta|ribu|jt|rb|k)?/i';

    /**
     * Parse a raw WhatsApp message into a structured transaction DTO.
     *
     * Returns null if the message cannot be recognized as a financial transaction
     * (e.g. commands like "saldo", "rekap", or messages without an amount).
     *
     * @param  string $rawMessage The raw WhatsApp text message
     * @return array{type: string, amount: int, description: string, category_hint: string|null}|null
     */
    public function parse(string $rawMessage): ?array
    {
        $message = trim($rawMessage);

        if (empty($message)) {
            return null;
        }

        // 1. Detect amount
        $amount = $this->extractAmount($message);

        if ($amount === null || $amount <= 0) {
            return null;
        }

        // 2. Detect transaction direction
        $type = $this->detectType($message);

        // Default to EXPENSE when amount found but no direction keyword (common shorthand)
        if ($type === null) {
            $type = 'EXPENSE';
        }

        // But if it looks like a command keyword, reject entirely
        if ($this->isCommandMessage($message)) {
            return null;
        }

        // 3. Extract description (words remaining after removing direction keyword + amount)
        $description = $this->extractDescription($message);

        // 4. Detect optional category hint
        $categoryHint = $this->detectCategoryHint($message);

        return [
            'type'          => $type,
            'amount'        => $amount,
            'description'   => $description,
            'category_hint' => $categoryHint,
        ];
    }

    // ---------------------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------------------

    /**
     * Extract and normalize amount from the message to integer Rupiah.
     *
     * Supported formats:
     *   25000, 25.000, 25,000  → 25000
     *   25k, 25rb, 25ribu      → 25000
     *   1.5jt, 1,5juta         → 1500000
     *   2jt                    → 2000000
     */
    private function extractAmount(string $message): ?int
    {
        if (!preg_match(self::AMOUNT_REGEX, $message, $matches)) {
            return null;
        }

        // $matches[2] = digits (possibly with . or , separators)
        // $matches[3] = multiplier suffix (optional)
        $rawNumber  = $matches[2] ?? '';
        $multiplier = strtolower($matches[3] ?? '');

        if (empty($rawNumber)) {
            return null;
        }

        // Normalize: remove thousand separators (dot or comma when followed by 3 digits)
        // Handle decimal comma (e.g. 1,5) vs thousand comma (e.g. 1,500)
        // Strategy: if there are digits after separator AND length <= 2 → decimal
        //           if 3 digits after separator → thousand separator
        $normalized = $this->normalizeNumber($rawNumber);

        if ($normalized === null) {
            return null;
        }

        // Apply multiplier
        $amount = match (true) {
            in_array($multiplier, ['k', 'rb', 'ribu']) => (int) round($normalized * 1000),
            in_array($multiplier, ['jt', 'juta'])      => (int) round($normalized * 1000000),
            default                                     => (int) $normalized,
        };

        return $amount > 0 ? $amount : null;
    }

    /**
     * Normalize raw number string to a float value.
     * Handles both:
     *   - Thousand separators: "25.000" → 25000, "1.500.000" → 1500000
     *   - Decimal notations: "1.5" → 1.5, "1,5" → 1.5
     */
    private function normalizeNumber(string $raw): ?float
    {
        // Count separators
        $dotCount   = substr_count($raw, '.');
        $commaCount = substr_count($raw, ',');

        if ($dotCount === 0 && $commaCount === 0) {
            // Plain integer
            return (float) $raw;
        }

        // Check if it's a decimal (dot/comma followed by 1-2 digits at end)
        if (preg_match('/^(\d+)[.,](\d{1,2})$/', $raw, $m)) {
            // e.g. "1.5" or "1,5" → decimal
            return (float) ($m[1] . '.' . $m[2]);
        }

        // Remove thousand separators (dots or commas before 3-digit groups)
        $clean = preg_replace('/[.,](\d{3})/', '$1', $raw);
        $clean = str_replace(['.', ','], '', $clean ?? $raw);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Detect whether the transaction is EXPENSE or INCOME based on:
     * 1. Explicit +/- sign prefix
     * 2. Indonesian directional keywords
     */
    private function detectType(string $message): ?string
    {
        $lowerMessage = strtolower($message);

        // Explicit sign prefix takes priority
        if (preg_match('/^[+-]/', $message)) {
            return str_starts_with($message, '+') ? 'INCOME' : 'EXPENSE';
        }

        // Check income keywords FIRST (higher specificity words like 'dapat' must win)
        foreach (self::INCOME_KEYWORDS as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'INCOME';
            }
        }

        // Check expense keywords
        foreach (self::EXPENSE_KEYWORDS as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'EXPENSE';
            }
        }

        // Cannot determine direction — ambiguous message
        return null;
    }

    /**
     * Extract description by removing direction keywords, amount tokens, and stop words.
     */
    private function extractDescription(string $message): string
    {
        $cleaned = strtolower(trim($message));

        // Remove sign prefix
        $cleaned = preg_replace('/^[+-]\s*/', '', $cleaned) ?? $cleaned;

        // Remove expense/income keywords at start
        $allKeywords = array_merge(self::EXPENSE_KEYWORDS, self::INCOME_KEYWORDS);
        usort($allKeywords, fn($a, $b) => strlen($b) - strlen($a)); // longest first
        foreach ($allKeywords as $kw) {
            if (str_starts_with($cleaned, $kw)) {
                $cleaned = ltrim(substr($cleaned, strlen($kw)));
                break;
            }
        }

        // Remove amount token (number + optional suffix)
        $cleaned = preg_replace('/\d+(?:[.,]\d+)?\s*(?:k|rb|ribu|jt|juta)?\s*/i', '', $cleaned) ?? $cleaned;

        // Trim and normalize whitespace
        $description = trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);

        return $description;
    }

    /**
     * Detect optional category hint from message keywords (best-effort, non-blocking).
     */
    private function detectCategoryHint(string $message): ?string
    {
        $lowerMessage = strtolower($message);

        foreach (self::CATEGORY_HINTS as $keyword => $hint) {
            if (str_contains($lowerMessage, $keyword)) {
                return $hint;
            }
        }

        return null;
    }

    /**
     * Check if the message looks like a bot command rather than a financial transaction.
     * Commands should NOT be parsed as default EXPENSE transactions.
     */
    private function isCommandMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        $commandPatterns = [
            '/^saldo$/i',
            '/^rekap/i',
            '/^laporan/i',
            '/^export/i',
            '/^batal$/i',
            '/^hapus/i',
            '/^bantuan$/i',
            '/^help$/i',
            '/^menu$/i',
            '/^start$/i',
            '/^hi$/i',
            '/^halo$/i',
            '/^kategori/i',
            '/^tambah kategori/i',
            '/^daftar kategori/i',
        ];

        foreach ($commandPatterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
    }
}
