<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — WA Finance Bot
|--------------------------------------------------------------------------
|
| Base URL: /api/v1
| All authenticated routes require: Authorization: Bearer <sanctum_token>
|
*/

// ---------------------------------------------------------------------------
// Webhook (Internal — called by Baileys gateway sidecar, not protected by Sanctum)
// ---------------------------------------------------------------------------
Route::middleware('gateway')->post('/webhook/whatsapp', [WebhookController::class, 'handle'])
    ->name('api.webhook.whatsapp');
Route::post('/webhook/midtrans', [MidtransWebhookController::class, 'handle'])->name('api.webhook.midtrans');
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::middleware('gateway')->get('/subscription/status', [SubscriptionController::class, 'status']);
Route::middleware('gateway')->post('/subscription/purchase', [SubscriptionController::class, 'purchase']);

// ---------------------------------------------------------------------------
// Public Authentication Routes
// ---------------------------------------------------------------------------
Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/request-otp', [AuthController::class, 'requestOtp'])->name('api.auth.request-otp');
        Route::post('/verify-otp',  [AuthController::class, 'verifyOtp'])->name('api.auth.verify-otp');
    });

    // ---------------------------------------------------------------------------
    // Authenticated Routes — Sanctum token required
    // ---------------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Profile & Balance
        Route::get('/profile', [AuthController::class, 'profile'])->name('api.profile');

        // Transactions
        Route::get('/transactions',         [TransactionController::class, 'index'])->name('api.transactions.index');
        Route::post('/transactions',        [TransactionController::class, 'store'])->name('api.transactions.store');
        Route::put('/transactions/{id}',    [TransactionController::class, 'update'])->name('api.transactions.update');
        Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('api.transactions.destroy');

        // Categories
        Route::get('/categories',  [CategoryController::class, 'index'])->name('api.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');

        // Budgets
        Route::get('/budgets',  [\App\Http\Controllers\Api\BudgetController::class, 'index'])->name('api.budgets.index');
        Route::post('/budgets', [\App\Http\Controllers\Api\BudgetController::class, 'store'])->name('api.budgets.store');

        // Wallets
        Route::get('/wallets',           [\App\Http\Controllers\Api\WalletController::class, 'index'])->name('api.wallets.index');
        Route::post('/wallets',          [\App\Http\Controllers\Api\WalletController::class, 'store'])->name('api.wallets.store');
        Route::post('/wallets/transfer', [\App\Http\Controllers\Api\WalletController::class, 'transfer'])->name('api.wallets.transfer');

        // Financial Goals
        Route::get('/goals',                 [\App\Http\Controllers\Api\GoalController::class, 'index'])->name('api.goals.index');
        Route::post('/goals',                [\App\Http\Controllers\Api\GoalController::class, 'store'])->name('api.goals.store');
        Route::post('/goals/{id}/contribute',[\App\Http\Controllers\Api\GoalController::class, 'contribute'])->name('api.goals.contribute');

        // Analytics Dashboard
        Route::get('/analytics/summary',            [AnalyticsController::class, 'summary'])->name('api.analytics.summary');
        Route::get('/analytics/category-breakdown', [AnalyticsController::class, 'categoryBreakdown'])->name('api.analytics.category-breakdown');
        Route::get('/analytics/monthly-trend',      [AnalyticsController::class, 'monthlyTrend'])->name('api.analytics.monthly-trend');
    });

    // Excel Export (Accepts either Sanctum Bearer token or WhatsApp user_jid query parameter)
    Route::get('/export/excel', [ExportController::class, 'excel'])->name('api.export.excel');
});
