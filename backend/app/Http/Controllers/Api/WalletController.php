<?php

namespace App\Http\Controllers\Api;

use App\Models\Wallet;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WalletController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService
    ) {}

    /**
     * GET /api/v1/wallets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = Wallet::where('user_jid', $user->jid)->orderBy('created_at')->get();

        if ($wallets->isEmpty()) {
            $this->financeService->getOrCreateDefaultWallet($user);
            $wallets = Wallet::where('user_jid', $user->jid)->get();
        }

        $items = [];
        $totalBalance = 0;
        foreach ($wallets as $wallet) {
            $bal = $wallet->balance;
            $totalBalance += $bal;
            $items[] = [
                'id'         => $wallet->id,
                'name'       => $wallet->name,
                'type'       => $wallet->type,
                'is_default' => $wallet->is_default,
                'balance'    => $bal,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'total_balance' => $totalBalance,
                'wallets'       => $items,
            ],
        ]);
    }

    /**
     * POST /api/v1/wallets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'nullable|string|in:CASH,BANK,EWALLET,OTHER',
            'initial_balance' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();

        try {
            $wallet = $this->financeService->addWallet(
                $user,
                $validated['name'],
                (int) ($validated['initial_balance'] ?? 0),
                $validated['type'] ?? 'OTHER'
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'         => $wallet->id,
                    'name'       => $wallet->name,
                    'type'       => $wallet->type,
                    'is_default' => $wallet->is_default,
                    'balance'    => $wallet->balance,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/wallets/transfer
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_wallet_id' => 'required|uuid|exists:wallets,id',
            'to_wallet_id'   => 'required|uuid|exists:wallets,id|different:from_wallet_id',
            'amount'         => 'required|integer|min:1',
            'description'    => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        try {
            $tx = $this->financeService->transferFunds(
                $user,
                $validated['from_wallet_id'],
                $validated['to_wallet_id'],
                $validated['amount'],
                $validated['description'] ?? ''
            );

            $fromWallet = Wallet::find($validated['from_wallet_id']);
            $toWallet   = Wallet::find($validated['to_wallet_id']);

            return response()->json([
                'success' => true,
                'data'    => [
                    'transaction_id' => $tx->id,
                    'amount'         => (int) $validated['amount'],
                    'from_wallet'    => [
                        'name'        => $fromWallet->name,
                        'new_balance' => $fromWallet->balance,
                    ],
                    'to_wallet'      => [
                        'name'        => $toWallet->name,
                        'new_balance' => $toWallet->balance,
                    ],
                    'total_balance'  => $user->current_balance,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
