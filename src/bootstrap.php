<?php
declare(strict_types=1);

/**
 * Loads config, helpers and flow code. Included by every entry point.
 */

function env_load(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

function cfg(string $key, ?string $default = null): ?string
{
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $v = getenv($key);
    return $v === false ? $default : $v;
}

// Local settings 
$local = __DIR__ . '/config.local.php'; // create local file to store db settings and passwords 
if (is_file($local)) {
    $_ENV = array_merge($_ENV, require $local);
}

/** Work to run AFTER the USSD response has been sent (SMS, provider alerts). */
$GLOBALS['deferred'] = [];
function defer(callable $fn): void
{
    $GLOBALS['deferred'][] = $fn;
}
function run_deferred(): void
{
    foreach ($GLOBALS['deferred'] as $fn) {
        try {
            $fn();
        } catch (Throwable $e) {
            error_log('[deferred] ' . $e->getMessage());
        }
    }
    $GLOBALS['deferred'] = [];
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/ussd_flow.php';
