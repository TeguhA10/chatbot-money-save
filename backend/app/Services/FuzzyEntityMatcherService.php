<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

/**
 * FuzzyEntityMatcherService
 *
 * Universal, data-grounded fuzzy and phonetic matching engine for Antigravity/HematWoy.
 * Bridges speech-to-text acoustic mishearings, slang, typos, and abbreviations
 * by verifying user intent directly against their existing database entities
 * across all financial tables (wallets, goals, budgets, transactions).
 */
class FuzzyEntityMatcherService
{
    /**
     * Common phonetic normalizations for Indonesian financial slang & STT mishearings.
     */
    protected array $phoneticSynonyms = [
        'sibang'   => 'seabank',
        'si bang'  => 'seabank',
        'cebang'   => 'seabank',
        'sebang'   => 'seabank',
        'sea bank' => 'seabank',
        'c bank'   => 'seabank',
        'kopi'     => 'gopay',
        'gopai'    => 'gopay',
        'gope'     => 'gopay',
        'gopaye'   => 'gopay',
        'bece a'   => 'bca',
        'becea'    => 'bca',
        'bisa'     => 'bca',
        'besea'    => 'bca',
        'opay'     => 'gopay',
        'sopipe'   => 'shopeepay',
        'sopi pe'  => 'shopeepay',
        'sopi pay' => 'shopeepay',
        'shope'    => 'shopeepay',
        'shopee'   => 'shopeepay',
        'opo'      => 'ovo',
        'oboo'     => 'ovo',
        'obo'      => 'ovo',
        'danau'    => 'dana',
        'dna'      => 'dana',
        'mandri'   => 'mandiri',
        'mandir'   => 'mandiri',
        'beri'     => 'bri',
        'berii'    => 'bri',
        'beni'     => 'bni',
        'jgo'      => 'jago',
        'bang jago'=> 'bank jago',
        'lektop'   => 'laptop',
        'leptop'   => 'laptop',
        'lepptop'  => 'laptop',
        'hempun'   => 'handphone',
        'henpon'   => 'handphone',
        'hp'       => 'handphone',
        'motor'    => 'sepeda motor',
        'kendaraan'=> 'motor',
    ];

