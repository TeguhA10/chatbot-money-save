<?php

namespace Tests;

use App\Services\TransactionParserService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public ?TransactionParserService $parser = null;
}
