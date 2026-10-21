<?php
// Africa's Talking USSD callback. Set this URL as the callback for your USSD code.
require __DIR__ . '/../src/bootstrap.php';

// Read the variables sent via POST from the gateway
$sessionId   = $_POST['sessionId']   ?? '';
$serviceCode = $_POST['serviceCode'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$text        = $_POST['text']        ?? '';

try {
    $response = handle_ussd($sessionId, $phoneNumber, $text);
} catch (Throwable $e) {
    error_log('[ussd] ' . $e->getMessage());
    $response = 'END ' . t('error', 'en');
}

// Echo the response back to the API
header('Content-type: text/plain');
echo fit($response);

// Send the response now, then do slow work (SMS, provider alerts).
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_flush();
    @flush();
}
run_deferred();
