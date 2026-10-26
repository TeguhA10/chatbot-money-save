<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AiFinancialAgentService
 *
 * Provides a conversational AI Financial Assistant powered by Google Gemini Tool Calling.
 * Stricly isolated to the authenticated user's data across 4 domains:
 * 1. Transaksi (Get, Create, Edit, Delete)
 * 2. Dompet (Get, Create, Edit, Transfer)
 * 3. Target Tabungan / Goals (Get, Create, Setor, Delete)
 * 4. Plafon Budget (Get, Set, Remove)
 */
class AiFinancialAgentService
{
    public function __construct(
        protected FinanceService $financeService,
        protected BudgetService $budgetService,
        protected EncryptionService $encryptionService,
        protected FuzzyEntityMatcherService $fuzzyMatcher,
    ) {}

    /**
     * Process natural language financial request for the given user.
     */
    public function process(User $user, string $userMessage): ?string
    {
        $geminiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $geminiModel = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest'));

        if (!$geminiKey) {
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$geminiKey}";

        $userName = $user->display_name ?: 'Kak';
        $todayStr = now()->translatedFormat('l, d F Y');
        $userContext = $this->fuzzyMatcher->buildUserContextPrompt($user);

        $systemInstruction = "Nama kamu adalah Viona, asisten perencana keuangan pribadi yang ramah, santai, cerdas, dan empatik dari HematWoy. "
            . "Kamu bisa memanggil dirimu 'Viona' dan menyapa lawan bicaramu dengan hangat. "
            . "Kamu sedang berbicara dengan {$userName}. Hari ini adalah {$todayStr}. "
            . "Gunakan gaya bahasa Indonesia yang natural, hangat, ringkas, dan jelas.\n\n"
            . "{$userContext}\n\n"
            . "Kamu memiliki wewenang untuk melihat, mencatat, mengubah, atau menghapus data keuangan HANYA untuk pengguna ini melalui tools yang tersedia: "
            . "1) Transaksi, 2) Dompet, 3) Target Tabungan, dan 4) Plafon Budget. "
            . "Jika pengguna meminta mencatat, melihat, mengedit, mentransfer, atau menghapus data, PANGGIL fungsi tools yang sesuai. "
            . "Tampilkan angka nominal dalam format Rupiah (contoh: Rp25.000, Rp1.500.000). "
            . "Jika ada pertanyaan umum tentang keuangan atau tips hemat, berikan jawaban bijak dan solutif.\n\n"
            . "ATURAN PENGELUARAN DENGAN TARGET TABUNGAN ATAU POS ANGGARAN:\n"
            . "- Saat mencatat transaksi pengeluaran (`catat_transaksi`), jika pengguna menyebut ingin menabung atau menyisihkan untuk target tabungan tertentu (contoh: 'nabung 100rb beli laptop', 'tabung 50rb target umroh'), sertakan parameter `goal_name` sesuai target aktif pengguna.\n"
            . "- Jika pengguna menyebut pengeluaran dari pos anggaran belanja tertentu (contoh: 'beli makan 30rb potong anggaran makan', 'belanja 200rb plafon belanja bulanan'), sertakan parameter `budget_name`.\n"
            . "- Jika hanya pengeluaran atau pemasukan rutin tanpa target/budget khusus, kosongkan `goal_name` dan `budget_name`.\n\n"
            . "ATURAN MENAMPILKAN DAFTAR TRANSAKSI DI CHAT:\n"
            . "1. Batasan waktu pencarian riwayat data di chat WhatsApp adalah MAKSIMAL 1 BULAN terakhir (atau bulan ini). Jika pengguna menanyakan riwayat lebih lama dari 1 bulan, jelaskan dengan ramah bahwa di chat WhatsApp batasan riwayat adalah maksimal 1 bulan terakhir, dan arahkan pengguna untuk membuka Dashboard Web.\n"
            . "2. Saat menampilkan transaksi, sampaikan dengan jelas, jujur, dan hangat total transaksi sebenarnya dalam 1 bulan/periode tersebut beserta total nominalnya (contoh: 'Di bulan ini ada total 15 catatan pemasukan dengan total nilai Rp85.000.000'). JANGAN PERNAH menyimpulkan total transaksi hanya sejumlah item yang ditampilkan di chat!\n"
            . "3. Jelaskan kepada pengguna bahwa demi kenyamanan membaca di WhatsApp (agar pesan tidak terlalu panjang), Viona menampilkan 5 transaksi terbaru.\n"
            . "4. Beri tahukan batasannya dengan bahasa yang santai dan mudah dimengerti, serta arahkan bahwa sisa transaksi dan seluruh riwayat lengkap bisa dilihat di Dashboard Web.";

        $tools = $this->getToolDeclarations();

        $messages = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => "System Context: {$systemInstruction}\n\nPengguna: {$userMessage}"]
                ]
            ]
        ];

        try {
            // Step 1: Initial call to Gemini with tools
            $response = Http::timeout(20)->post($url, [
                'contents' => $messages,
                'tools'    => $tools,
            ]);

            if ($response->failed()) {
                Log::warning('Gemini AI Agent call failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return null;
            }

            $respData = $response->json();
            $candidate = $respData['candidates'][0] ?? null;

            if (!$candidate) {
                return null;
            }

            $parts = $candidate['content']['parts'] ?? [];
            Log::info('Gemini Turn 1 raw parts: ' . json_encode($parts));
            $functionCalls = [];
            $textOutput = '';

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCalls[] = $part['functionCall'];
                }
                if (isset($part['text'])) {
                    $textOutput .= $part['text'];
                }
            }

            // If no function call, return text directly
            if (empty($functionCalls)) {
                return !empty($textOutput) ? trim($textOutput) : null;
            }

            // Step 2: Execute function calls strictly for this user
            $functionResponses = [];
            foreach ($functionCalls as $fc) {
                $funcName = $fc['name'] ?? '';
                $args = is_array($fc['args'] ?? null) ? $fc['args'] : [];

                $execResult = $this->executeTool($user, $funcName, $args);

                $fResp = [
                    'name'     => $funcName,
                    'response' => [
                        'result' => $execResult,
                    ],
                ];
                if (!empty($fc['id'])) {
                    $fResp['id'] = $fc['id'];
                }

                $functionResponses[] = [
                    'functionResponse' => $fResp,
                ];
            }

            // Step 3: Send function execution results back to Gemini for final response
            $cleanModelParts = [];
            foreach ($parts as $p) {
                if (isset($p['functionCall'])) {
                    $fc = $p['functionCall'];
                    $cleanFc = [
                        'name' => $fc['name'],
                        'args' => empty($fc['args']) ? (object) [] : (object) $fc['args'],
                    ];
                    if (!empty($fc['id'])) {
                        $cleanFc['id'] = $fc['id'];
                    }

                    $partData = ['functionCall' => $cleanFc];
                    if (!empty($p['thoughtSignature'])) {
                        $partData['thoughtSignature'] = $p['thoughtSignature'];
                    }
                    $cleanModelParts[] = $partData;
                } elseif (isset($p['text'])) {
                    $cleanModelParts[] = ['text' => $p['text']];
                }
            }

            $messages[] = [
                'role'  => 'model',
                'parts' => $cleanModelParts,
            ];
            $messages[] = [
                'role'  => 'user',
                'parts' => $functionResponses,
            ];

            $followUpResp = Http::timeout(20)->post($url, [
                'contents' => $messages,
            ]);

            if ($followUpResp->successful()) {
                $finalText = $followUpResp->json('candidates.0.content.parts.0.text') ?? '';
                if (!empty($finalText)) {
                    return trim($finalText);
                }
            } else {
                Log::warning('Gemini followUpResp failed', [
                    'status' => $followUpResp->status(),
                    'body'   => $followUpResp->json(),
                ]);
            }

            return !empty($textOutput) ? trim($textOutput) : null;

        } catch (\Throwable $e) {
            Log::error('AiFinancialAgentService exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * Tool execution router strictly isolated to $user.
     */
    protected function executeTool(User $user, string $name, array $args): array
    {
        try {
            switch ($name) {
                // --- TRANSAKSI ---
                case 'lihat_transaksi':
                    return $this->toolLihatTransaksi($user, $args);

                case 'catat_transaksi':
                    return $this->toolCatatTransaksi($user, $args);

                case 'koreksi_transaksi':
                    return $this->toolKoreksiTransaksi($user, $args);

                case 'hapus_transaksi':
                    return $this->toolHapusTransaksi($user, $args);

                // --- DOMPET ---
                case 'lihat_dompet':
                    return $this->toolLihatDompet($user);

                case 'tambah_dompet':
                    return $this->toolTambahDompet($user, $args);

                case 'ubah_dompet':
                    return $this->toolUbahDompet($user, $args);

                case 'hapus_dompet':
                    return $this->toolHapusDompet($user, $args);

                case 'transfer_dompet':
                    return $this->toolTransferDompet($user, $args);

                // --- TARGET TABUNGAN ---
                case 'lihat_target':
                    return $this->toolLihatTarget($user);

                case 'buat_target':
                    return $this->toolBuatTarget($user, $args);

                case 'tabung_target':
                    return $this->toolTabungTarget($user, $args);

                case 'ubah_target':
                    return $this->toolUbahTarget($user, $args);

                case 'hapus_target':
                    return $this->toolHapusTarget($user, $args);

                // --- PLAFON BUDGET ---
                case 'lihat_budget':
                    return $this->toolLihatBudget($user);

                case 'atur_budget':
                    return $this->toolAturBudget($user, $args);

                case 'hapus_budget':
                    return $this->toolHapusBudget($user);

                default:
                    return ['status' => 'error', 'message' => "Fungsi '{$name}' tidak dikenali."];
            }
        } catch (\Throwable $e) {
            Log::warning("Tool {$name} error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Tool Implementations (Strictly Scoped by user_jid)
    // -------------------------------------------------------------------------

    protected function toolLihatTransaksi(User $user, array $args): array
    {
        $limit = min(15, max(1, (int) ($args['limit'] ?? 5)));
        $type = !empty($args['type']) ? strtoupper($args['type']) : null;
        $period = $args['period'] ?? null;

        // Ambil ringkasan agregat periode (dibatasi maksimal 1 bulan)
        $summary = $this->financeService->getTransactionPeriodSummary($user, $type, $period);
        $txs = $this->financeService->getTransactions($user, $type, $period, $limit);

        $list = [];
        foreach ($txs as $t) {
            $list[] = [
                'id'          => substr((string) $t->id, -8),
                'type'        => $t->type,
                'amount'      => (int) $t->amount,
                'description' => $t->description,
                'date'        => $t->transaction_date->format('d/m/Y H:i'),
                'wallet'      => $t->wallet ? $t->wallet->name : 'Utama',
                'target'      => $t->goal ? $t->goal->name : null,
                'budget'      => $t->budget ? ($t->budget->description ?: 'Batas Belanja') : null,
            ];
        }

        $totalCount = $summary['total_count'];
        $totalAmount = $summary['total_amount'];
        $shownCount = count($list);
        $hasMore = $totalCount > $shownCount;

        return [
            'status'                       => 'success',
            'saldo_saat_ini'               => $this->financeService->getBalance($user),
            'periode'                      => $summary['period_label'],
            'total_transaksi_dalam_periode' => $totalCount,
            'total_nominal_dalam_periode'  => 'Rp' . number_format($totalAmount, 0, ',', '.'),
            'jumlah_transaksi_ditampilkan' => $shownCount,
            'transaksi'                    => $list,
            'ada_transaksi_lain'           => $hasMore,
            'sisa_belum_ditampilkan'       => max(0, $totalCount - $shownCount),
            'panduan_jawaban_viona'        => "PANDUAN MENYAMPAIKAN KE USER:\n"
                . "1. Sebutkan total catatan sesungguhnya di periode ini: Ada {$totalCount} catatan (total Rp" . number_format($totalAmount, 0, ',', '.') . "). JANGAN PERNAH bilang hanya ada {$shownCount} catatan jika totalnya {$totalCount}!\n"
                . "2. Jelaskan dengan santai dan ramah: 'Karena batasan tampilan di chat WhatsApp agar tetap ringkas dan nyaman dibaca (maksimal rentang 1 bulan terakhir), berikut Viona tampilkan {$shownCount} transaksi terbarunya ya.'\n"
                . ($hasMore ? "3. Beri tahu pengguna bahwa masih ada " . ($totalCount - $shownCount) . " transaksi lainnya di periode ini, dan pengguna bisa melihat daftar selengkapnya di Dashboard Web HematWoy.\n" : "3. Beri tahu pengguna bahwa batasan riwayat di chat adalah maksimal 1 bulan terakhir.\n"),
        ];
    }

    protected function toolCatatTransaksi(User $user, array $args): array
    {
        $type = strtoupper($args['type'] ?? 'EXPENSE');
        $amount = (int) ($args['amount'] ?? 0);
        $desc = trim($args['description'] ?? 'Tanpa keterangan');
        $walletName = $args['wallet_name'] ?? null;
        $goalName = !empty($args['goal_name']) ? trim($args['goal_name']) : null;
        $budgetName = !empty($args['budget_name']) ? trim($args['budget_name']) : null;

        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Nominal transaksi harus lebih dari 0.'];
        }

        $targetWalletName = !empty($walletName) ? trim($walletName) : 'Cash';
        $w = $this->fuzzyMatcher->resolveWallet($user, $targetWalletName);
        if (!$w) {
            $w = $this->financeService->getOrCreateDefaultWallet($user);
        }
        $walletId = $w->id;

        $goalId = null;
        $goalObj = null;
        if ($type === 'EXPENSE' && $goalName) {
            $goalObj = $this->fuzzyMatcher->resolveGoal($user, $goalName);
            if ($goalObj) {
                $goalId = $goalObj->id;
                // Update goal progress
                $newCurrent = (int) $goalObj->current_amount + $amount;
                $goalObj->encrypted_current_amount = $this->encryptionService->encryptForStorage($newCurrent);
                if ($newCurrent >= (int) $goalObj->target_amount) {
                    $goalObj->status = 'ACHIEVED';
                }
                $goalObj->save();
            }
        }

        $budgetId = null;
        $budgetObj = null;
        if ($type === 'EXPENSE' && !$goalId && $budgetName) {
            $budgetObj = $this->fuzzyMatcher->resolveBudget($user, $budgetName);
            if ($budgetObj) {
                $budgetId = $budgetObj->id;
            }
        }

        $parsed = [
            'type'        => $type,
            'amount'      => $amount,
            'description' => $desc,
            'category'    => $desc,
            'wallet_id'   => $walletId,
            'goal_id'     => $goalId,
            'budget_id'   => $budgetId,
            'is_voice'    => false,
        ];

        $transaction = $this->financeService->recordTransaction($user, $parsed);
        $wName = $transaction->wallet?->name ?? $w->name;

        $allocationNote = '';
        if ($goalObj) {
            $allocationNote = " (🎯 Alokasi Target: '{$goalObj->name}', progress {$goalObj->progress_percent}%)";
        } elseif ($budgetObj) {
            $budgetDesc = $budgetObj->description ?: 'Batas Pengeluaran Bulanan';
            $allocationNote = " (📊 Pos Anggaran: '{$budgetDesc}')";
        }

        return [
            'status'          => 'success',
            'pesan'           => "Transaksi {$type} sebesar Rp" . number_format($amount, 0, ',', '.') . " pada dompet {$wName}{$allocationNote} berhasil dicatat.",
            'transaksi_id'    => substr((string) $transaction->id, -8),
            'dompet'          => $wName,
            'target_tabungan' => $goalObj ? $goalObj->name : null,
            'pos_anggaran'    => $budgetObj ? ($budgetObj->description ?: 'Batas Belanja') : null,
            'saldo_terbaru'   => $this->financeService->getBalance($user),
        ];
    }

    protected function toolKoreksiTransaksi(User $user, array $args): array
    {
        $query = trim($args['transaction_id_or_keyword'] ?? '');
        $newAmount = isset($args['new_amount']) ? (int) $args['new_amount'] : null;
        $newDesc = isset($args['new_description']) ? trim($args['new_description']) : null;

        /** @var Transaction|null $tx */
        $tx = null;

        if (!empty($query)) {
            $tx = $user->transactions()->active()
                ->where(function ($q) use ($query) {
                    $q->where('id', 'like', "%{$query}")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->latest('id')
                ->first();
        }

        if (!$tx) {
            $tx = $user->transactions()->active()->latest('id')->first();
        }

        if (!$tx) {
            return ['status' => 'error', 'message' => 'Tidak ada transaksi aktif yang dapat dikoreksi.'];
        }

        $updateAmount = ($newAmount !== null && $newAmount > 0) ? $newAmount : (int) $tx->amount;
        $updateDesc = ($newDesc !== null && $newDesc !== '') ? $newDesc : $tx->description;

        $result = $this->financeService->updateTransaction($user, (string) $tx->id, $updateAmount, $updateDesc);

        return [
            'status'        => 'success',
            'pesan'         => "Transaksi berhasil diperbarui menjadi {$updateDesc} (Rp" . number_format($updateAmount, 0, ',', '.') . ").",
            'saldo_terbaru' => $result['new_balance'],
        ];
    }

    protected function toolHapusTransaksi(User $user, array $args): array
    {
        $query = trim($args['transaction_id_or_keyword'] ?? '');

        /** @var Transaction|null $tx */
        $tx = null;

        if (!empty($query)) {
            $tx = $user->transactions()->active()
                ->where(function ($q) use ($query) {
                    $q->where('id', 'like', "%{$query}")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->latest('id')
                ->first();
        }

        if (!$tx) {
            $tx = $user->transactions()->active()->latest('id')->first();
        }

        if (!$tx) {
            return ['status' => 'error', 'message' => 'Tidak ada transaksi yang dapat dihapus.'];
        }

        $result = $this->financeService->voidTransaction($user, $tx->id);

        return [
            'status'        => 'success',
            'pesan'         => "Transaksi '{$tx->description}' sebesar Rp" . number_format((int) $tx->amount, 0, ',', '.') . " berhasil dibatalkan/dihapus.",
            'saldo_terbaru' => $result['new_balance'] ?? $this->financeService->getBalance($user),
        ];
    }

    protected function toolLihatDompet(User $user): array
    {
        $wallets = $user->wallets()->get();
        $totalBalance = $this->financeService->getBalance($user);

        $res = [];
        foreach ($wallets as $w) {
            $res[] = [
                'id'      => $w->id,
                'name'    => $w->name,
                'type'    => $w->type,
                'balance' => (int) $w->balance,
            ];
        }

        return [
            'status'       => 'success',
            'total_saldo'  => $totalBalance,
            'daftar_dompet'=> $res,
        ];
    }

    protected function toolTambahDompet(User $user, array $args): array
    {
        $name = trim($args['name'] ?? '');
        $initialBalance = (int) ($args['initial_balance'] ?? 0);

        if (empty($name)) {
            return ['status' => 'error', 'message' => 'Nama dompet wajib diisi.'];
        }

        // Fuzzy guardrail: verify user doesn't already have an identical or very similar wallet (e.g. sibang vs SeaBank)
        $similar = $this->fuzzyMatcher->checkSimilarWallet($user, $name, 0.68);
        if ($similar) {
            $existing = $similar['item'];
            $simPct = (int) round($similar['score'] * 100);
            return [
                'status'  => 'error',
                'message' => "Anda sudah memiliki dompet '{$existing->name}' yang sangat mirip (kemiripan {$simPct}%). Sistem mencegah duplikasi akibat typo suara. Silakan gunakan dompet '{$existing->name}'.",
            ];
        }

        $wallet = $user->wallets()->create([
            'name'       => $name,
            'type'       => 'OTHER',
            'is_default' => false,
        ]);

        if ($initialBalance > 0) {
            $this->financeService->recordTransaction($user, [
                'type'        => 'INCOME',
                'amount'      => $initialBalance,
                'description' => "Saldo awal {$name}",
                'wallet_id'   => $wallet->id,
            ]);
        }

        return [
            'status'      => 'success',
            'pesan'       => "Dompet '{$name}' berhasil dibuat" . ($initialBalance > 0 ? " dengan saldo awal Rp" . number_format($initialBalance, 0, ',', '.') : "") . ".",
            'total_saldo' => $this->financeService->getBalance($user),
        ];
    }

    protected function toolUbahDompet(User $user, array $args): array
    {
        $oldName = trim($args['old_name'] ?? '');
        $newName = trim($args['new_name'] ?? '');
        $newBalance = isset($args['new_balance']) ? (int) $args['new_balance'] : null;

        if (empty($oldName)) {
            return ['status' => 'error', 'message' => 'Nama dompet yang ingin diubah harus disebutkan.'];
        }

        $targetWallet = $this->fuzzyMatcher->resolveWallet($user, $oldName);
        if (!$targetWallet) {
            return ['status' => 'error', 'message' => "Dompet '{$oldName}' tidak ditemukan."];
        }

        try {
            $data = [];
            if (!empty($newName)) {
                $data['name'] = $newName;
            }
            if ($newBalance !== null) {
                $data['balance'] = $newBalance;
            }

            $wallet = $this->financeService->updateWallet($user, $targetWallet->id, $data);

            return [
                'status' => 'success',
                'pesan'  => "Dompet '{$wallet->name}' berhasil diperbarui. Saldo saat ini: Rp" . number_format($wallet->balance, 0, ',', '.') . ".",
                'dompet' => [
                    'id'      => $wallet->id,
                    'name'    => $wallet->name,
                    'balance' => (int) $wallet->balance,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function toolHapusDompet(User $user, array $args): array
    {
        $walletName = trim($args['wallet_name'] ?? '');
        $transferTo = !empty($args['transfer_to_wallet_name']) ? trim($args['transfer_to_wallet_name']) : null;

        if (empty($walletName)) {
            return ['status' => 'error', 'message' => 'Nama dompet yang ingin dihapus harus disebutkan.'];
        }

        $targetWallet = $this->fuzzyMatcher->resolveWallet($user, $walletName);
        if (!$targetWallet) {
            return ['status' => 'error', 'message' => "Dompet '{$walletName}' tidak ditemukan."];
        }

        $targetTransferWalletId = null;
        if ($transferTo) {
            $tw = $this->fuzzyMatcher->resolveWallet($user, $transferTo);
            if ($tw) $targetTransferWalletId = $tw->id;
        }

        try {
            $this->financeService->deleteWallet($user, $targetWallet->id, $targetTransferWalletId);

            return [
                'status' => 'success',
                'pesan'  => "Dompet '{$targetWallet->name}' berhasil dihapus dan saldonya telah diamankan.",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function toolTransferDompet(User $user, array $args): array
    {
        $fromName = trim($args['from_wallet'] ?? '');
        $toName = trim($args['to_wallet'] ?? '');
        $amount = (int) ($args['amount'] ?? 0);

        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Nominal transfer harus lebih dari 0.'];
        }

        $fromWallet = $this->fuzzyMatcher->resolveWallet($user, $fromName);
        $toWallet = $this->fuzzyMatcher->resolveWallet($user, $toName);

        if (!$fromWallet || !$toWallet) {
            return ['status' => 'error', 'message' => 'Dompet asal atau dompet tujuan tidak ditemukan.'];
        }

        if ($fromWallet->id === $toWallet->id) {
            return ['status' => 'error', 'message' => 'Dompet asal dan tujuan tidak boleh sama.'];
        }

        $this->financeService->transferFunds($user, $fromWallet->id, $toWallet->id, $amount);

        return [
            'status' => 'success',
            'pesan'  => "Transfer Rp" . number_format($amount, 0, ',', '.') . " dari {$fromWallet->name} ke {$toWallet->name} berhasil.",
        ];
    }

    protected function toolLihatTarget(User $user): array
    {
        $goals = $user->financialGoals()->where('status', 'ACTIVE')->get();

        $res = [];
        foreach ($goals as $g) {
            $res[] = [
                'id'               => $g->id,
                'name'             => $g->name,
                'target_amount'    => (int) $g->target_amount,
                'current_amount'   => (int) $g->current_amount,
                'progress_percent' => $g->progress_percent,
                'target_date'      => $g->target_date ? $g->target_date->format('d/m/Y') : null,
            ];
        }

        return [
            'status'        => 'success',
            'daftar_target' => $res,
            'count'         => count($res),
        ];
    }

    protected function toolBuatTarget(User $user, array $args): array
    {
        $name = trim($args['name'] ?? '');
        $targetAmount = (int) ($args['target_amount'] ?? 0);
        $targetDate = $args['target_date'] ?? null;

        if (empty($name) || $targetAmount <= 0) {
            return ['status' => 'error', 'message' => 'Nama target dan nominal target harus valid.'];
        }

        // Fuzzy guardrail: verify user doesn't already have an identical or very similar goal
        $similar = $this->fuzzyMatcher->checkSimilarGoal($user, $name, 0.68);
        if ($similar) {
            $existing = $similar['item'];
            $simPct = (int) round($similar['score'] * 100);
            return [
                'status'  => 'error',
                'message' => "Anda sudah memiliki target tabungan '{$existing->name}' yang sangat mirip (kemiripan {$simPct}%). Silakan gunakan target yang sudah ada atau gunakan nama yang lebih spesifik.",
            ];
        }

        $goal = $user->financialGoals()->create([
            'name'                     => $name,
            'encrypted_target_amount'  => $this->encryptionService->encryptForStorage($targetAmount),
            'encrypted_current_amount' => $this->encryptionService->encryptForStorage(0),
            'target_date'              => $targetDate ? Carbon::parse($targetDate)->toDateString() : null,
            'status'                   => 'ACTIVE',
        ]);

        return [
            'status' => 'success',
            'pesan'  => "Target tabungan '{$name}' sebesar Rp" . number_format($targetAmount, 0, ',', '.') . " berhasil dibuat.",
        ];
    }

    protected function toolTabungTarget(User $user, array $args): array
    {
        $goalName = trim($args['goal_name'] ?? '');
        $amount = (int) ($args['amount'] ?? 0);
        $walletName = $args['wallet_name'] ?? null;

        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Nominal tabungan harus lebih dari 0.'];
        }

        $goal = $this->fuzzyMatcher->resolveGoal($user, $goalName);

        if (!$goal) {
            return ['status' => 'error', 'message' => "Target tabungan '{$goalName}' tidak ditemukan."];
        }

        // Catat pengeluaran dari dompet (opsional jika dompet ditentukan)
        $walletId = null;
        if (!empty($walletName)) {
            $w = $this->fuzzyMatcher->resolveWallet($user, $walletName);
            if ($w) $walletId = $w->id;
        }

        $this->financeService->recordTransaction($user, [
            'type'        => 'EXPENSE',
            'amount'      => $amount,
            'description' => "Setoran Tabungan: {$goal->name}",
            'wallet_id'   => $walletId,
            'goal_id'     => $goal->id,
        ]);

        $newCurrent = (int) $goal->current_amount + $amount;
        $goal->encrypted_current_amount = $this->encryptionService->encryptForStorage($newCurrent);
        if ($newCurrent >= (int) $goal->target_amount) {
            $goal->status = 'ACHIEVED';
        }
        $goal->save();

        return [
            'status'         => 'success',
            'pesan'          => "Berhasil menabung Rp" . number_format($amount, 0, ',', '.') . " untuk '{$goal->name}'. Progress: {$goal->progress_percent}%.",
            'current_amount' => $newCurrent,
            'target_amount'  => (int) $goal->target_amount,
            'is_achieved'    => $goal->status === 'ACHIEVED',
        ];
    }

    protected function toolUbahTarget(User $user, array $args): array
    {
        $goalName = trim($args['goal_name'] ?? '');
        $newName = trim($args['new_name'] ?? '');
        $newAmount = isset($args['new_target_amount']) ? (int) $args['new_target_amount'] : null;
        $newDate = $args['new_target_date'] ?? null;

        if (empty($goalName)) {
            return ['status' => 'error', 'message' => 'Nama target yang ingin diubah harus disebutkan.'];
        }

        $goal = $this->fuzzyMatcher->resolveGoal($user, $goalName);
        if (!$goal) {
            return ['status' => 'error', 'message' => "Target tabungan '{$goalName}' tidak ditemukan."];
        }

        try {
            $data = [];
            if (!empty($newName)) {
                $data['name'] = $newName;
            }
            if ($newAmount && $newAmount > 0) {
                $data['target_amount'] = $newAmount;
            }
            if ($newDate !== null) {
                $data['target_date'] = $newDate;
            }

            $updated = $this->financeService->updateGoal($user, $goal->id, $data);

            return [
                'status'         => 'success',
                'pesan'          => "Target tabungan '{$updated->name}' berhasil diperbarui. Target nominal: Rp" . number_format($updated->target_amount, 0, ',', '.') . " (Progress: {$updated->progress_percent}%).",
                'goal'           => [
                    'id'               => $updated->id,
                    'name'             => $updated->name,
                    'target_amount'    => (int) $updated->target_amount,
                    'current_amount'   => (int) $updated->current_amount,
                    'progress_percent' => $updated->progress_percent,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function toolHapusTarget(User $user, array $args): array
    {
        $goalName = trim($args['goal_name'] ?? '');

        if (empty($goalName)) {
            return ['status' => 'error', 'message' => 'Nama target tabungan yang ingin dihapus harus disebutkan.'];
        }

        $goal = $this->fuzzyMatcher->resolveGoal($user, $goalName);
        if (!$goal) {
            return ['status' => 'error', 'message' => "Target tabungan '{$goalName}' tidak ditemukan."];
        }

        try {
            $result = $this->financeService->deleteGoal($user, $goal->id, true);
            $pesan = "Target tabungan '{$result['name']}' berhasil dihapus.";
            if (!empty($result['refunded_amount']) && $result['refunded_amount'] > 0) {
                $pesan .= " Dana terkumpul sebesar Rp" . number_format($result['refunded_amount'], 0, ',', '.') . " telah dikembalikan ke {$result['wallet']->name}.";
            }

            return [
                'status' => 'success',
                'pesan'  => $pesan,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function toolLihatBudget(User $user): array
    {
        $budget = Budget::where('user_jid', $user->jid)->first();

        if (!$budget) {
            return [
                'status' => 'empty',
                'pesan'  => 'Anda belum mengatur plafon budget bulanan. Ketik perintah seperti "atur budget 3jt" untuk memasang batas pengeluaran.',
            ];
        }

        $status = $this->budgetService->getConsumption($budget);

        return [
            'status'           => 'success',
            'limit_amount'     => $status['limit_amount'],
            'spent_amount'     => $status['spent_amount'],
            'remaining_amount' => $status['remaining_amount'],
            'percentage'       => $status['percentage'],
            'is_warning'       => $status['is_warning'],
            'is_exceeded'      => $status['is_exceeded'],
            'progress_bar'     => $status['progress_bar'] ?? '',
        ];
    }

    protected function toolAturBudget(User $user, array $args): array
    {
        $limitAmount = (int) ($args['limit_amount'] ?? 0);
        $desc = trim($args['description'] ?? 'Batas Pengeluaran Bulanan');

        if ($limitAmount <= 0) {
            return ['status' => 'error', 'message' => 'Plafon budget bulanan harus lebih dari 0.'];
        }

        $budget = $this->budgetService->setBudget($user, $limitAmount, $desc);

        return [
            'status'       => 'success',
            'pesan'        => "Plafon budget '{$desc}' berhasil diatur sebesar Rp" . number_format($limitAmount, 0, ',', '.') . ".",
            'description'  => $desc,
            'limit_amount' => $limitAmount,
        ];
    }

    protected function toolHapusBudget(User $user): array
    {
        $deleted = $this->budgetService->deleteBudget($user);

        if ($deleted) {
            return [
                'status' => 'success',
                'pesan'  => 'Plafon budget bulanan berhasil dihapus. Batas pengeluaran telah dinonaktifkan.',
            ];
        }

        return [
            'status' => 'success',
            'pesan'  => 'Anda memang belum memasang batas plafon budget bulanan.',
        ];
    }

    /**
     * Define Tool Schema for Google Gemini REST API.
     */
    protected function getToolDeclarations(): array
    {
        return [
            [
                'function_declarations' => [
                    // 1. Transaksi
                    [
                        'name'        => 'lihat_transaksi',
                        'description' => 'Melihat daftar riwayat transaksi keuangan terbaru dan saldo saat ini (dibatasi rentang waktu maksimal 1 bulan)',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'limit'  => ['type' => 'INTEGER', 'description' => 'Jumlah transaksi yang ingin dilihat di chat (default 5, max 10)'],
                                'type'   => ['type' => 'STRING', 'enum' => ['EXPENSE', 'INCOME'], 'description' => 'Filter tipe: EXPENSE (pengeluaran) atau INCOME (pemasukan)'],
                                'period' => ['type' => 'STRING', 'enum' => ['today', 'month'], 'description' => 'Filter periode: today (hari ini) atau month (bulan ini). Riwayat chat dibatasi maksimal 1 bulan terakhir.'],
                            ],
                        ],
                    ],
                    [
                        'name'        => 'catat_transaksi',
                        'description' => 'Mencatat pengeluaran atau pemasukan baru ke dalam catatan keuangan',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'type'        => ['type' => 'STRING', 'enum' => ['EXPENSE', 'INCOME'], 'description' => 'Tipe transaksi: EXPENSE (pengeluaran) atau INCOME (pemasukan)'],
                                'amount'      => ['type' => 'INTEGER', 'description' => 'Nominal uang Rupiah (contoh: 25000, 500000)'],
                                'description' => ['type' => 'STRING', 'description' => 'Deskripsi/keterangan transaksi (contoh: makan siang padang, gaji bulanan, bensin pertalite)'],
                                'wallet_name' => ['type' => 'STRING', 'description' => 'Nama dompet atau rekening yang digunakan (default: Cash, atau pilih: BCA, GoPay, Mandiri, dll)'],
                                'goal_name'   => ['type' => 'STRING', 'description' => 'Nama target tabungan jika transaksi ini bertujuan untuk menabung ke target impian aktif (opsional, contoh: Beli Motor, Umroh, Dana Darurat)'],
                                'budget_name' => ['type' => 'STRING', 'description' => 'Nama pos anggaran jika pengeluaran ini memotong anggaran belanja tertentu (opsional, contoh: Anggaran Makan, Plafon Belanja Bulanan)'],
                            ],
                            'required'   => ['type', 'amount', 'description'],
                        ],
                    ],
                    [
                        'name'        => 'koreksi_transaksi',
                        'description' => 'Mengubah nominal atau deskripsi transaksi terakhir atau transaksi tertentu',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'transaction_id_or_keyword' => ['type' => 'STRING', 'description' => 'ID transaksi atau kata kunci deskripsi transaksi yang ingin dikoreksi'],
                                'new_amount'                => ['type' => 'INTEGER', 'description' => 'Nominal baru dalam Rupiah (opsional)'],
                                'new_description'           => ['type' => 'STRING', 'description' => 'Deskripsi baru (opsional)'],
                            ],
                        ],
                    ],
                    [
                        'name'        => 'hapus_transaksi',
                        'description' => 'Membatalkan atau menghapus transaksi keuangan',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'transaction_id_or_keyword' => ['type' => 'STRING', 'description' => 'ID transaksi atau kata kunci transaksi yang ingin dihapus'],
                            ],
                        ],
                    ],

                    // 2. Dompet
                    [
                        'name'        => 'lihat_dompet',
                        'description' => 'Melihat daftar semua dompet, rekening, atau e-wallet beserta saldonya',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => (object) [],
                        ],
                    ],
                    [
                        'name'        => 'tambah_dompet',
                        'description' => 'Menambahkan dompet atau rekening baru (contoh: BCA, Mandiri, Kas, GoPay)',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'name'            => ['type' => 'STRING', 'description' => 'Nama dompet baru'],
                                'initial_balance' => ['type' => 'INTEGER', 'description' => 'Saldo awal dompet dalam Rupiah (opsional)'],
                            ],
                            'required'   => ['name'],
                        ],
                    ],
                    [
                        'name'        => 'ubah_dompet',
                        'description' => 'Mengubah nama atau menyesuaikan saldo dompet/rekening yang sudah ada (contoh: ganti nama dompet seabang jadi seabank, atau atur saldo dompet Cash jadi 500rb)',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'old_name'    => ['type' => 'STRING', 'description' => 'Nama dompet yang ingin diubah'],
                                'new_name'    => ['type' => 'STRING', 'description' => 'Nama baru untuk dompet (opsional)'],
                                'new_balance' => ['type' => 'INTEGER', 'description' => 'Saldo baru jika ingin mengoreksi saldo dompet (opsional)'],
                            ],
                            'required'   => ['old_name'],
                        ],
                    ],
                    [
                        'name'        => 'hapus_dompet',
                        'description' => 'Menghapus dompet/rekening tertentu dan mengamankan sisa saldonya',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'wallet_name'             => ['type' => 'STRING', 'description' => 'Nama dompet yang ingin dihapus'],
                                'transfer_to_wallet_name' => ['type' => 'STRING', 'description' => 'Nama dompet tujuan untuk menampung sisa saldo (opsional)'],
                            ],
                            'required'   => ['wallet_name'],
                        ],
                    ],
                    [
                        'name'        => 'transfer_dompet',
                        'description' => 'Memindahkan saldo atau uang dari satu dompet ke dompet lainnya',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'from_wallet' => ['type' => 'STRING', 'description' => 'Nama dompet asal pengirim'],
                                'to_wallet'   => ['type' => 'STRING', 'description' => 'Nama dompet tujuan penerima'],
                                'amount'      => ['type' => 'INTEGER', 'description' => 'Nominal transfer dalam Rupiah'],
                            ],
                            'required'   => ['from_wallet', 'to_wallet', 'amount'],
                        ],
                    ],

                    // 3. Target Tabungan
                    [
                        'name'        => 'lihat_target',
                        'description' => 'Melihat daftar target tabungan impian (goals) dan progress pencapaiannya',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => (object) [],
                        ],
                    ],
                    [
                        'name'        => 'buat_target',
                        'description' => 'Membuat target tabungan impian baru (contoh: Beli Motor 25jt, Liburan Bali 5jt)',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'name'          => ['type' => 'STRING', 'description' => 'Nama target impian'],
                                'target_amount' => ['type' => 'INTEGER', 'description' => 'Nominal target dalam Rupiah'],
                                'target_date'   => ['type' => 'STRING', 'description' => 'Target tanggal pencapaian format YYYY-MM-DD (opsional)'],
                            ],
                            'required'   => ['name', 'target_amount'],
                        ],
                    ],
                    [
                        'name'        => 'tabung_target',
                        'description' => 'Menyetor atau menabung sejumlah uang ke target tabungan impian tertentu',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'goal_name'   => ['type' => 'STRING', 'description' => 'Nama target tabungan tujuan'],
                                'amount'      => ['type' => 'INTEGER', 'description' => 'Nominal uang yang disetor dalam Rupiah'],
                                'wallet_name' => ['type' => 'STRING', 'description' => 'Nama dompet sumber uang (opsional)'],
                            ],
                            'required'   => ['goal_name', 'amount'],
                        ],
                    ],
                    [
                        'name'        => 'ubah_target',
                        'description' => 'Mengubah nama, nominal target, atau tanggal target tabungan impian',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'goal_name'         => ['type' => 'STRING', 'description' => 'Nama target yang ingin diubah'],
                                'new_name'          => ['type' => 'STRING', 'description' => 'Nama baru target (opsional)'],
                                'new_target_amount' => ['type' => 'INTEGER', 'description' => 'Nominal target baru dalam Rupiah (opsional)'],
                                'new_target_date'   => ['type' => 'STRING', 'description' => 'Tanggal target baru YYYY-MM-DD (opsional)'],
                            ],
                            'required'   => ['goal_name'],
                        ],
                    ],
                    [
                        'name'        => 'hapus_target',
                        'description' => 'Menghapus atau membatalkan target tabungan (dana yang terkumpul otomatis dikembalikan ke dompet)',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'goal_name' => ['type' => 'STRING', 'description' => 'Nama target yang ingin dihapus'],
                            ],
                            'required'   => ['goal_name'],
                        ],
                    ],

                    // 4. Plafon Budget
                    [
                        'name'        => 'lihat_budget',
                        'description' => 'Mengecek status plafon anggaran bulanan, total pengeluaran, dan sisa budget',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => (object) [],
                        ],
                    ],
                    [
                        'name'        => 'atur_budget',
                        'description' => 'Mengatur batas maksimal pengeluaran bulanan (plafon budget bulanan) dengan deskripsi/keterangan',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'limit_amount' => ['type' => 'INTEGER', 'description' => 'Batas maksimal pengeluaran bulanan dalam Rupiah (contoh: 2000000)'],
                                'description'  => ['type' => 'STRING', 'description' => 'Deskripsi atau nama batas anggaran (contoh: Batas Belanja Bulanan, Jajan & Hiburan)'],
                            ],
                            'required'   => ['limit_amount', 'description'],
                        ],
                    ],
                    [
                        'name'        => 'hapus_budget',
                        'description' => 'Menghapus atau mereset plafon budget bulanan (menonaktifkan batas belanja)',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => (object) [],
                        ],
                    ],
                ],
            ],
        ];
    }
}
