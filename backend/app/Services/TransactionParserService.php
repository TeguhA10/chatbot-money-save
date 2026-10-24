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
        'kluar', 'keluarr', 'klr', 'out', 'byr', 'byar', 'blanja',
    ];

    /** Keywords that indicate an INCOME transaction */
    private const INCOME_KEYWORDS = [
        'masuk', 'terima', 'dapat', 'dapet', 'pemasukan', 'income',
        'honor', 'trf masuk', 'transfer masuk', 'nerima',
        'jual', 'penjualan', 'omzet',
        'masukk', 'msk', 'in', 'dpt',
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
        $amountInfo = $this->extractAmountDetails($message);

        if ($amountInfo === null || $amountInfo['amount'] <= 0) {
            return null;
        }

        $amount = $amountInfo['amount'];
        $rawMatched = $amountInfo['raw_matched'];

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

        // Extract wallet tag if present (e.g., "via dana", "pake gopay", "bayar pake cash", "ke bca")
        $walletTag = null;
        if (preg_match('/\b(?:via|pakai|pake|bayar\s+pake|bayar\s+pakai|menggunakan|ke)\s+([a-zA-Z0-9_-]+)\b/i', $message, $wMatch)) {
            $walletTag = trim($wMatch[1]);
            $message = trim(preg_replace('/\b(?:via|pakai|pake|bayar\s+pake|bayar\s+pakai|menggunakan|ke)\s+[a-zA-Z0-9_-]+\b/i', '', $message));
        }

        // 3. Extract description (words remaining after removing direction keyword + amount)
        $description = $this->extractDescription($message, $rawMatched);

        return [
            'type'          => $type,
            'amount'        => $amount,
            'description'   => $description,
            'category_hint' => null,
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
        if (preg_match('/^(?:tambah|buat)\s+dompet\s+([a-zA-Z0-9_\-\s]+?)(?:\s+saldo\s+(.+))?$/i', trim($message), $matches)) {
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
        if (preg_match('/^(?:transfer|trf|tarik tunai)\s+(.+?)\s+dari\s+([a-zA-Z0-9_\-\s]+?)\s+ke\s+([a-zA-Z0-9_\-\s]+)$/i', trim($message), $matches)) {
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
        if (preg_match('/^(?:langganan|buat langganan)\s+(.+?)\s+([0-9.,]+(?:\s*[a-zA-Z]+)?)\s+setiap\s+tanggal\s+(\d{1,2})(?:\s+via\s+([a-zA-Z0-9_\-]+))?$/i', trim($message), $matches)) {
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
        if (preg_match('/^(?:buat\s+target|target)\s+(.+?)\s+([0-9.,]+(?:\s*[a-zA-Z]+)?)$/i', trim($message), $matches)) {
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
        if (preg_match('/^(?:tambah\s+tabungan|tabung)\s+(.+?)\s+untuk\s+(.+?)(?:\s+via\s+([a-zA-Z0-9_\-]+))?$/i', trim($message), $matches)) {
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
     * Master method to extract amount and the exact substring matched.
     *
     * Supports:
     * 1. Compound Million + Thousand:
     *    - "6jt850rb", "6jt 850rb", "6juta 850ribu", "6jt850k", "6jt 850k", "1jt 50rb"
     * 2. Compound Million + Shorthand Thousand (without suffix):
     *    - "6jt 850", "6jt850", "1jt 500", "2jt 250", "1jt 50"
     * 3. Compound Thousand + Hundreds:
     *    - "25rb 500", "25k 500", "25rb 500 perak", "15rb 500 rupiah"
     * 4. Single Unit with Multiplier Suffix:
     *    - "6.850jt", "6,850jt", "6.85jt", "6.8jt", "2jt" -> Million
     *    - "1.5m", "1,5milyar" -> Billion
     *    - "1.5k", "2.5rb", "25k", "500rb", "1.500k" -> Thousand
     * 5. Standard Currency Numbers:
     *    - "6.850.000", "1.500.000", "25.000", "25000", "500"
     *    - With "Rp" or "Rp." prefix: "Rp 25.000", "Rp. 6.850.000", "rp 6jt850rb"
     *
     * @return array{amount: int, raw_matched: string}|null
     */
    public function extractAmountDetails(string $message): ?array
    {
        $text = trim($message);

        // 1. Compound: Million + Thousand with suffix (e.g. "6jt850rb", "6jt 850rb", "6juta 850ribu", "6jt 850k")
        if (preg_match('/(?:rp\.?\s*)?(\d+(?:[.,]\d+)?)\s*(jt|juta)\s*(\d+(?:[.,]\d+)?)\s*(rb|ribu|k)\b/i', $text, $m)) {
            $mAmount = $this->parseSingleNumber($m[1], $m[2]);
            $kAmount = $this->parseSingleNumber($m[3], $m[4]);
            $total = $mAmount + $kAmount;
            if ($total > 0) {
                return ['amount' => $total, 'raw_matched' => $m[0]];
            }
        }

        // 2. Compound: Million + Shorthand Thousand (e.g. "6jt 850", "6jt850", "1jt 500", "2jt 50")
        // Disallows units like pcs, lembar, dll.
        if (preg_match('/(?:rp\.?\s*)?(\d+(?:[.,]\d+)?)\s*(jt|juta)\s*(\d{2,3})\b(?!\s*(?:pcs|pc|item|biji|buah|lembar|porsi|ekor|kg|gram|gr|meter|m|hari|bln|bulan|thn|tahun|kali|x)\b)/i', $text, $m)) {
            $mAmount = $this->parseSingleNumber($m[1], $m[2]);
            $kAmount = ((int) $m[3]) * 1000;
            $total = $mAmount + $kAmount;
            if ($total > 0) {
                return ['amount' => $total, 'raw_matched' => $m[0]];
            }
        }

        // 3. Compound: Thousand + Hundreds (e.g. "25rb 500", "25k 500", "25rb 500 perak")
        if (preg_match('/(?:rp\.?\s*)?(\d+(?:[.,]\d+)?)\s*(rb|ribu|k)\s*(\d{1,3})\s*(?:perak|rupiah)?\b(?!\s*(?:pcs|pc|item|biji|buah|lembar|porsi|ekor|kg|gram|gr|meter|m|hari|bln|bulan|thn|tahun|kali|x)\b)/i', $text, $m)) {
            $kAmount = $this->parseSingleNumber($m[1], $m[2]);
            $hAmount = (int) $m[3];
            $total = $kAmount + $hAmount;
            if ($total > 0) {
                return ['amount' => $total, 'raw_matched' => $m[0]];
            }
        }

        // 4. Single with multiplier suffix (e.g. "6.850jt", "6,850jt", "6.85jt", "2jt", "1.5m", "25k", "500rb", "1.500k")
        if (preg_match('/(?:rp\.?\s*)?(\d+(?:[.,]\d+)*)\s*(jt|juta|m|milyar|miliar|rb|ribu|k)\b/i', $text, $m)) {
            $amount = $this->parseSingleNumber($m[1], $m[2]);
            if ($amount > 0) {
                return ['amount' => $amount, 'raw_matched' => $m[0]];
            }
        }

        // 5. Standard currency numbers without suffix:
        // Either numbers formatted with thousand separators ("6.850.000", "25.000") or plain digits ("25000", "500")
        if (preg_match('/(?:rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})+|\d+)\b/i', $text, $m)) {
            $amount = $this->parseSingleNumber($m[1], null);
            if ($amount > 0) {
                return ['amount' => $amount, 'raw_matched' => $m[0]];
            }
        }

        return null;
    }

    /**
     * Extract and normalize amount from the message to integer Rupiah.
     */
    public function extractAmount(string $message): ?int
    {
        $details = $this->extractAmountDetails($message);
        return $details['amount'] ?? null;
    }

    /**
     * Parse and normalize amount from the message to integer Rupiah.
     */
    public function parseAmount(string $message): ?int
    {
        return $this->extractAmount($message);
    }

    /**
     * Parse a single numeric token with an optional multiplier unit.
     */
    private function parseSingleNumber(string $rawNum, ?string $unit): int
    {
        $rawNum = trim($rawNum);
        $unit = $unit !== null ? strtolower(trim($unit)) : null;

        $dotCount = substr_count($rawNum, '.');
        $commaCount = substr_count($rawNum, ',');
        $totalSep = $dotCount + $commaCount;

        if ($unit === 'jt' || $unit === 'juta') {
            // E.g.: "6.850jt", "6,850jt", "6.85jt", "1.5jt", "2jt"
            if ($totalSep === 1) {
                // Single separator: always decimal in million!
                // "6.850" -> 6.85 * 1,000,000 = 6,850,000
                // "6.85"  -> 6.85 * 1,000,000 = 6,850,000
                // "1.500" -> 1.5 * 1,000,000  = 1,500,000
                $decimalStr = str_replace(',', '.', $rawNum);
                return (int) round(((float) $decimalStr) * 1_000_000);
            }
            if ($totalSep > 1) {
                $clean = str_replace(['.', ','], '', $rawNum);
                return (int) round(((float) $clean) * 1_000_000);
            }
            return (int) round(((float) $rawNum) * 1_000_000);
        }

        if (in_array($unit, ['m', 'milyar', 'miliar'], true)) {
            if ($totalSep === 1) {
                $decimalStr = str_replace(',', '.', $rawNum);
                return (int) round(((float) $decimalStr) * 1_000_000_000);
            }
            $clean = str_replace(['.', ','], '', $rawNum);
            return (int) round(((float) $clean) * 1_000_000_000);
        }

        if (in_array($unit, ['rb', 'ribu', 'k'], true)) {
            if ($totalSep === 1) {
                // If separator is followed by 1 or 2 digits -> decimal (e.g. 1.5k = 1500, 2.5rb = 2500)
                if (preg_match('/[.,]\d{1,2}$/', $rawNum)) {
                    $decimalStr = str_replace(',', '.', $rawNum);
                    return (int) round(((float) $decimalStr) * 1_000);
                }
                // If separator followed by 3 digits (e.g. 1.500k -> 1,500,000)
                $clean = str_replace(['.', ','], '', $rawNum);
                return (int) round(((float) $clean) * 1_000);
            }
            if ($totalSep > 1) {
                $clean = str_replace(['.', ','], '', $rawNum);
                return (int) round(((float) $clean) * 1_000);
            }
            return (int) round(((float) $rawNum) * 1_000);
        }

        // Standard number without unit suffix (e.g. "6.850.000", "25.000", "25000", "500")
        $clean = str_replace(['.', ','], '', $rawNum);
        return is_numeric($clean) ? (int) $clean : 0;
    }

    /**
     * Detect whether the transaction is EXPENSE or INCOME based on:
     * 1. Explicit +/- sign prefix
     * 2. Indonesian directional keywords (leading keywords prioritized, with word boundaries)
     */
    private function detectType(string $message): ?string
    {
        $lowerMessage = strtolower(trim($message));

        // Explicit sign prefix takes priority
        if (preg_match('/^[+-]/', $message)) {
            return str_starts_with($message, '+') ? 'INCOME' : 'EXPENSE';
        }

        // Check if message STARTS with an income or expense keyword
        foreach (self::INCOME_KEYWORDS as $keyword) {
            if (preg_match('/^' . preg_quote($keyword, '/') . '\b/i', $lowerMessage)) {
                return 'INCOME';
            }
        }

        foreach (self::EXPENSE_KEYWORDS as $keyword) {
            if (preg_match('/^' . preg_quote($keyword, '/') . '\b/i', $lowerMessage)) {
                return 'EXPENSE';
            }
        }

        // Check income keywords with word boundaries
        foreach (self::INCOME_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $lowerMessage)) {
                return 'INCOME';
            }
        }

        // Check expense keywords with word boundaries
        foreach (self::EXPENSE_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $lowerMessage)) {
                return 'EXPENSE';
            }
        }

        // Cannot determine direction — ambiguous message
        return null;
    }

    /**
     * Extract description by removing direction keywords, amount tokens, and stop words.
     */
    /**
     * Extract description by removing direction keywords, amount tokens, conversational fillers, and stop words.
     */
    private function extractDescription(string $message, ?string $rawMatched = null): string
    {
        $cleaned = trim($message);

        // Remove sign prefix
        $cleaned = preg_replace('/^[+-]\s*/', '', $cleaned) ?? $cleaned;

        // Remove the matched amount token cleanly first
        if (!empty($rawMatched)) {
            $cleaned = preg_replace('/' . preg_quote($rawMatched, '/') . '/i', '', $cleaned, 1) ?? $cleaned;
        } else {
            // Fallback: remove amount pattern
            $cleaned = preg_replace('/\d+(?:[.,]\d+)*\s*(?:k|rb|ribu|jt|juta|m|milyar|miliar)?\s*/i', '', $cleaned) ?? $cleaned;
        }

        // Clean common conversational fillers / prefixes (e.g. "aku baru aja masuk dari", "tadi siang beli", "tolong catat")
        $fillers = [
            'aku baru aja masuk dari',
            'aku baru aja masuk',
            'baru aja masuk dari',
            'baru aja masuk',
            'baru saja masuk dari',
            'baru saja masuk',
            'aku baru aja keluar',
            'baru aja keluar',
            'baru saja keluar',
            'aku baru aja',
            'baru saja',
            'baru aja',
            'tadi siang',
            'tadi pagi',
            'tadi malam',
            'tadi sore',
            'barusan',
            'tolong catat',
            'tolong catatin',
            'bantu catat',
            'catat ya',
            'catat dong',
            'catatin ya',
            'catat',
            'tolong',
            'bantu',
        ];

        foreach ($fillers as $filler) {
            $cleaned = preg_replace('/\b' . preg_quote($filler, '/') . '\b/i', '', $cleaned) ?? $cleaned;
        }

        // Remove direction keywords (case-insensitive with word boundary)
        $allKeywords = array_merge(self::EXPENSE_KEYWORDS, self::INCOME_KEYWORDS);
        usort($allKeywords, fn($a, $b) => strlen($b) - strlen($a)); // longest first
        foreach ($allKeywords as $kw) {
            $cleaned = preg_replace('/\b' . preg_quote($kw, '/') . '\b/i', '', $cleaned) ?? $cleaned;
        }

        // Remove connector stop words at beginning or end of description (e.g. "dari gaji" -> "gaji", "buat beli makan" -> "makan")
        $cleaned = preg_replace('/\b(?:dari|buat|untuk|sebesar|sebanyak|uang)\b/i', '', $cleaned) ?? $cleaned;

        // Clean any leftover "rp" or "rp." or leading/trailing punctuation (like periods at end of voice transcript)
        $cleaned = preg_replace('/\brp\.?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = trim(preg_replace('/^[:\-+.,\s]+|[:\-+.,\s]+$/', '', $cleaned) ?? $cleaned);
        $description = trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);

        return $description;
    }

    /**
     * Parse budget set command: "budget <amount>" or "anggaran <amount>"
     *
     * @return array{amount: int}|null
     */
    public function parseBudgetSet(string $message): ?array
    {
        if (preg_match('/^(?:budget|anggaran)(?:\s+bulanan)?\s+([0-9.,]+(?:\s*[a-zA-Z]+)?)$/i', trim($message), $matches)) {
            $amount = $this->extractAmount($matches[1]);
            if ($amount && $amount > 0) {
                return [
                    'amount' => $amount,
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
            '/^(ubah|ganti|edit)(\s+transaksi)?/i',
            '/^(hapus|delete)(\s+transaksi)?/i',
            '/^batal/i',

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
