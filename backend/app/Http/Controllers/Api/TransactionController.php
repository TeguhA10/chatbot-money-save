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

        $query = Transaction::where('user_jid', $user->jid)
            ->with('category')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('type')) {
            $query->where('type', strtoupper($request->type));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        if (!$request->filled('include_voided')) {
            $query->where('status', 'ACTIVE');
        }

        $limit    = min((int) ($request->limit ?? 20), 100);
        $paginated = $query->paginate($limit);

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
            'category_id'      => 'nullable|uuid',
            'description'      => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
        ]);

        $transaction = $this->financeService->recordTransaction($user, $validated);

        return response()->json([
            'success' => true,
            'data'    => $this->formatTransaction($transaction->load('category')),
            'message' => 'Transaksi berhasil dicatat.',
        ], 201);
    }

    /**
     * DELETE /api/v1/transactions/{id}
     * Void a specific transaction and refund its balance impact.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('user_jid', $user->jid)
            ->where('id', $id)
            ->where('status', 'ACTIVE')
            ->firstOrFail();

        // Use targeted void (not undoLast)
        $amount = $transaction->amount;
        $refund = $transaction->type === 'EXPENSE'
            ? $user->current_balance + $amount
            : $user->current_balance - $amount;

        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $refund, $user) {
            $transaction->update(['status' => 'VOIDED']);
            User::where('jid', $user->jid)->update(['current_balance' => $refund]);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $transaction->id,
                'status'         => 'VOIDED',
                'refunded_amount' => $transaction->amount,
                'new_balance'    => $refund,
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
            'category'         => $t->category ? [
                'id'   => $t->category->id,
                'name' => $t->category->name,
                'icon' => $t->category->icon,
            ] : null,
            'balance_after'    => $t->balance_after,
            'transaction_date' => $t->transaction_date?->toISOString(),
            'status'           => $t->status,
        ];
    }
}
