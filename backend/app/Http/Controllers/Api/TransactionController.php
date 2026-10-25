<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * TransactionController
 *
 * REST API for manual transaction management from Vue 3 Web and React Native Expo.
 * Implements paginated listing, creation, and void endpoints.
 */
class TransactionController extends Controller
{
    public function __construct(private readonly FinanceService $financeService) {}

    /**
     * GET /api/v1/transactions
     * Returns paginated transaction list with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Transaction::with(['wallet', 'toWallet'])
            ->where('user_jid', $user->jid)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('type')) {
            $query->where('type', strtoupper($request->type));
        }
        if ($request->filled('wallet_id')) {
            $wId = $request->wallet_id;
            $query->where(function ($q) use ($wId) {
                $q->where('wallet_id', $wId)
                  ->orWhere('to_wallet_id', $wId);
            });
        }
        if ($request->filled('start_date')) {
            $startDate = str_contains($request->start_date, ' ') ? $request->start_date : $request->start_date . ' 00:00:00';
            $query->where('transaction_date', '>=', $startDate);
        }
        if ($request->filled('end_date')) {
            $endDate = str_contains($request->end_date, ' ') ? $request->end_date : $request->end_date . ' 23:59:59';
            $query->where('transaction_date', '<=', $endDate);
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', '%' . $s . '%')
                  ->orWhereHas('wallet', function ($wq) use ($s) {
                      $wq->where('name', 'like', '%' . $s . '%');
                  });
            });
        }
        if (!$request->filled('include_voided')) {
            $query->where('status', 'ACTIVE');
        }

        $limit     = min((int) ($request->limit ?? 20), 100);
        $paginated = $query->paginate($limit);

        // Auto-clamp out-of-range page requests (e.g. when a filter narrows down total pages)
        if ($paginated->total() > 0 && $paginated->currentPage() > $paginated->lastPage()) {
            $paginated = $query->paginate($limit, ['*'], 'page', $paginated->lastPage());
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'items'      => $paginated->map(fn($t) => $this->formatTransaction($t)),
                'pagination' => [
                    'page'        => $paginated->currentPage(),
                    'limit'       => $paginated->perPage(),
                    'total_items' => $paginated->total(),
                    'total_pages' => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/transactions
     * Manually create a transaction from Web/Mobile form.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type'             => 'required|in:EXPENSE,INCOME',
            'amount'           => 'required|integer|min:1',
            'description'      => 'nullable|string|max:255',
            'wallet_id'        => 'nullable|string',
            'transaction_date' => 'nullable|date',
        ]);

        if (empty($validated['wallet_id'])) {
            $defaultWallet = $this->financeService->getOrCreateDefaultWallet($user);
            $validated['wallet_id'] = $defaultWallet->id;
        }

        $transaction = $this->financeService->recordTransaction($user, $validated);

        return response()->json([
            'success' => true,
            'data'    => $this->formatTransaction($transaction->load(['wallet', 'toWallet'])),
            'message' => 'Transaksi berhasil dicatat.',
        ], 201);
    }

    /**
     * PUT /api/v1/transactions/{id}
     * Update an active transaction's amount, description, and wallet.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'amount'      => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'wallet_id'   => 'nullable|string',
        ]);

        $result = $this->financeService->updateTransaction(
            $user,
            $id,
            $validated['amount'],
            $validated['description'],
            $validated['wallet_id'] ?? null
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Transaksi tidak ditemukan atau sudah tidak aktif.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'transaction' => $this->formatTransaction($result['transaction']->load(['wallet', 'toWallet'])),
                'new_balance' => $result['new_balance'],
            ],
            'message' => 'Transaksi berhasil diubah.',
        ]);
    }

    /**
     * DELETE /api/v1/transactions/{id}
     * Void a specific transaction and refund its balance impact with ledger recalculation.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $result = $this->financeService->voidTransaction($user, $id);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Transaksi tidak ditemukan atau sudah dibatalkan.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $result['voided']->id,
                'status'          => 'VOIDED',
                'refunded_amount' => $result['voided']->amount,
                'new_balance'     => $result['new_balance'],
            ],
            'message' => 'Transaksi berhasil dibatalkan.',
        ]);
    }

    private function formatTransaction(Transaction $t): array
    {
        return [
            'id'               => $t->id,
            'type'             => $t->type,
            'amount'           => $t->amount,
            'description'      => $t->description,
            'balance_after'    => $t->balance_after,
            'transaction_date' => $t->transaction_date?->toISOString(),
            'status'           => $t->status,
            'wallet_id'        => $t->wallet_id,
            'wallet_name'      => $t->wallet?->name ?? 'Cash',
            'wallet_type'      => $t->wallet?->type ?? 'CASH',
            'to_wallet_id'     => $t->to_wallet_id,
            'to_wallet_name'   => $t->toWallet?->name,
        ];
    }
}
