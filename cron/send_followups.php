<?php
// Run every 15-30 minutes:  */15 * * * * php /path/to/cron/send_followups.php
require __DIR__ . '/../src/bootstrap.php';

$pdo = db();

// 1) Send due follow-up checks
$rows = $pdo->query(
    "SELECT f.id, c.reference, u.phone, u.language
     FROM followups f
     JOIN cases c ON c.id = f.case_id
     JOIN users u ON u.id = c.user_id
     WHERE f.status = 'pending' AND f.due_at <= NOW()
     ORDER BY f.due_at LIMIT 100"
)->fetchAll();

foreach ($rows as $r) {
    if (send_sms($r['phone'], t('sms_followup', $r['language'], ['ref' => $r['reference']]))) {
        $pdo->prepare("UPDATE followups SET status='sent', sent_at=NOW() WHERE id=:id")->execute([':id' => $r['id']]);
    }
}

// 2) Flag referrals with no reply after 72h as incomplete (coordinator follow-up)
$pdo->exec(
    "UPDATE followups f JOIN cases c ON c.id = f.case_id
     SET f.status = 'missed', c.status = 'incomplete'
     WHERE f.status = 'sent' AND f.sent_at < (NOW() - INTERVAL 72 HOUR)
       AND c.status IN ('matched','notified','acknowledged')"
);

echo 'sent=' . count($rows) . PHP_EOL;
