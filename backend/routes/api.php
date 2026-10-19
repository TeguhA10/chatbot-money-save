<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\PaymentCheckoutController;
use App\Http\Controllers\Api\SimulatorController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;
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
// Webhook (Internal Baileys sidecar & Meta WhatsApp Cloud API)
// ---------------------------------------------------------------------------
Route::get('/webhook/whatsapp', [WebhookController::class, 'verifyMetaWebhook'])
    ->name('api.webhook.whatsapp.verify');
Route::middleware('gateway')->post('/webhook/whatsapp', [WebhookController::class, 'handle'])
    ->name('api.webhook.whatsapp');
Route::post('/webhook/midtrans', [MidtransWebhookController::class, 'handle'])->name('api.webhook.midtrans');
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::middleware('gateway')->get('/subscription/status', [SubscriptionController::class, 'status']);
Route::middleware('gateway')->post('/subscription/purchase', [SubscriptionController::class, 'purchase']);

// ---------------------------------------------------------------------------
// Public Authentication Routes & Protected API (Rate Limited)
// ---------------------------------------------------------------------------
Route::prefix('v1')->middleware('throttle:60,1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/request-otp', [AuthController::class, 'requestOtp'])->name('api.auth.request-otp');
        Route::post('/verify-otp',  [AuthController::class, 'verifyOtp'])->name('api.auth.verify-otp');
    });

    // Public In-Chat Payment Checkout & Status
    Route::prefix('payment')->group(function () {
        Route::get('/order/{orderId}',     [PaymentCheckoutController::class, 'show'])->name('api.payment.show');
        Route::post('/simulate/{orderId}', [PaymentCheckoutController::class, 'simulate'])->name('api.payment.simulate');
    });

    // Public Web Simulator (Interactive Demo with 3-attempt limit)
    Route::prefix('simulator')->group(function () {
        Route::get('/status',   [SimulatorController::class, 'status'])->name('api.simulator.status');
        Route::post('/',        [SimulatorController::class, 'simulate'])->name('api.simulator.simulate');
    });

    // ---------------------------------------------------------------------------
    // Authenticated Routes — Sanctum token required
    // ---------------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Auth management
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // Profile & Balance
        Route::get('/profile', [AuthController::class, 'profile'])->name('api.profile');

        // Zero-Knowledge Security Vault
        Route::prefix('vault')->group(function () {
            Route::get('/status',   [VaultController::class, 'status'])->name('api.vault.status');
            Route::post('/setup',   [VaultController::class, 'setup'])->name('api.vault.setup');
            Route::post('/unlock',  [VaultController::class, 'unlock'])->name('api.vault.unlock');
            Route::post('/lock',    [VaultController::class, 'lock'])->name('api.vault.lock');
            Route::post('/reset',   [VaultController::class, 'reset'])->name('api.vault.reset');
        });

        // Transactions
        Route::get('/transactions',         [TransactionController::class, 'index'])->name('api.transactions.index');
        Route::post('/transactions',        [TransactionController::class, 'store'])->name('api.transactions.store');
        Route::put('/transactions/{id}',    [TransactionController::class, 'update'])->name('api.transactions.update');
        Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('api.transactions.destroy');

        // Categories
        Route::get('/categories',  [CategoryController::class, 'index'])->name('api.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');

        // Budgets
        Route::get('/budgets',  [BudgetController::class, 'index'])->name('api.budgets.index');
        Route::post('/budgets', [BudgetController::class, 'store'])->name('api.budgets.store');

        // Wallets
        Route::get('/wallets',           [WalletController::class, 'index'])->name('api.wallets.index');
        Route::post('/wallets',          [WalletController::class, 'store'])->name('api.wallets.store');
        Route::post('/wallets/transfer', [WalletController::class, 'transfer'])->name('api.wallets.transfer');

        // Financial Goals
        Route::get('/goals',                 [GoalController::class, 'index'])->name('api.goals.index');
        Route::post('/goals',                [GoalController::class, 'store'])->name('api.goals.store');
        Route::post('/goals/{id}/contribute',[GoalController::class, 'contribute'])->name('api.goals.contribute');

        // Analytics Dashboard
        Route::get('/analytics/summary',            [AnalyticsController::class, 'summary'])->name('api.analytics.summary');
        Route::get('/analytics/category-breakdown', [AnalyticsController::class, 'categoryBreakdown'])->name('api.analytics.category-breakdown');
        Route::get('/analytics/monthly-trend',      [AnalyticsController::class, 'monthlyTrend'])->name('api.analytics.monthly-trend');
    });

    // Excel Export via Temporary Signed URL with unique single-use export_id
    Route::get('/export/excel/{export_id}', [ExportController::class, 'excelSigned'])
        ->name('api.export.excel')
        ->middleware('signed');

    // Excel Export via Sanctum Bearer token for authenticated clients
    Route::get('/export/excel', [ExportController::class, 'excel'])
        ->name('api.export.excel.sanctum');
});