    /**
     * Compute a comprehensive similarity score between 0.0 and 1.0
     * combining exact matching, token containment, Levenshtein distance,
     * similar_text percentage, and Indonesian phonetic transforms.
     */
    public function scoreSimilarity(string $query, string $target): float
    {
        $qClean = strtolower(trim($query));
        $tClean = strtolower(trim($target));

        if ($qClean === '' || $tClean === '') {
            return 0.0;
        }

        // 1. Exact match
        if ($qClean === $tClean) {
            return 1.0;
        }

        // 2. Strip non-alphanumeric (ignoring spaces & hyphens)
        $qAlnum = preg_replace('/[^a-z0-9]/', '', $qClean);
        $tAlnum = preg_replace('/[^a-z0-9]/', '', $tClean);

        if ($qAlnum === $tAlnum) {
            return 0.98;
        }

        // 3. Synonym dictionary pre-mapping (e.g. sibang -> seabank)
        $qMapped = $this->phoneticSynonyms[$qClean] ?? $qClean;
        $tMapped = $this->phoneticSynonyms[$tClean] ?? $tClean;
        if ($qMapped === $tMapped) {
            return 0.95;
        }

        // 4. Word / Token overlap (e.g. "lektop" matches "Laptop" in "Beli Laptop", "Jajan" in "Batas Jajan Bulanan")
        $qWords = array_filter(explode(' ', $qClean));
        $tWords = array_filter(explode(' ', $tClean));

        if (!empty($qWords) && !empty($tWords)) {
            // Check if full query or mapped query is a substring of target or vice versa
            if (str_contains($tClean, $qClean) || str_contains($qClean, $tClean) || str_contains($tClean, $qMapped) || str_contains($qMapped, $tClean)) {
                $subRatio = min(strlen($qClean), strlen($tClean)) / max(strlen($qClean), strlen($tClean));
                return max(0.80, 0.65 + ($subRatio * 0.30));
            }

            // Word-by-word comparison for multi-word targets
            $bestWordMatch = 0.0;
            foreach ($qWords as $qw) {
                $qwMapped = $this->phoneticSynonyms[$qw] ?? $qw;
                $qwPhone = $this->phoneticNormalize($qwMapped);

                foreach ($tWords as $tw) {
                    $twMapped = $this->phoneticSynonyms[$tw] ?? $tw;
                    $twPhone = $this->phoneticNormalize($twMapped);

                    if ($qw === $tw || $qwMapped === $twMapped || (!empty($qwPhone) && $qwPhone === $twPhone && strlen($qwPhone) >= 3)) {
                        $bestWordMatch = max($bestWordMatch, 0.82);
                    } else {
                        $wMax = max(strlen($qw), strlen($tw));
                        $wLev = 1.0 - (levenshtein($qw, $tw) / $wMax);
                        if ($wLev >= 0.70) {
                            $bestWordMatch = max($bestWordMatch, $wLev * 0.80);
                        }
                    }
                }
            }

            if ($bestWordMatch >= 0.65) {
                return $bestWordMatch;
            }
        }

        // 5. Phonetic normalization score
        $qPhone = $this->phoneticNormalize($qMapped);
        $tPhone = $this->phoneticNormalize($tMapped);

        if ($qPhone === $tPhone && strlen($qPhone) >= 3) {
            return 0.93;
        }

        // 6. Mathematical algorithms (Levenshtein + similar_text)
        $maxLen = max(strlen($qClean), strlen($tClean));
        $lev = levenshtein($qClean, $tClean);
        $levScore = max(0.0, 1.0 - ($lev / $maxLen));

        similar_text($qClean, $tClean, $simPercent);
        $simScore = $simPercent / 100.0;

        // Also compute on phonetic strings
        $phoneMax = max(strlen($qPhone), strlen($tPhone));
        $phoneLev = levenshtein($qPhone, $tPhone);
        $phoneLevScore = $phoneMax > 0 ? max(0.0, 1.0 - ($phoneLev / $phoneMax)) : 0;

        // Metaphone bonus
        $metaQ = metaphone($qClean);
        $metaT = metaphone($tClean);
        $metaBonus = ($metaQ !== '' && $metaQ === $metaT) ? 0.20 : 0.0;

        // Weighted blend
        $finalScore = max(
            $simScore,
            $levScore,
            ($phoneLevScore * 0.85) + $metaBonus
        );

        return min(1.0, round($finalScore, 4));
    }

    /**
     * Phonetically normalize strings for Indonesian accents, mishearings & loanwords.
     */
    public function phoneticNormalize(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]/', '', $s);

        // Replace common vowel clusters
        $s = str_replace(['ea', 'ee', 'ie'], 'i', $s);
        $s = str_replace(['oo', 'ou'], 'u', $s);

        // Consonant smoothing
        // bank vs bang vs banc
        $s = preg_replace('/bank\b/', 'bang', $s);
        $s = preg_replace('/banc\b/', 'bang', $s);

        // c -> s (unless ch)
        $s = preg_replace('/c(?!h)/', 's', $s);

        // v / p / f smoothing
        $s = str_replace('v', 'f', $s);

        // trailing h stripping (e.g. kas vs kash, bca vs bcah)
        $s = preg_replace('/h+$/', '', $s);

        // collapse double identical characters
        $s = preg_replace('/(.)\\1+/', '$1', $s);

