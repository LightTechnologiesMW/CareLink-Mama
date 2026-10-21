<?php
declare(strict_types=1);

function sms_enabled(): bool
{
    return cfg('SMS_ENABLED', '0') === '1';
}

/**
 * Send an SMS through Africa's Talking. Returns true on HTTP 2xx.
 * Never log message bodies or phone numbers (health data).
 */
function send_sms(string $to, string $message): bool
{
    if (!sms_enabled()) {
        error_log('[sms] disabled; message not sent (' . strlen($message) . ' chars)');
        return false;
    }
    $base = cfg('AT_ENV', 'sandbox') === 'production'
        ? 'https://api.africastalking.com'
        : 'https://api.sandbox.africastalking.com';

    $fields = ['username' => cfg('AT_USERNAME', ''), 'to' => $to, 'message' => $message];
    if (cfg('AT_SENDER_ID')) {
        $fields['from'] = cfg('AT_SENDER_ID');
    }

    $ch = curl_init($base . '/version1/messaging');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . cfg('AT_API_KEY', ''),
        ],
    ]);
    $out  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($out === false || $code < 200 || $code >= 300) {
        error_log("[sms] send failed, http={$code}");
        return false;
    }
    return true;
}
