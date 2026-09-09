<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

/**
 * Test Suite: FinanceService
 *
 * Tests atomic balance mutations, ACID transaction logging, summary calculations,
 * category management, and undo logic from spec.md FR-006, FR-007, FR-008, FR-013.
 *
 * Run: php artisan test tests/Unit/FinanceServiceTest.php
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new FinanceService();

    // Seed default categories for tests
    Artisan::call('db:seed', ['--class' => 'CategorySeeder']);

    // Create a test user
    $this->user = User::create([
        'jid'          => '6281234567890@s.whatsapp.net',
        'display_name' => 'Test User',
    ]);
});

// =============================================================================
// USER ONBOARDING
// =============================================================================

test('findOrCreateUser creates new user on first message', function () {
    $jid = '6289012345678@s.whatsapp.net';
    $user = $this->service->findOrCreateUser($jid, 'Budi Santoso');

    expect($user->jid)->toBe($jid)
        ->and($user->display_name)->toBe('Budi Santoso')
        ->and($user->current_balance)->toBe(0);
});

test('findOrCreateUser returns existing user on second message', function () {
    $jid = $this->user->jid;
    $user1 = $this->service->findOrCreateUser($jid, 'Test User');
    $user2 = $this->service->findOrCreateUser($jid, 'Updated Name');

    // Should be same record, name NOT overwritten
    expect($user1->jid)->toBe($user2->jid)
        ->and(User::where('jid', $jid)->count())->toBe(1);
});

// =============================================================================
// EXPENSE RECORDING — FR-006, FR-007
// =============================================================================

test('records expense and decrements balance atomically', function () {
    $initialBalance = $this->user->current_balance; // 0
    $expenseAmount  = 35000;

    $transaction = $this->service->recordTransaction($this->user, [
        'type'        => 'EXPENSE',
        'amount'      => $expenseAmount,
        'description' => 'Makan siang',
    ]);

    $this->user->refresh();

    expect($transaction->type)->toBe('EXPENSE')
        ->and($transaction->amount)->toBe($expenseAmount)
        ->and($transaction->balance_after)->toBe($initialBalance - $expenseAmount)
        ->and($this->user->current_balance)->toBe($initialBalance - $expenseAmount)
        ->and($transaction->status)->toBe('ACTIVE');
});

// =============================================================================
// INCOME RECORDING
// =============================================================================

test('records income and increments balance atomically', function () {
    $incomeAmount = 500000;

    $transaction = $this->service->recordTransaction($this->user, [
        'type'        => 'INCOME',
        'amount'      => $incomeAmount,
        'description' => 'Gaji bulanan',
    ]);

    $this->user->refresh();

    expect($transaction->type)->toBe('INCOME')
        ->and($transaction->amount)->toBe($incomeAmount)
        ->and($transaction->balance_after)->toBe($incomeAmount)
        ->and($this->user->current_balance)->toBe($incomeAmount);
});

// =============================================================================
// SEQUENTIAL BALANCE ACCURACY
// =============================================================================

test('multiple transactions accumulate balance correctly', function () {
    $this->service->recordTransaction($this->user, ['type' => 'INCOME',  'amount' => 1000000, 'description' => 'Gaji']);
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 35000,   'description' => 'Makan']);
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 50000,   'description' => 'Bensin']);
    $this->service->recordTransaction($this->user, ['type' => 'INCOME',  'amount' => 200000,  'description' => 'Freelance']);

    $this->user->refresh();
    $expectedBalance = 1000000 - 35000 - 50000 + 200000; // 1,115,000

    expect($this->user->current_balance)->toBe($expectedBalance);
});

// =============================================================================
// UNDO / VOID — FR-013
// =============================================================================

test('voids the latest transaction and refunds balance', function () {
    $this->service->recordTransaction($this->user, ['type' => 'INCOME',  'amount' => 500000, 'description' => 'Gaji']);
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 35000,  'description' => 'Makan']);

    $this->user->refresh();
    $balanceBefore = $this->user->current_balance; // 465,000

    $undoResult = $this->service->undoLastTransaction($this->user);

    $this->user->refresh();

    expect($undoResult['voided']->status)->toBe('VOIDED')
        ->and($this->user->current_balance)->toBe($balanceBefore + 35000); // balance restored
});

test('returns null when no active transactions to undo', function () {
    $result = $this->service->undoLastTransaction($this->user);
    expect($result)->toBeNull();
});

// =============================================================================
// SUMMARY CALCULATIONS — FR-008
// =============================================================================

test('getBalance returns current balance', function () {
    $this->service->recordTransaction($this->user, ['type' => 'INCOME', 'amount' => 500000, 'description' => 'Test']);

    $balance = $this->service->getBalance($this->user);
    expect($balance)->toBe(500000);
});

test('getMonthlySummary returns correct income/expense/savings totals', function () {
    $this->service->recordTransaction($this->user, ['type' => 'INCOME',  'amount' => 3000000, 'description' => 'Gaji']);
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 50000,   'description' => 'Makan']);
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 100000,  'description' => 'Transport']);

    $summary = $this->service->getMonthlySummary($this->user, now()->year, now()->month);

    expect($summary['total_income'])->toBe(3000000)
        ->and($summary['total_expense'])->toBe(150000)
        ->and($summary['net_savings'])->toBe(2850000);
});

test('getDailySummary returns only today\'s transactions', function () {
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 25000, 'description' => 'Kopi']);
    $this->service->recordTransaction($this->user, ['type' => 'EXPENSE', 'amount' => 15000, 'description' => 'Parkir']);

    $summary = $this->service->getDailySummary($this->user, now()->toDateString());

    expect($summary['total_expense'])->toBe(40000)
        ->and($summary['transaction_count'])->toBe(2);
});

// =============================================================================
// CUSTOM CATEGORY MANAGEMENT — FR-005
// =============================================================================

test('creates a custom user category', function () {
    $category = $this->service->addCustomCategory($this->user, 'Investasi', 'EXPENSE', '📈');

    expect($category->name)->toBe('Investasi')
        ->and($category->user_jid)->toBe($this->user->jid)
        ->and($category->is_default)->toBeFalse();
});

test('lists categories includes system defaults and user customs', function () {
    $this->service->addCustomCategory($this->user, 'Kucing', 'EXPENSE', '🐱');

    $categories = $this->service->getCategoriesForUser($this->user);

    $names = $categories->pluck('name')->toArray();

    expect($names)->toContain('Makanan & Minuman') // system default
        ->and($names)->toContain('Kucing');          // user custom
});

test('prevents duplicate category name for same user', function () {
    $this->service->addCustomCategory($this->user, 'Investasi', 'EXPENSE', '📈');

    expect(fn () => $this->service->addCustomCategory($this->user, 'Investasi', 'EXPENSE', '📈'))
        ->toThrow(\Exception::class);
});
