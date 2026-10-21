<?php
// Africa's Talking incoming-SMS callback: handles replies to follow-up checks
// ("1" = reached care, "2" = did not). Restrict access to AT's IP ranges.
require __DIR__ . '/../src/bootstrap.php';

$from = $_POST['from'] ?? '';
$text = strtoupper(trim($_POST['text'] ?? ''));
$tok  = preg_split('/\s+/', $text) ?: [];
$ans  = end($tok);

$yes = in_array($ans, ['1', 'YES', 'INDE'], true);
$no  = in_array($ans, ['2', 'NO', 'AYI'], true);

if ($from !== '' && ($yes || $no)) {
    $pdo = db();
    $st  = $pdo->prepare(
        'SELECT c.id, u.language FROM cases c JOIN users u ON u.id = c.user_id
         WHERE u.phone = :p ORDER BY c.created_at DESC, c.id DESC LIMIT 1'
    );
    $st->execute([':p' => $from]);
    if ($case = $st->fetch()) {
        $caseStatus = $yes ? 'attended' : 'incomplete';
        $refStatus  = $yes ? 'attended' : 'not_attended';
        $pdo->prepare('UPDATE cases SET status = :s WHERE id = :c')->execute([':s' => $caseStatus, ':c' => $case['id']]);
        $pdo->prepare('UPDATE referrals SET status = :s, attended_at = IF(:y = 1, NOW(), NULL) WHERE case_id = :c')
            ->execute([':s' => $refStatus, ':y' => $yes ? 1 : 0, ':c' => $case['id']]);
        $pdo->prepare("UPDATE followups SET status='completed', response=:r WHERE case_id=:c AND status IN ('sent','pending')")
            ->execute([':r' => $yes ? 'yes' : 'no', ':c' => $case['id']]);
        send_sms($from, t($yes ? 'sms_followup_ack_yes' : 'sms_followup_ack_no', $case['language']));
    }
}
header('Content-type: text/plain');
echo 'OK';
