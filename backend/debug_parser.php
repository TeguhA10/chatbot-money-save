<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = new App\Services\TransactionParserService();

$tests = [
    'parkir 25ribu',
    'makan 25rb',
    'parkir 25000',
    'keluar 25rb makan siang',
    'dapat 500k uang jajan',
];

foreach ($tests as $t) {
    $result = $parser->parse($t);
    echo "Input: '$t'\n";
    echo "Result: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";
}
