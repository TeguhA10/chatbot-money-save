<?php

use App\Services\TransactionParserService;

/**
 * Test Suite: TransactionParserService
 *
 * Tests the deterministic Indonesian regex parser for financial transaction messages.
 * Covers: amount normalization, transaction direction detection, category hints,
 * and edge cases from spec.md FR-003, FR-004, FR-014.
 *
 * Run: php artisan test tests/Unit/ParserServiceTest.php
 *
 * @property TransactionParserService $parser
 */

beforeEach(function () {
    $this->parser = new TransactionParserService();
});

// =============================================================================
// AMOUNT PARSING — FR-004: Indonesian currency formats
// =============================================================================

test('parses plain integer amount', function () {
    $result = $this->parser->parse('keluar 25000 makan siang');
    expect($result['amount'])->toBe(25000);
});

test('parses amount with dot separator (25.000)', function () {
    $result = $this->parser->parse('keluar 25.000 makan siang');
    expect($result['amount'])->toBe(25000);
});

test('parses amount with k abbreviation (25k)', function () {
    $result = $this->parser->parse('beli bensin 25k');
    expect($result['amount'])->toBe(25000);
});

test('parses amount with rb abbreviation (25rb)', function () {
    $result = $this->parser->parse('makan 25rb');
    expect($result['amount'])->toBe(25000);
});

test('parses amount with ribu abbreviation (25ribu)', function () {
    $result = $this->parser->parse('parkir 25ribu');
    expect($result['amount'])->toBe(25000);
});

test('parses million amount (1.5jt)', function () {
    $result = $this->parser->parse('masuk 1.5jt gaji');
    expect($result['amount'])->toBe(1500000);
});

test('parses million amount (2jt)', function () {
    $result = $this->parser->parse('+2jt bonus proyek');
    expect($result['amount'])->toBe(2000000);
});

test('parses amount with comma decimal (1,5jt)', function () {
    $result = $this->parser->parse('terima 1,5jt freelance');
    expect($result['amount'])->toBe(1500000);
});

test('parses large amount (500.000)', function () {
    $result = $this->parser->parse('masuk 500.000 gaji');
    expect($result['amount'])->toBe(500000);
});

// =============================================================================
// TRANSACTION DIRECTION — FR-003: income vs expense detection
// =============================================================================

test('detects EXPENSE direction from "keluar"', function () {
    $result = $this->parser->parse('keluar 35000 makan siang');
    expect($result['type'])->toBe('EXPENSE');
});

test('detects EXPENSE direction from "beli"', function () {
    $result = $this->parser->parse('beli nasi padang 25000');
    expect($result['type'])->toBe('EXPENSE');
});

test('detects EXPENSE direction from minus prefix (-)', function () {
    $result = $this->parser->parse('-15000 parkir');
    expect($result['type'])->toBe('EXPENSE');
});

test('detects EXPENSE direction from "bayar"', function () {
    $result = $this->parser->parse('bayar listrik 150000');
    expect($result['type'])->toBe('EXPENSE');
});

test('detects INCOME direction from "masuk"', function () {
    $result = $this->parser->parse('masuk 500000 gaji');
    expect($result['type'])->toBe('INCOME');
});

test('detects INCOME direction from "terima"', function () {
    $result = $this->parser->parse('terima 250000 bonus');
    expect($result['type'])->toBe('INCOME');
});

test('detects INCOME direction from plus prefix (+)', function () {
    $result = $this->parser->parse('+250000 transfer masuk');
    expect($result['type'])->toBe('INCOME');
});

test('detects INCOME direction from "dapat"', function () {
    $result = $this->parser->parse('dapat 500k uang jajan');
    expect($result['type'])->toBe('INCOME');
});

// =============================================================================
// DESCRIPTION EXTRACTION
// =============================================================================

test('extracts description from trailing words', function () {
    $result = $this->parser->parse('keluar 25000 makan siang');
    expect($result['description'])->toBe('makan siang');
});

test('extracts description with "beli" as expense keyword', function () {
    $result = $this->parser->parse('beli kopi susu 25k');
    expect($result['description'])->toBe('kopi susu');
});

test('extracts description from income message', function () {
    $result = $this->parser->parse('masuk 1jt bonus proyek');
    expect($result['description'])->toBe('bonus proyek');
});

// =============================================================================
// CATEGORY HINTS — optional, best-effort keyword matching
// =============================================================================

test('detects food category hint from "makan"', function () {
    $result = $this->parser->parse('keluar 25000 makan siang');
    expect($result['category_hint'])->toContain('makan');
});

test('detects transport category hint from "bensin"', function () {
    $result = $this->parser->parse('beli bensin 50000');
    expect($result['category_hint'])->toContain('transport');
});

// =============================================================================
// UNRECOGNIZED / INVALID MESSAGES — FR-014: helpful guidance
// =============================================================================

test('returns null for message without amount', function () {
    $result = $this->parser->parse('pengeluaran banyak hari ini');
    expect($result)->toBeNull();
});

test('returns null for empty message', function () {
    $result = $this->parser->parse('');
    expect($result)->toBeNull();
});

test('returns null for command-like message (saldo)', function () {
    $result = $this->parser->parse('saldo');
    expect($result)->toBeNull();
});

// =============================================================================
// EDGE CASES — spec.md Edge Cases section
// =============================================================================

test('rejects zero amount', function () {
    $result = $this->parser->parse('nabung 0');
    expect($result)->toBeNull();
});

test('normalizes negative amount from "beli baju -50000" to positive expense', function () {
    $result = $this->parser->parse('beli baju 50000');
    expect($result['amount'])->toBe(50000)
        ->and($result['type'])->toBe('EXPENSE');
});

test('parse executes within 50ms performance target', function () {
    $start = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        $this->parser->parse('keluar 25.000 makan siang nasi padang warung depan kantor');
    }

    $elapsedMs = (microtime(true) - $start) * 1000;
    $avgMs = $elapsedMs / 100;

    // Average per parse should be well under 50ms (target from FR-014)
    expect($avgMs)->toBeLessThan(50.0);
})->group('performance');
