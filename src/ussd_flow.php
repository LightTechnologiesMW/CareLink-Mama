<?php
declare(strict_types=1);

/**
 * USSD menu logic, Africa's Talking style.
 *
 * Africa's Talking POSTs: sessionId, serviceCode, phoneNumber, text.
 * `text` is every choice made so far joined with "*" (e.g. "1*1*2*3").
 * We reply with "CON ..." (keep session open) or "END ..." (close session).
 *
 * Path map (text => screen):
 *   ""                    language
 *   L                     main menu                (L = 1 English, 2 Chichewa)
 *   L*1*...               report a health need     L*1*group*need*region*[8*]district*consent
 *   L*2*...               find a service           L*2*category*region*[8*]district
 *   L*3                   emergency advice
 *   L*4                   my referral status
 *   L*5                   about & privacy
 */

const USSD_MAX_CHARS = 182;

function fit(string $response): string
{
    if (mb_strlen($response) > USSD_MAX_CHARS) {
        error_log('[ussd] response truncated, length=' . mb_strlen($response));
        return mb_substr($response, 0, USSD_MAX_CHARS);
    }
    return $response;
}

function bad(string $lang = 'en'): string
{
    return 'END ' . t('invalid', $lang);
}

function handle_ussd(string $sessionId, string $phoneNumber, string $text): string
{
    $p = $text === '' ? [] : explode('*', $text);

    // First screen: language
    if (count($p) === 0) {
        return 'CON ' . t('welcome');
    }

    $lang = ['1' => 'en', '2' => 'ny'][$p[0]] ?? null;
    if ($lang === null) {
        return bad();
    }

    if (count($p) === 1) {
        return 'CON ' . t('main_menu', $lang);
    }

    switch ($p[1]) {
        case '1':
            return flow_report($p, $lang, $phoneNumber, $sessionId);
        case '2':
            return flow_find($p, $lang, $phoneNumber);
        case '3':
            return 'END ' . t('emergency', $lang);
        case '4':
            return flow_status($lang, $phoneNumber);
        case '5':
            return 'END ' . t('about', $lang);
        default:
            return bad($lang);
    }
}

/**
 * Walk the district screens starting at index $start. "8" = next page.
 * Returns ['error'=>bool, 'districtId'=>?int, 'page'=>int, 'next'=>int].
 */
function walk_districts(array $p, int $start, int $regionId): array
{
    $list = districts_for($regionId);
    $page = 0;
    $n    = count($p);

    for ($i = $start; $i < $n; $i++) {
        $slice   = array_slice($list, $page * DISTRICTS_PER_PAGE, DISTRICTS_PER_PAGE);
        $hasMore = ($page + 1) * DISTRICTS_PER_PAGE < count($list);

        if ($p[$i] === '8') {
            if (!$hasMore) {
                return ['error' => true];
            }
            $page++;
            continue;
        }
        $idx = ctype_digit($p[$i]) ? (int) $p[$i] : 0;
        if ($idx < 1 || $idx > count($slice)) {
            return ['error' => true];
        }
        return ['error' => false, 'districtId' => (int) $slice[$idx - 1]['id'], 'page' => $page, 'next' => $i + 1];
    }
    return ['error' => false, 'districtId' => null, 'page' => $page, 'next' => $n];
}

function region_id_from(string $choice): int
{
    return ['1' => 1, '2' => 2, '3' => 3][$choice] ?? 0;
}

