<?php

require __DIR__ . '/panel/app/Services/TotpService.php';

use App\Services\TotpService;

$t = new TotpService();

$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // RFC 6238 base32 for "12345678901234567890"

$cases = [
    [59, '287082'],
    [1111111109, '081804'],
    [1111111111, '050471'],
    [1234567890, '005924'],
    [2000000000, '279037'],
    [20000000000, '353130'],
];

foreach ($cases as $c) {
    $time = $c[0];
    $exp = $c[1];
    $code = $t->getCode($secret, $time, 30, 6);
    $ok = $code === $exp;
    echo $time . ' => ' . $code . ' expected ' . $exp . ' ' . ($ok ? 'OK' : 'FAIL') . PHP_EOL;
}

