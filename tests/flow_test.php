<?php
// Run: php tests/flow_test.php   (needs the database imported and .env set)
require __DIR__ . '/../src/bootstrap.php';

$fails = 0; $screens = 0; $maxLen = 0;
function run(string $text, string $phone = '+265999000222'): string {
    global $screens, $maxLen, $fails;
    $r = handle_ussd('test', $phone, $text);
    $screens++; $len = mb_strlen($r); $maxLen = max($maxLen, $len);
    if ($len > USSD_MAX_CHARS) { $fails++; echo "TOO LONG ($len): [$text]\n"; }
    if (!preg_match('/^(CON|END) /', $r)) { $fails++; echo "BAD PREFIX: [$text]\n"; }
    return $r;
}
function check(string $name, bool $ok): void {
    global $fails; if (!$ok) $fails++; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
}
function cases_count(): int { return (int) db()->query('SELECT COUNT(*) FROM cases')->fetchColumn(); }

// ---- Length sweep: every screen reachable in both languages
foreach (['1', '2'] as $L) {
    run('');
    run($L);
    foreach (['3', '4', '5'] as $m) run("$L*$m");
    foreach (['1', '2', '3'] as $g) {
        run("$L*1*$g");
        foreach (array_keys(needs()[groups()[$g]]) as $nk) {
            run("$L*1*$g*$nk");
            foreach (['1', '2', '3'] as $reg) {
                run("$L*1*$g*$nk*$reg");
                run("$L*1*$g*$nk*$reg*8");
                run("$L*1*$g*$nk*$reg*8*8");
            }
        }
    }
    run("$L*2");
    foreach (array_keys(find_categories()) as $c) {
        run("$L*2*$c");
        foreach (['1', '2', '3'] as $reg) { run("$L*2*$c*$reg"); run("$L*2*$c*$reg*8"); }
    }
    run("$L*1*1*1*2*4"); // consent screen
}

// ---- Behaviour
$before = cases_count();
check('welcome',            str_starts_with(run(''), 'CON WELCOME TO CARELINK'));
check('main menu EN',       str_contains(run('1'), 'Report a health need'));
check('main menu NY',       str_contains(run('2'), 'Nenani'));
check('emergency is END',   str_starts_with(run('1*3'), 'END EMERGENCY'));
check('urgent warns early', str_contains(run('1*1*1*3'), 'URGENT'));
check('consent shown',      str_contains(run('1*1*1*1*2*4'), '1. I agree'));
check('invalid language',   str_starts_with(run('9'), 'END Invalid'));
check('invalid group',      str_starts_with(run('1*1*9'), 'END Invalid'));
check('paging page 2',      str_contains(run('1*1*1*1*3*8'), 'Mulanje'));
check('paging page 3',      str_contains(run('1*1*1*1*3*8*8'), 'Zomba'));
check('8 on last page bad', str_starts_with(run('1*1*1*1*3*8*8*8'), 'END Invalid'));

run('1*1*1*1*2*4*2');
check('cancel stores nothing', cases_count() === $before);

$r = run('1*1*1*1*2*4*1');
check('routine saved + matched', str_contains($r, 'Ref CL-') && str_contains($r, 'SAMPLE'));
$r = run('1*1*1*3*2*4*1');
check('urgent saved message',    str_contains($r, 'URGENT. Go to'));
$r = run('1*1*3*2*3*8*8*1*1'); // adolescent -> family planning -> Southern -> Zomba -> consent
check('paged district saved',    str_contains($r, 'Ref CL-'));
$r = run('2*1*2*1*2*1*1');       // Chichewa, Dedza (no listed service)
check('no-match is graceful',    str_contains($r, 'Ref CL-') && str_contains($r, 'Sitinapeze'));
check('cases created = 4',       cases_count() === $before + 4);

check('consent recorded',        (int) db()->query("SELECT COUNT(*) FROM users WHERE phone='+265999000222' AND consent_at IS NOT NULL")->fetchColumn() === 1);
check('referral + followup rows', (int) db()->query('SELECT COUNT(*) FROM referrals')->fetchColumn() >= 3
                               && (int) db()->query('SELECT COUNT(*) FROM followups')->fetchColumn() >= 3);
check('status screen',           str_contains(run('1*4'), 'Ref CL-'));
check('status none for new num', str_contains(run('1*4', '+265999000333'), 'No referral'));
check('find shows services',     str_contains(run('1*2*1*2*4'), 'SAMPLE'));
check('find none graceful',      str_contains(run('1*2*1*2*1'), 'No listed service'));
check('sample data hidden when off', (function () {
    $_ENV['ALLOW_SAMPLE_DATA'] = '0';
    $r = run('1*2*1*2*4'); $_ENV['ALLOW_SAMPLE_DATA'] = '1';
    return str_contains($r, 'No listed service');
})());

echo "\nScreens checked: $screens, longest: $maxLen chars (limit " . USSD_MAX_CHARS . ")\n";
echo $fails === 0 ? "ALL TESTS PASSED\n" : "$fails FAILURE(S)\n";
exit($fails === 0 ? 0 : 1);
