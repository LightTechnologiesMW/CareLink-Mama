<?php
declare(strict_types=1);

/** Translate a string key. $lang is 'en' or 'ny' (Chichewa). */
function t(string $key, string $lang = 'en', array $vars = []): string
{
    static $S = null;
    if ($S === null) {
        $S = require __DIR__ . '/strings.php';
    }
    $s = $S[$key][$lang] ?? $S[$key]['en'] ?? $key;
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}
