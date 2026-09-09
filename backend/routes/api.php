<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\TransactionController;
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
// Webhook (Internal — called by Baileys gateway sidecar, not protected by Sanctum)
// ---------------------------------------------------------------------------
Route::post('/webhook/whatsapp', [WebhookController::class, 'handle'])
    ->name('api.webhook.whatsapp');

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
        Route::get('/transactions',     [TransactionController::class, 'index'])->name('api.transactions.index');
        Route::post('/transactions',    [TransactionController::class, 'store'])->name('api.transactions.store');
        Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('api.transactions.destroy');

        // Categories
        Route::get('/categories',  [CategoryController::class, 'index'])->name('api.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');

        // Analytics Dashboard
        Route::get('/analytics/summary',            [AnalyticsController::class, 'summary'])->name('api.analytics.summary');
        Route::get('/analytics/category-breakdown', [AnalyticsController::class, 'categoryBreakdown'])->name('api.analytics.category-breakdown');
        Route::get('/analytics/monthly-trend',      [AnalyticsController::class, 'monthlyTrend'])->name('api.analytics.monthly-trend');
    });

    // Excel Export (Accepts either Sanctum Bearer token or WhatsApp user_jid query parameter)
    Route::get('/export/excel', [ExportController::class, 'excel'])->name('api.export.excel');
});

