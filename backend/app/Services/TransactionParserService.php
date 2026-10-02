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
        'modal', 'kulakan', 'kulak', 'bahan baku', 'hpp',
    ];

    /** Keywords that indicate an INCOME transaction */
    private const INCOME_KEYWORDS = [
        'masuk', 'terima', 'dapat', 'dapet', 'pemasukan', 'income',
        'honor', 'trf masuk', 'transfer masuk', 'nerima',
        'jual', 'penjualan', 'omzet',
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

        // Extract wallet tag if present (e.g., "via dana", "ke bca")
        $walletTag = null;
        if (preg_match('/\b(?:via|pakai|menggunakan|ke)\s+([a-zA-Z0-9_-]+)\b/i', $message, $wMatch)) {
            $walletTag = trim($wMatch[1]);
            $message = trim(preg_replace('/\b(?:via|pakai|menggunakan|ke)\s+[a-zA-Z0-9_-]+\b/i', '', $message));
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
            'wallet_tag'    => $walletTag,
        ];
    }

    /**
     * Parse add wallet command: "tambah dompet <nama> [saldo <nominal>]"
     *
     * @return array{name: string, initial_balance: int}|null
     */
    public function parseAddWallet(string $message): ?array
    {
        if (preg_match('/^(?:tambah|buat)\s+dompet\s+([a-zA-Z0-9_\-\s]+?)(?:\s+saldo\s+([0-9.,]+(?:\s*(?:jt|juta|rb|ribu|k))?))?$/i', trim($message), $matches)) {
            $name = trim($matches[1]);
            $initialBalance = 0;
            if (!empty($matches[2])) {
                $initialBalance = $this->extractAmount($matches[2]) ?? 0;
            }
            if (!empty($name)) {
                return [
                    'name'            => $name,
                    'initial_balance' => $initialBalance,
                ];
            }
        }
        return null;
    }

    /**
     * Parse inter-wallet transfer command:
     * "transfer <nominal> dari <asal> ke <tujuan>"
     *
     * @return array{amount: int, from_wallet: string, to_wallet: string}|null
     */
    public function parseTransfer(string $message): ?array
    {
        if (preg_match('/^(?:transfer|trf|tarik tunai)\s+([0-9.,]+(?:\s*(?:jt|juta|rb|ribu|k))?)\s+dari\s+([a-zA-Z0-9_\-\s]+?)\s+ke\s+([a-zA-Z0-9_\-\s]+)$/i', trim($message), $matches)) {
            $amount = $this->extractAmount($matches[1]);
            $fromWallet = trim($matches[2]);
            $toWallet = trim($matches[3]);

            if ($amount && $amount > 0 && !empty($fromWallet) && !empty($toWallet)) {
                return [
                    'amount'      => $amount,
                    'from_wallet' => $fromWallet,
                    'to_wallet'   => $toWallet,
                ];
            }
        }
        return null;
    }

    /**
     * Parse recurring schedule command:
     * "langganan <nama> <nominal> setiap tanggal <tgl> [via <wallet>]"
     *
     * @return array{description: string, amount: int, day_of_month: int, wallet_name: string|null, type: string}|null
     */
    public function parseRecurringSchedule(string $message): ?array
    {
        if (preg_match('/^(?:langganan|buat langganan)\s+(.+?)\s+([0-9.,]+(?:\s*(?:jt|juta|rb|ribu|k))?)\s+setiap\s+tanggal\s+(\d{1,2})(?:\s+via\s+([a-zA-Z0-9_\-]+))?$/i', trim($message), $matches)) {
            $name = trim($matches[1]);
            $amount = $this->extractAmount($matches[2]);
            $dayOfMonth = (int) $matches[3];
            $walletName = !empty($matches[4]) ? trim($matches[4]) : null;

            if (!empty($name) && $amount && $amount > 0 && $dayOfMonth >= 1 && $dayOfMonth <= 31) {
                return [
                    'description'  => $name,
                    'amount'       => $amount,
                    'day_of_month' => $dayOfMonth,
                    'wallet_name'  => $walletName,
                    'type'         => 'EXPENSE',
                ];
            }
        }
        return null;
    }

    /**
     * Parse goal creation command:
     * "buat target <nama> <nominal>" or "target <nama> <nominal>"
     *
     * @return array{name: string, target_amount: int}|null
     */
    public function parseCreateGoal(string $message): ?array
    {
        if (preg_match('/^(?:buat\s+target|target)\s+(.+?)\s+([0-9.,]+(?:\s*(?:jt|juta|rb|ribu|k))?)$/i', trim($message), $matches)) {
            $name = trim($matches[1]);
            $amount = $this->extractAmount($matches[2]);
            if (!empty($name) && $amount && $amount > 0) {
                return [
                    'name'          => $name,
                    'target_amount' => $amount,
                ];
            }
        }
        return null;
    }

    /**
     * Parse goal contribution command:
     * "tambah tabungan <nominal> untuk <nama> [via <wallet>]"
     *
     * @return array{amount: int, goal_name: string, wallet_name: string|null}|null
     */
    public function parseGoalContribution(string $message): ?array
    {
        if (preg_match('/^(?:tambah\s+tabungan|tabung)\s+([0-9.,]+(?:\s*(?:jt|juta|rb|ribu|k))?)\s+untuk\s+(.+?)(?:\s+via\s+([a-zA-Z0-9_\-]+))?$/i', trim($message), $matches)) {
            $amount = $this->extractAmount($matches[1]);
            $goalName = trim($matches[2]);
            $walletName = !empty($matches[3]) ? trim($matches[3]) : null;

            if ($amount && $amount > 0 && !empty($goalName)) {
                return [
                    'amount'      => $amount,
                    'goal_name'   => $goalName,
                    'wallet_name' => $walletName,
                ];
            }
        }
        return null;
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
        $cleaned = trim($message);

        // Remove sign prefix
        $cleaned = preg_replace('/^[+-]\s*/', '', $cleaned) ?? $cleaned;

        // Remove expense/income keywords at start (case-insensitive)
        $allKeywords = array_merge(self::EXPENSE_KEYWORDS, self::INCOME_KEYWORDS);
        usort($allKeywords, fn($a, $b) => strlen($b) - strlen($a)); // longest first
        foreach ($allKeywords as $kw) {
            if (preg_match('/^' . preg_quote($kw, '/') . '(?:\s+|$)/i', $cleaned, $m)) {
                $cleaned = substr($cleaned, strlen($m[0]));
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
     * Public accessor to normalize and extract an amount from a string.
     */
    public function parseAmount(string $raw): ?int
    {
        return $this->extractAmount($raw);
    }

    /**
     * Parse budget set command: "budget <category> <amount>" or "anggaran <category> <amount>"
     *
     * @return array{category: string, amount: int}|null
     */
    public function parseBudgetSet(string $message): ?array
    {
        if (preg_match('/^(?:budget|anggaran)\s+(.+?)\s+([0-9.,]+(?:\s*(?:jt|juta|rb|ribu|k))?)$/i', trim($message), $matches)) {
            $catName = trim($matches[1]);
            $amount = $this->extractAmount($matches[2]);
            if ($amount && $amount > 0 && !empty($catName)) {
                return [
                    'category' => $catName,
                    'amount'   => $amount,
                ];
            }
        }
        return null;
    }

    /**
     * Check if the message looks like a bot command rather than a financial transaction.
     * Commands should NOT be parsed as default EXPENSE transactions.
     */
    public function isCommandMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        $commandPatterns = [
            // Saldo & Dompet
            '/^saldo$/i',
            '/^dompet/i',
            '/^(tambah|buat) dompet/i',
            '/^(daftar|list) dompet/i',

            // Transfer antar dompet
            '/^transfer\s+/i',

            // Budget
            '/^(budget|anggaran)/i',
            '/^(cek|status|list) budget/i',

            // Recurring / Langganan
            '/^(langganan|daftar langganan|hapus langganan|buat langganan)/i',

            // Goals / Target
            '/^(buat target|target|tambah tabungan|daftar target)/i',

            // Insights & Inquiries
            '/^bulan ini boros/i',
            '/^evaluasi pengeluaran/i',

            // Freelancer & UMKM
            '/^omzet/i',
            '/^laba/i',
            '/^rekap (freelance|bisnis|proyek)/i',

            // Notification preferences
            '/^(aktifkan|matikan) rekap harian/i',

            // Rekap / laporan
            '/^rekap/i',
            '/^laporan/i',
            '/^export/i',

            // Transaksi
            '/^pengeluaran/i',
            '/^pemasukan/i',
            '/^ubah transaksi/i',
            '/^hapus transaksi/i',
            '/^batal$/i',

            // Bantuan
            '/^bantuan$/i',
            '/^help$/i',
            '/^menu$/i',
            '/^start$/i',
            '/^hi$/i',
            '/^halo$/i',

            // Kategori
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

    public function isCommand(string $message): bool
    {
        return $this->isCommandMessage($message);
    }
}
