<?php
declare(strict_types=1);

/**
 * Service directory matching and case handling.
 * Only providers with verified = 1 are ever recommended.
 */

function find_providers(int $districtId, string $category, int $limit = 3): array
{
    $sql = 'SELECT p.* FROM providers p
            JOIN provider_services ps ON ps.provider_id = p.id
            WHERE p.district_id = :d AND ps.category_code = :c
              AND p.verified = 1 AND p.active = 1';
    if (cfg('ALLOW_SAMPLE_DATA', '0') !== '1') {
        $sql .= ' AND p.is_sample = 0';
    }
    // Free services first, then hospitals/centres before others, then name.
    $sql .= ' ORDER BY p.is_free DESC, p.name LIMIT ' . (int) $limit;

    $st = db()->prepare($sql);
    $st->execute([':d' => $districtId, ':c' => $category]);
    return $st->fetchAll();
}

/**
 * Save a consented report: upsert user, create case, referral and follow-up.
 * Returns ['ref' => string, 'case_id' => int, 'provider' => ?array].
 */
function save_case(string $phone, string $lang, string $sessionId, string $stage, array $need, int $districtId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO users (phone, language, consent_at) VALUES (:p, :l, NOW())
             ON DUPLICATE KEY UPDATE language = VALUES(language), consent_at = NOW()'
        )->execute([':p' => $phone, ':l' => $lang]);

        $st = $pdo->prepare('SELECT id FROM users WHERE phone = :p');
        $st->execute([':p' => $phone]);
        $userId = (int) $st->fetchColumn();

        $provider = find_providers($districtId, $need['category'], 1)[0] ?? null;
        $status   = $provider ? 'matched' : 'new';

        $ref = '';
        for ($i = 0; $i < 5; $i++) {
            $ref = 'CL-' . strtoupper(bin2hex(random_bytes(3)));
            try {
                $pdo->prepare(
                    'INSERT INTO cases
                       (reference, user_id, session_id, stage, need_code, category_code, urgency,
                        district_id, matched_provider_id, status)
                     VALUES (:ref, :u, :s, :stage, :need, :cat, :urg, :d, :prov, :st)'
                )->execute([
                    ':ref' => $ref, ':u' => $userId, ':s' => $sessionId, ':stage' => $stage,
                    ':need' => $need['code'], ':cat' => $need['category'], ':urg' => $need['urgency'],
                    ':d' => $districtId, ':prov' => $provider['id'] ?? null, ':st' => $status,
                ]);
                break;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000' || $i === 4) {
                    throw $e;
                }
            }
        }
        $caseId = (int) $pdo->lastInsertId();

        if ($provider) {
            $pdo->prepare('INSERT INTO referrals (case_id, provider_id) VALUES (:c, :p)')
                ->execute([':c' => $caseId, ':p' => $provider['id']]);

            $hours = $need['urgency'] === 'urgent'
                ? (int) cfg('URGENT_FOLLOWUP_HOURS', '12')
                : (int) cfg('FOLLOWUP_DELAY_HOURS', '48');
            $pdo->prepare(
                'INSERT INTO followups (case_id, due_at) VALUES (:c, DATE_ADD(NOW(), INTERVAL :h HOUR))'
            )->execute([':c' => $caseId, ':h' => $hours]);
        }

        $pdo->commit();
        return ['ref' => $ref, 'case_id' => $caseId, 'provider' => $provider];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Most recent case for a phone number, with provider name. */
function latest_case(string $phone): ?array
{
    $st = db()->prepare(
        'SELECT c.reference, c.status, p.name AS provider_name
         FROM cases c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN providers p ON p.id = c.matched_provider_id
         WHERE u.phone = :p
         ORDER BY c.created_at DESC, c.id DESC LIMIT 1'
    );
    $st->execute([':p' => $phone]);
    return $st->fetch() ?: null;
}

function cost_text(array $p, string $lang): string
{
    if ((int) $p['is_free'] === 1) {
        return t('sms_cost_free', $lang);
    }
    if ((int) $p['is_subsidised'] === 1) {
        return t('sms_cost_sub', $lang);
    }
    return '';
}

/** Full details SMS for the user (sent after the USSD session closes). */
function sms_for_case(string $ref, array $provider, array $need, string $lang): string
{
    return t('sms_case', $lang, [
        'ref'     => $ref,
        'urgent'  => $need['urgency'] === 'urgent' ? t('sms_urgent_prefix', $lang) : '',
        'name'    => $provider['name'],
        'address' => $provider['address'] ?: '-',
        'phone'   => $provider['phone'] ?: '-',
        'hours'   => $provider['opening_hours'] ?: '-',
        'cost'    => cost_text($provider, $lang),
    ]);
}

/** Consent-based, minimal alert to the matched provider (planned: dashboard). */
function notify_provider(array $provider, array $result, array $need, string $stage, string $userPhone): void
{
    if (empty($provider['notify_phone']) || !sms_enabled()) {
        return;
    }
    $msg = sprintf(
        'CareLink referral %s: %s, %s, urgency %s. Client phone %s. Please acknowledge and follow up.',
        $result['ref'], $stage, $need['en'], $need['urgency'], $userPhone
    );
    if (send_sms($provider['notify_phone'], $msg)) {
        $pdo = db();
        $pdo->prepare("UPDATE referrals SET status='notified', notified_at=NOW() WHERE case_id=:c")
            ->execute([':c' => $result['case_id']]);
        $pdo->prepare("UPDATE cases SET status='notified' WHERE id=:c AND status='matched'")
            ->execute([':c' => $result['case_id']]);
    }
}
