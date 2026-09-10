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
 * Run: php artisan test tests/Feature/FinanceServiceTest.php
 */

uses(RefreshDatabase::class);

function service(): FinanceService
{
    static $instance = null;
    return $instance ??= new FinanceService();
}

function user(): User
{
    return User::where('jid', '6281234567890@s.whatsapp.net')->first();
}

beforeEach(function () {
    // Seed default categories for tests
    Artisan::call('db:seed', ['--class' => 'CategorySeeder']);

    // Create a test user
    User::firstOrCreate(
        ['jid' => '6281234567890@s.whatsapp.net'],
        ['display_name' => 'Test User', 'current_balance' => 0]
    );
});

// =============================================================================
// USER ONBOARDING
// =============================================================================

test('findOrCreateUser creates new user on first message', function () {
    $jid = '6289012345678@s.whatsapp.net';
    $created = service()->findOrCreateUser($jid, 'Budi Santoso');

    expect($created->jid)->toBe($jid)
        ->and($created->display_name)->toBe('Budi Santoso')
        ->and($created->current_balance)->toBe(0);
});

test('findOrCreateUser returns existing user on second message', function () {
    $u = user();
    $user1 = service()->findOrCreateUser($u->jid, 'Test User');
    $user2 = service()->findOrCreateUser($u->jid, 'Updated Name');

    // Should be same record, name NOT overwritten
    expect($user1->jid)->toBe($user2->jid)
        ->and(User::where('jid', $u->jid)->count())->toBe(1);
});

// =============================================================================
// EXPENSE RECORDING — FR-006, FR-007
// =============================================================================

test('records expense and decrements balance atomically', function () {
    $u = user();
    $initialBalance = $u->current_balance; // 0
    $expenseAmount  = 35000;

    $transaction = service()->recordTransaction($u, [
        'type'        => 'EXPENSE',
        'amount'      => $expenseAmount,
        'description' => 'Makan siang',
    ]);

    $u->refresh();

    expect($transaction->type)->toBe('EXPENSE')
        ->and($transaction->amount)->toBe($expenseAmount)
        ->and($transaction->balance_after)->toBe($initialBalance - $expenseAmount)
        ->and($u->current_balance)->toBe($initialBalance - $expenseAmount)
        ->and($transaction->status)->toBe('ACTIVE');
});

// =============================================================================
// INCOME RECORDING
// =============================================================================

test('records income and increments balance atomically', function () {
    $u = user();
    $incomeAmount = 500000;

    $transaction = service()->recordTransaction($u, [
        'type'        => 'INCOME',
        'amount'      => $incomeAmount,
        'description' => 'Gaji bulanan',
    ]);

    $u->refresh();

    expect($transaction->type)->toBe('INCOME')
        ->and($transaction->amount)->toBe($incomeAmount)
        ->and($transaction->balance_after)->toBe($incomeAmount)
        ->and($u->current_balance)->toBe($incomeAmount);
});

// =============================================================================
// SEQUENTIAL BALANCE ACCURACY
// =============================================================================

test('multiple transactions accumulate balance correctly', function () {
    $u = user();
    service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 1000000, 'description' => 'Gaji']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 35000,   'description' => 'Makan']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 50000,   'description' => 'Bensin']);
    service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 200000,  'description' => 'Freelance']);

    $u->refresh();
    $expectedBalance = 1000000 - 35000 - 50000 + 200000; // 1,115,000

    expect($u->current_balance)->toBe($expectedBalance);
});

// =============================================================================
// UNDO / VOID — FR-013
// =============================================================================

test('voids the latest transaction and refunds balance', function () {
    $u = user();
    service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 500000, 'description' => 'Gaji']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 35000,  'description' => 'Makan']);

    $u->refresh();
    $balanceBefore = $u->current_balance; // 465,000

    $undoResult = service()->undoLastTransaction($u);

    $u->refresh();

    expect($undoResult['voided']->status)->toBe('VOIDED')
        ->and($u->current_balance)->toBe($balanceBefore + 35000); // balance restored
});

test('returns null when no active transactions to undo', function () {
    $result = service()->undoLastTransaction(user());
    expect($result)->toBeNull();
});

// =============================================================================
// SUMMARY CALCULATIONS — FR-008
// =============================================================================

test('getBalance returns current balance', function () {
    $u = user();
    service()->recordTransaction($u, ['type' => 'INCOME', 'amount' => 500000, 'description' => 'Test']);

    $balance = service()->getBalance($u);
    expect($balance)->toBe(500000);
});

test('getMonthlySummary returns correct income/expense/savings totals', function () {
    $u = user();
    service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 3000000, 'description' => 'Gaji']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 50000,   'description' => 'Makan']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 100000,  'description' => 'Transport']);

    $summary = service()->getMonthlySummary($u, now()->year, now()->month);

    expect($summary['total_income'])->toBe(3000000)
        ->and($summary['total_expense'])->toBe(150000)
        ->and($summary['net_savings'])->toBe(2850000);
});

