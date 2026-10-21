<?php
// Local USSD simulator (no gateway needed):  php tests/simulate.php [+265999000111]
// Type each menu choice and press Enter. Ctrl+C to quit.
require __DIR__ . '/../src/bootstrap.php';

$phone   = $argv[1] ?? '+265999000111';
$session = 'sim-' . bin2hex(random_bytes(4));
$choices = [];

while (true) {
    $text = implode('*', $choices);
    $resp = fit(handle_ussd($session, $phone, $text));
    echo "\n-----------------------------\n" . substr($resp, 4) . "\n(" . mb_strlen($resp) . " chars)\n-----------------------------\n";
    if (str_starts_with($resp, 'END')) {
        run_deferred();
        echo "Session ended.\n";
        break;
    }
    echo '> ';
    $in = trim((string) fgets(STDIN));
    if ($in === '') {
        continue;
    }
    $choices[] = $in;
}