        return $s;
    }

    /**
     * Generic best match finder across any iterable collection of items.
     *
     * @param string $query
     * @param iterable $items
     * @param callable|string $nameExtractor Property name or closure returning string
     * @param float $minThreshold Minimum similarity score to be considered a match (default 0.60)
     * @return array{item: mixed, score: float, name: string, is_exact: bool}|null
     */
    public function findBestMatch(string $query, iterable $items, callable|string $nameExtractor = 'name', float $minThreshold = 0.60): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $bestItem = null;
        $bestScore = 0.0;
        $bestName = '';

        foreach ($items as $item) {
            $name = is_callable($nameExtractor)
                ? (string) $nameExtractor($item)
                : (string) ($item->{$nameExtractor} ?? '');

            if ($name === '') {
                continue;
            }

            // Check exact match first
            if (strcasecmp($query, $name) === 0) {
                return [
                    'item'     => $item,
                    'score'    => 1.0,
                    'name'     => $name,
                    'is_exact' => true,
                ];
            }

            $score = $this->scoreSimilarity($query, $name);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestItem = $item;
                $bestName = $name;
            }
        }

        if ($bestItem !== null && $bestScore >= $minThreshold) {
            return [
                'item'     => $bestItem,
                'score'    => $bestScore,
                'name'     => $bestName,
                'is_exact' => $bestScore >= 0.95,
            ];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // TABLE-SPECIFIC RESOLVERS (VERIFIKASI TERHADAP DATA PENGGUNA)
    // -------------------------------------------------------------------------

    /**
     * Resolve a wallet for the user using exact match, substring, or fuzzy similarity.
     */
    public function resolveWallet(User $user, ?string $query, float $threshold = 0.60): ?Wallet
    {
        if (empty($query)) {
            return null;
        }

        $clean = trim($query);
        if (Str::isUuid($clean)) {
            $w = $user->wallets()->where('id', $clean)->first();
            if ($w) return $w;
        }

        $wallets = $user->wallets()->get();
        if ($wallets->isEmpty()) {
            return null;
        }

        $match = $this->findBestMatch($clean, $wallets, 'name', $threshold);
        return $match ? $match['item'] : null;
    }

    /**
     * Check if a proposed new wallet name is dangerously close to an existing wallet.
     * Prevents duplicate typo wallets like "sibang" when "SeaBank" already exists.
     */
    public function checkSimilarWallet(User $user, string $candidateName, float $threshold = 0.68): ?array
    {
        $wallets = $user->wallets()->get();
        if ($wallets->isEmpty()) {
            return null;
        }

        $match = $this->findBestMatch($candidateName, $wallets, 'name', $threshold);
        return $match;
    }

    /**
     * Resolve a financial goal for the user using fuzzy similarity.
     */
    public function resolveGoal(User $user, ?string $query, float $threshold = 0.58): ?FinancialGoal
    {
        if (empty($query)) {
            return null;
        }

        $clean = trim($query);
        if (Str::isUuid($clean)) {
            $g = $user->financialGoals()->where('id', $clean)->first();
            if ($g) return $g;
        }

        $goals = $user->financialGoals()->where('status', 'ACTIVE')->get();
        if ($goals->isEmpty()) {
            // Also check all goals if no active ones match
            $goals = $user->financialGoals()->get();
        }

        if ($goals->isEmpty()) {
            return null;
        }

        $match = $this->findBestMatch($clean, $goals, 'name', $threshold);
        return $match ? $match['item'] : null;
    }

    /**
     * Check if a proposed new goal name is dangerously close to an existing goal.
     */
    public function checkSimilarGoal(User $user, string $candidateName, float $threshold = 0.68): ?array
    {
        $goals = $user->financialGoals()->get();
        if ($goals->isEmpty()) {
            return null;
        }

        return $this->findBestMatch($candidateName, $goals, 'name', $threshold);
    }

    /**
     * Resolve a budget for the user by description or category.
     */
    public function resolveBudget(User $user, ?string $query, float $threshold = 0.58): ?Budget
    {
        if (empty($query)) {
            return null;
        }

        $clean = trim($query);
        $budgets = Budget::where('user_jid', $user->jid)->get();
        if ($budgets->isEmpty()) {
            return null;
        }

        // Match against description or category
        $match = $this->findBestMatch($clean, $budgets, function (Budget $b) {
            return $b->description ?: ($b->category ?: 'Batas Pengeluaran Bulanan');
        }, $threshold);

        return $match ? $match['item'] : null;
    }

    // -------------------------------------------------------------------------
    // AI CONTEXT GROUNDING ENGINE
    // -------------------------------------------------------------------------

    /**
     * Build an explicit, rich context block of the user's active database entities.
     * This is injected into the AI's prompt so it is ALWAYS grounded in reality
     * and knows exactly what entities already exist before taking any action.
     */
    public function buildUserContextPrompt(User $user): string
    {
        // 1. Wallets
        $wallets = $user->wallets()->get();
        $walletList = [];
        foreach ($wallets as $w) {
            $balFmt = 'Rp' . number_format((int) $w->balance, 0, ',', '.');
            $walletList[] = "- \"{$w->name}\" (Tipe: {$w->type}, Saldo: {$balFmt})" . ($w->is_default ? " [Default/Utama]" : "");
        }
        $walletStr = !empty($walletList) ? implode("\n", $walletList) : "- Belum ada dompet khusus (Hanya Cash)";

        // 2. Goals
        $goals = $user->financialGoals()->where('status', 'ACTIVE')->get();
        $goalList = [];
        foreach ($goals as $g) {
            $curFmt = 'Rp' . number_format((int) $g->current_amount, 0, ',', '.');
            $tgtFmt = 'Rp' . number_format((int) $g->target_amount, 0, ',', '.');
            $goalList[] = "- \"{$g->name}\" (Terkumpul: {$curFmt} dari {$tgtFmt}, Progress: {$g->progress_percent}%)";
        }
        $goalStr = !empty($goalList) ? implode("\n", $goalList) : "- Tidak ada target tabungan aktif";

        // 3. Budgets
        $budget = Budget::where('user_jid', $user->jid)->first();
        if ($budget) {
            $limFmt = 'Rp' . number_format((int) $budget->limit_amount, 0, ',', '.');
            $desc = $budget->description ?: 'Batas Pengeluaran Bulanan';
            $budgetStr = "- \"{$desc}\" (Plafon: {$limFmt})";
        } else {
            $budgetStr = "- Belum memasang batas plafon budget";
        }

        // 4. Recent frequent transaction descriptions / merchants (for smart context)
        $recentTxs = Transaction::where('user_jid', $user->jid)
            ->active()
            ->latest('id')
            ->limit(10)
            ->pluck('description')
            ->unique()
            ->values()
            ->all();
        $recentTxStr = !empty($recentTxs) ? implode(', ', array_slice($recentTxs, 0, 6)) : 'Belum ada';

        return "=== DATA RESMI PENGGUNA SAAT INI (RUJUKAN UTAMA ANDA) ===\n"
            . "1. DAFTAR DOMPET PENGGUNA:\n{$walletStr}\n\n"
            . "2. DAFTAR TARGET TABUNGAN AKTIF:\n{$goalStr}\n\n"
            . "3. PLAFON ANGGARAN (BUDGET):\n{$budgetStr}\n\n"
            . "4. CONTOH TRANSAKSI TERAKHIR: {$recentTxStr}\n"
            . "========================================================\n\n"
            . "ATURAN KRUSIAL PENCOCOKAN KATA & TYPO VOICE NOTE:\n"
            . "- Pengguna berbicara lewat pesan suara (Voice Note) atau teks cepat yang rentan TYPO atau SALAH DENGAR fonetik.\n"
            . "- Contoh: 'sibang' / 'cebang' / 'si bang' MERUJUK KE 'SeaBank'. 'kopi' / 'gopai' MERUJUK KE 'GoPay'. 'bece a' MERUJUK KE 'BCA'. 'lektop' MERUJUK KE 'Laptop'.\n"
            . "- Selalu COCOKKAN nama yang diucapkan pengguna ke daftar DATA RESMI PENGGUNA di atas!\n"
            . "- JANGAN PERNAH membuat dompet atau target baru jika namanya mirip dengan data yang sudah terdaftar di atas.\n"
            . "- Jika pengguna menyebut 'sibang' dan di daftar dompet ada 'SeaBank', panggil tool dengan nama dompet resmi: 'SeaBank'!\n";
    }
}
