<?php
/**
 * All user-facing text. USSD screens must stay within 182 characters.
 * IMPORTANT: Chichewa text is a working draft and MUST be reviewed by a
 * native speaker and the clinical partners before any pilot.
 */
return [
    'welcome' => ['en' => "WELCOME TO CARELINK\nTAKULANDIRANI KU CARELINK\n1. English\n2. Chichewa"],

    'main_menu' => [
        'en' => "CareLink Mama & Baby\n1. Report a health need\n2. Find a service\n3. Emergency help\n4. My referral\n5. About & privacy",
        'ny' => "CareLink Mama & Baby\n1. Nenani vuto la thanzo\n2. Pezani chithandizo\n3. Thandizo ladzidzidzi\n4. Za kutumizidwa kwanga\n5. Za CareLink ndi chinsinsi",
    ],

    'group_menu' => [
        'en' => "Who needs help?\n1. I am pregnant\n2. New mother / baby (0-90 days)\n3. Girl or young woman (reproductive health)",
        'ny' => "Ndani akufuna thandizo?\n1. Ndili ndi pakati\n2. Ndabereka kumene / mwana (masiku 0-90)\n3. Mtsikana kapena mkazi wamng'ono (thanzo la uchembere)",
    ],

    'needs_title' => ['en' => 'What do you need help with?', 'ny' => 'Mukufuna thandizo pa chiyani?'],

    'region_menu' => [
        'en' => "Select region\n1. Northern\n2. Central\n3. Southern",
        'ny' => "Sankhani chigawo\n1. Kumpoto\n2. Pakati\n3. Kummwera",
    ],

    'district_title' => ['en' => 'Select district', 'ny' => 'Sankhani boma'],
    'more'           => ['en' => '8. More', 'ny' => '8. Zambiri'],

    'consent' => [
        'en' => "Your report will be saved and shared with a health provider or health worker to help you.\n1. I agree\n2. Cancel",
        'ny' => "Lipoti lanu lisungidwa ndi kuperekedwa kwa wothandiza zaumoyo kuti akuthandizeni.\n1. Ndikuvomereza\n2. Ayi",
    ],
    'cancelled' => ['en' => 'Cancelled. Nothing was saved.', 'ny' => 'Taletsa. Palibe chomwe chasungidwa.'],

    'urgent_warn' => [
        'en' => 'URGENT: go to the nearest health facility now. Do not wait for SMS.',
        'ny' => 'ZADZIDZIDZI: pitani ku chipatala chapafupi tsopano. Musadikire SMS.',
    ],

    'saved_match' => [
        'en' => "Thank you. Ref {ref}.\nGo to: {name}\nTel {phone}\nDetails by SMS.",
        'ny' => "Zikomo. Ref {ref}.\nPitani ku: {name}\nFoni {phone}\nTikutumiza zambiri pa SMS.",
    ],
    'saved_urgent' => [
        'en' => "URGENT. Go to {name} NOW.\nTel {phone}\nRef {ref}. Do not wait for SMS.",
        'ny' => "ZADZIDZIDZI. Pitani ku {name} TSOPANO.\nFoni {phone}\nRef {ref}. Musadikire SMS.",
    ],
    'saved_nomatch' => [
        'en' => "Thank you. Ref {ref}.\nNo listed service found yet. A CareLink coordinator will follow up by SMS.",
        'ny' => "Zikomo. Ref {ref}.\nSitinapeze malo olembedwa. Wogwirizira wa CareLink akutumizirani uthenga pa SMS.",
    ],

    'find_menu' => [
        'en' => "Find a service\n1. Antenatal care\n2. Anaemia care\n3. Birth/delivery\n4. Newborn & postnatal\n5. Youth-friendly\n6. Family planning",
        'ny' => "Pezani chithandizo\n1. Kukayezetsa pakati\n2. Za kuchepa magazi\n3. Kubereka\n4. Mwana ndi pambuyo pobereka\n5. Chithandizo cha achinyamata\n6. Kulera",
    ],
    'find_result' => [
        'en' => "Services nearby:\n{list}\nDetails by SMS.",
        'ny' => "Malo apafupi:\n{list}\nZambiri pa SMS.",
    ],
    'find_none' => [
        'en' => 'No listed service found in that district yet. Try another district or ask a health worker.',
        'ny' => 'Sitinapeze malo olembedwa m\'boma limenelo. Yesani boma lina kapena funsani wogwira ntchito zaumoyo.',
    ],

    'emergency' => [
        'en' => 'EMERGENCY: Go to nearest health facility NOW for heavy bleeding, fits, severe headache/blurred sight, breathing trouble, or a very weak baby. Do not wait for SMS.',
        'ny' => 'ZADZIDZIDZI: Pitani ku chipatala chapafupi TSOPANO ngati mukukha magazi ambiri, kukomoka, mutu waukulu, kupuma movutika, kapena mwana wafooka. Musadikire SMS.',
    ],

    'status_none' => ['en' => 'No referral found for this number.', 'ny' => 'Palibe kutumizidwa komwe kwapezeka pa nambala iyi.'],
    'status_line' => [
        'en' => "Ref {ref}\nStatus: {status}\nService: {provider}",
        'ny' => "Ref {ref}\nMmene zilili: {status}\nMalo: {provider}",
    ],
    'st_new'          => ['en' => 'Received', 'ny' => 'Talandira'],
    'st_matched'      => ['en' => 'Service found', 'ny' => 'Malo apezeka'],
    'st_notified'     => ['en' => 'Provider alerted', 'ny' => 'Chipatala chadziwitsidwa'],
    'st_acknowledged' => ['en' => 'Provider confirmed', 'ny' => 'Chipatala chatsimikiza'],
    'st_attended'     => ['en' => 'You reached care', 'ny' => 'Mwafika ku chithandizo'],
    'st_incomplete'   => ['en' => 'Needs follow-up', 'ny' => 'Tikutsatira'],
    'st_closed'       => ['en' => 'Closed', 'ny' => 'Yatsekedwa'],

    'about' => [
        'en' => 'CareLink links you to existing services. It does not diagnose or replace health workers. Free to use. We save only what you agree to share.',
        'ny' => 'CareLink imakulumikizani ku chithandizo chomwe chilipo. Siipeza matenda kapena kulowa m\'malo mwa ogwira ntchito zaumoyo. Ndi yaulere. Timasunga zomwe mwavomereza zokha.',
    ],

    'invalid' => ['en' => 'Invalid choice. Please dial again.', 'ny' => 'Mwasankha molakwika. Imbaninso.'],
    'error'   => [
        'en' => 'Sorry, service unavailable. If urgent, go to the nearest health facility.',
        'ny' => 'Pepani, ntchito sikupezeka. Ngati ndi zadzidzidzi, pitani ku chipatala chapafupi.',
    ],

    // ---- SMS ----
    'sms_case' => [
        'en' => 'CareLink ref {ref}. {urgent}Please go to {name}, {address}. Tel {phone}. Hours: {hours}. {cost}CareLink does not diagnose; if you feel worse go to the nearest facility.',
        'ny' => 'CareLink ref {ref}. {urgent}Pitani ku {name}, {address}. Foni {phone}. Nthawi: {hours}. {cost}CareLink siipeza matenda; mukavutika kwambiri pitani ku chipatala chapafupi.',
    ],
    'sms_urgent_prefix' => ['en' => 'URGENT - go now, do not wait. ', 'ny' => 'ZADZIDZIDZI - pitani tsopano. '],
    'sms_cost_free'     => ['en' => 'Listed as free. ', 'ny' => 'Ndi yaulere. '],
    'sms_cost_sub'      => ['en' => 'Subsidised service. ', 'ny' => 'Imathandizidwa ndalama. '],
    'sms_followup' => [
        'en' => 'CareLink: Did you reach the health service (ref {ref})? Reply 1 for Yes, 2 for No.',
        'ny' => 'CareLink: Kodi mwafika ku chipatala (ref {ref})? Yankhani 1 = Inde, 2 = Ayi.',
    ],
    'sms_followup_ack_yes' => ['en' => 'Thank you. We are glad you reached care.', 'ny' => 'Zikomo. Tikondwera kuti mwafika ku chithandizo.'],
    'sms_followup_ack_no'  => ['en' => 'Thank you. A CareLink coordinator will contact you to help.', 'ny' => 'Zikomo. Wogwirizira wa CareLink adzakulumikizani kuti akuthandizeni.'],
];
