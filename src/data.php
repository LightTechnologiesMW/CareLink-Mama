<?php
declare(strict_types=1);

const DISTRICTS_PER_PAGE = 6;

/** Menu choice => group key (who needs help). */
function groups(): array
{
    return ['1' => 'PREGNANT', '2' => 'POSTNATAL', '3' => 'ADOLESCENT'];
}

function stage_for_group(string $group): string
{
    return [
        'PREGNANT'   => 'pregnant',
        'POSTNATAL'  => 'postnatal_0_90d',
        'ADOLESCENT' => 'adolescent',
    ][$group];
}

/**
 * Health needs per group. 'category' links to service_categories.code.
 * urgency: routine | priority | urgent
 *
 * The app NEVER diagnoses. Labels describe what the person reports, and the
 * urgency flags below must be reviewed and approved by clinical partners.
 */
function needs(): array
{
    return [
        'PREGNANT' => [
            '1' => ['code' => 'ANC_VISIT',   'category' => 'ANC',          'urgency' => 'routine',
                    'en' => 'Antenatal visit',                      'ny' => 'Kukayezetsa pakati'],
            '2' => ['code' => 'TIRED_PALE',  'category' => 'ANAEMIA',      'urgency' => 'priority',
                    'en' => 'Tired, weak or pale',                  'ny' => 'Kutopa, kufooka, kuyera'],
            '3' => ['code' => 'DANGER_SIGNS_PREG', 'category' => 'URGENT_CARE', 'urgency' => 'urgent',
                    'en' => 'Bad headache, swelling, blurry sight', 'ny' => 'Mutu waukulu, kutupa, kusaona'],
            '4' => ['code' => 'BIRTH_PREP',  'category' => 'BIRTH',        'urgency' => 'routine',
                    'en' => 'Prepare for birth',                    'ny' => 'Kukonzekera kubereka'],
            '5' => ['code' => 'OTHER_PREG',  'category' => 'ANC',          'urgency' => 'routine',
                    'en' => 'Other pregnancy worry',                'ny' => 'Nkhawa zina za pakati'],
        ],
        'POSTNATAL' => [
            '1' => ['code' => 'PNC_MOTHER',  'category' => 'PNC_NEWBORN',  'urgency' => 'routine',
                    'en' => 'My recovery after birth',              'ny' => 'Kuchira kwanga nditabereka'],
            '2' => ['code' => 'NEWBORN_CHECK', 'category' => 'PNC_NEWBORN', 'urgency' => 'routine',
                    'en' => 'Baby check-up or vaccines',            'ny' => 'Kuyeza mwana kapena katemera'],
            '3' => ['code' => 'DANGER_SIGNS_NEWBORN', 'category' => 'URGENT_CARE', 'urgency' => 'urgent',
                    'en' => 'Baby sick, tiny or not feeding',       'ny' => 'Mwana akudwala, wamng\'ono, sakuyamwa'],
        ],
        'ADOLESCENT' => [
            '1' => ['code' => 'SRH_ADVICE',  'category' => 'ADOLESCENT_SRH', 'urgency' => 'routine',
                    'en' => 'Reproductive health advice',           'ny' => 'Uphungu wa thanzo la uchembere'],
            '2' => ['code' => 'FAMILY_PLANNING', 'category' => 'FAMILY_PLANNING', 'urgency' => 'routine',
                    'en' => 'Family planning',                      'ny' => 'Kulera'],
            '3' => ['code' => 'MAYBE_PREGNANT', 'category' => 'ANC',   'urgency' => 'routine',
                    'en' => 'I think I am pregnant',                'ny' => 'Ndikuganiza kuti ndili ndi pakati'],
            '4' => ['code' => 'SUPPORT',     'category' => 'SUPPORT',      'urgency' => 'routine',
                    'en' => 'Talk to someone',                      'ny' => 'Kulankhula ndi munthu wondithandiza'],
        ],
    ];
}

/** "Find a service" menu choice => service_categories.code */
function find_categories(): array
{
    return [
        '1' => 'ANC',
        '2' => 'ANAEMIA',
        '3' => 'BIRTH',
        '4' => 'PNC_NEWBORN',
        '5' => 'ADOLESCENT_SRH',
        '6' => 'FAMILY_PLANNING',
    ];
}

function needs_menu(string $group, string $lang): string
{
    $out = t('needs_title', $lang);
    foreach (needs()[$group] as $key => $n) {
        $out .= "\n{$key}. " . $n[$lang];
    }
    return $out;
}

function districts_for(int $regionId): array
{
    $st = db()->prepare('SELECT id, name FROM districts WHERE region_id = :r ORDER BY name');
    $st->execute([':r' => $regionId]);
    return $st->fetchAll();
}

function district_menu(int $regionId, int $page, string $lang): string
{
    $list  = districts_for($regionId);
    $slice = array_slice($list, $page * DISTRICTS_PER_PAGE, DISTRICTS_PER_PAGE);
    $out   = t('district_title', $lang);
    foreach ($slice as $i => $d) {
        $out .= "\n" . ($i + 1) . '. ' . $d['name'];
    }
    if (($page + 1) * DISTRICTS_PER_PAGE < count($list)) {
        $out .= "\n" . t('more', $lang);
    }
    return $out;
}