// ---------------------------------------------------------------------------
// 1. Report a health need
// ---------------------------------------------------------------------------
function flow_report(array $p, string $lang, string $phone, string $sessionId): string
{
    $n = count($p);

    if ($n === 2) {
        return 'CON ' . t('group_menu', $lang);
    }
    $group = groups()[$p[2]] ?? null;
    if ($group === null) {
        return bad($lang);
    }

    if ($n === 3) {
        return 'CON ' . needs_menu($group, $lang);
    }
    $need = needs()[$group][$p[3]] ?? null;
    if ($need === null) {
        return bad($lang);
    }

    if ($n === 4) {
        $warn = $need['urgency'] === 'urgent' ? t('urgent_warn', $lang) . "\n" : '';
        return 'CON ' . $warn . t('region_menu', $lang);
    }
    $regionId = region_id_from($p[4]);
    if ($regionId === 0) {
        return bad($lang);
    }

    $sel = walk_districts($p, 5, $regionId);
    if ($sel['error']) {
        return bad($lang);
    }
    if ($sel['districtId'] === null) {
        return 'CON ' . district_menu($regionId, $sel['page'], $lang);
    }

    // Informed consent BEFORE anything is stored.
    $i = $sel['next'];
    if ($n === $i) {
        return 'CON ' . t('consent', $lang);
    }
    if ($n > $i + 1) {
        return bad($lang);
    }
    if ($p[$i] === '2') {
        return 'END ' . t('cancelled', $lang);
    }
    if ($p[$i] !== '1') {
        return bad($lang);
    }

    $stage  = stage_for_group($group);
    $result = save_case($phone, $lang, $sessionId, $stage, $need, $sel['districtId']);
    $prov   = $result['provider'];

    defer(function () use ($result, $prov, $need, $stage, $phone, $lang) {
        if ($prov) {
            send_sms($phone, sms_for_case($result['ref'], $prov, $need, $lang));
            notify_provider($prov, $result, $need, $stage, $phone);
        }
    });

    if (!$prov) {
        return 'END ' . t('saved_nomatch', $lang, ['ref' => $result['ref']]);
    }
    $key = $need['urgency'] === 'urgent' ? 'saved_urgent' : 'saved_match';
    return 'END ' . t($key, $lang, [
        'ref'   => $result['ref'],
        'name'  => mb_strimwidth($prov['name'], 0, 30, '.'),
        'phone' => $prov['phone'] ?: '-',
    ]);
}

// ---------------------------------------------------------------------------
// 2. Find a service (nothing is stored)
// ---------------------------------------------------------------------------
function flow_find(array $p, string $lang, string $phone): string
{
    $n = count($p);

    if ($n === 2) {
        return 'CON ' . t('find_menu', $lang);
    }
    $category = find_categories()[$p[2]] ?? null;
    if ($category === null) {
        return bad($lang);
    }

    if ($n === 3) {
        return 'CON ' . t('region_menu', $lang);
    }
    $regionId = region_id_from($p[3]);
    if ($regionId === 0) {
        return bad($lang);
    }

    $sel = walk_districts($p, 4, $regionId);
    if ($sel['error'] || $n > $sel['next']) {
        return bad($lang);
    }
    if ($sel['districtId'] === null) {
        return 'CON ' . district_menu($regionId, $sel['page'], $lang);
    }

    $providers = find_providers($sel['districtId'], $category, 3);
    if (!$providers) {
        return 'END ' . t('find_none', $lang);
    }

    $lines = [];
    foreach (array_slice($providers, 0, 2) as $i => $pr) {
        $lines[] = ($i + 1) . '. ' . mb_strimwidth($pr['name'], 0, 28, '.') . ' ' . ($pr['phone'] ?: '');
    }

    defer(function () use ($providers, $phone, $lang) {
        $parts = [];
        foreach ($providers as $pr) {
            $parts[] = $pr['name'] . ', ' . ($pr['address'] ?: '-') . ', Tel ' . ($pr['phone'] ?: '-')
                     . ', ' . ($pr['opening_hours'] ?: '-') . '. ' . cost_text($pr, $lang);
        }
        send_sms($phone, 'CareLink: ' . implode(' | ', $parts));
    });

    return 'END ' . t('find_result', $lang, ['list' => implode("\n", $lines)]);
}

// ---------------------------------------------------------------------------
// 4. My referral
// ---------------------------------------------------------------------------
function flow_status(string $lang, string $phone): string
{
    $c = latest_case($phone);
    if (!$c) {
        return 'END ' . t('status_none', $lang);
    }
    return 'END ' . t('status_line', $lang, [
        'ref'      => $c['reference'],
        'status'   => t('st_' . $c['status'], $lang),
        'provider' => $c['provider_name'] ? mb_strimwidth($c['provider_name'], 0, 40, '.') : '-',
    ]);
}