test('getDailySummary returns only today\'s transactions', function () {
    $u = user();
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 25000, 'description' => 'Kopi']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 15000, 'description' => 'Parkir']);

    $summary = service()->getDailySummary($u, now()->toDateString());

    expect($summary['total_expense'])->toBe(40000)
        ->and($summary['transaction_count'])->toBe(2);
});

// =============================================================================
// CUSTOM CATEGORY MANAGEMENT — FR-005
// =============================================================================

test('creates a custom user category', function () {
    $u = user();
    $category = service()->addCustomCategory($u, 'Investasi', 'EXPENSE', '📈');

    expect($category->name)->toBe('Investasi')
        ->and($category->user_jid)->toBe($u->jid)
        ->and($category->is_default)->toBeFalse();
});

test('lists categories includes system defaults and user customs', function () {
    $u = user();
    service()->addCustomCategory($u, 'Kucing', 'EXPENSE', '🐱');

    $categories = service()->getCategoriesForUser($u);

    $names = $categories->pluck('name')->toArray();

    expect($names)->toContain('Makanan & Minuman') // system default
        ->and($names)->toContain('Kucing');          // user custom
});

test('prevents duplicate category name for same user', function () {
    $u = user();
    service()->addCustomCategory($u, 'Investasi', 'EXPENSE', '📈');

    expect(fn () => service()->addCustomCategory($u, 'Investasi', 'EXPENSE', '📈'))
        ->toThrow(\Exception::class);
});

// =============================================================================
// TRANSACTION LISTING & LOOKUP — FR-015
// =============================================================================

test('getTransactions filters by type and period', function () {
    $u = user();
    service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 1000000, 'description' => 'Gaji']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 50000,   'description' => 'Makan']);
    service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 20000,   'description' => 'Kopi']);

    // Filter EXPENSE
    $expenses = service()->getTransactions($u, 'EXPENSE', null, 10);
    expect($expenses)->toHaveCount(2)
        ->and($expenses->first()->type)->toBe('EXPENSE');

    // Filter INCOME
    $incomes = service()->getTransactions($u, 'INCOME', null, 10);
    expect($incomes)->toHaveCount(1)
        ->and($incomes->first()->type)->toBe('INCOME');

    // Filter today
    $today = service()->getTransactions($u, null, 'today', 10);
    expect($today)->toHaveCount(3);
});

test('findTransactionById supports full UUID and unique prefix', function () {
    $u = user();
    $tx = service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 45000, 'description' => 'Buku']);

    // Full UUID
    $foundFull = service()->findTransactionById($u, $tx->id);
    expect($foundFull)->not->toBeNull()
        ->and($foundFull->id)->toBe($tx->id);

    // Prefix 8 characters
    $prefix = substr($tx->id, 0, 8);
    $foundPrefix = service()->findTransactionById($u, $prefix);
    expect($foundPrefix)->not->toBeNull()
        ->and($foundPrefix->id)->toBe($tx->id);

    // Empty or whitespace
    expect(service()->findTransactionById($u, ''))->toBeNull();
    expect(service()->findTransactionById($u, '   '))->toBeNull();
});

// =============================================================================
// TRANSACTION UPDATE & VOID WITH LEDGER RECALCULATION
// =============================================================================

test('updateTransaction modifies amount and recalculates running ledger snapshots', function () {
    $u = user();
    $t1 = service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 1000000, 'description' => 'Gaji']);
    $t2 = service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 200000,  'description' => 'Belanja']);
    $t3 = service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 100000,  'description' => 'Makan']);

    $u->refresh();
    expect($u->current_balance)->toBe(700000);

    // Change t2 from 200.000 to 300.000
    $result = service()->updateTransaction($u, $t2->id, 300000, 'Belanja Bulanan');

    expect($result)->not->toBeNull()
        ->and($result['new_balance'])->toBe(600000);

    $u->refresh();
    expect($u->current_balance)->toBe(600000);

    // Verify sequential balance_after snapshots are corrected
    $t1->refresh();
    $t2->refresh();
    $t3->refresh();

    expect($t1->balance_after)->toBe(1000000)
        ->and($t2->balance_after)->toBe(700000)  // 1.000.000 - 300.000
        ->and($t3->balance_after)->toBe(600000); // 700.000 - 100.000
});

test('voidTransaction marks status VOIDED and recalculates ledger snapshots', function () {
    $u = user();
    $t1 = service()->recordTransaction($u, ['type' => 'INCOME',  'amount' => 1000000, 'description' => 'Gaji']);
    $t2 = service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 200000,  'description' => 'Belanja']);
    $t3 = service()->recordTransaction($u, ['type' => 'EXPENSE', 'amount' => 100000,  'description' => 'Makan']);

    // Void t2
    $result = service()->voidTransaction($u, $t2->id);

    expect($result)->not->toBeNull()
        ->and($result['voided']->status)->toBe('VOIDED')
        ->and($result['new_balance'])->toBe(900000);

    $u->refresh();
    expect($u->current_balance)->toBe(900000);

    $t3->refresh();
    expect($t3->balance_after)->toBe(900000); // 1.000.000 - 100.000 (t2 ignored because voided)
});
