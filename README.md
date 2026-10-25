# CareLink Mama & Baby

A basic-phone-first health access and referral coordination platform for women and girls in Malawi, built by Light Technologies. People report a reproductive, maternal or newborn health need through a **USSD menu** (no smartphone or internet needed), are matched to a **verified service directory**, receive details by SMS, and are followed up until they reach care.

> **CareLink does not diagnose, treat or replace health workers.** Every urgent pathway tells the user to go to the nearest facility immediately.

**Status: prototype.** Not yet validated in a live pilot. See *Working vs planned* below.

## What works today vs what is planned

| Feature | Status |
|---|---|
| USSD menu (Africa's Talking format), English + Chichewa | **Working**, covered by automated tests |
| Consent screen before anything is stored | **Working** |
| Report a need (pregnant / new mother & baby 0-90 days / adolescent) | **Working** |
| Region and district selection (all 28 districts, paged) | **Working** |
| Matching to verified directory entries by district + service type | **Working** |
| "Find a service" (stores nothing) | **Working** |
| Emergency advice screen, urgent-need warnings | **Working**, wording needs clinical review |
| Case, referral and follow-up records (`database/carelink.sql`) | **Working** |
| "My referral" status screen | **Working** |
| SMS details to user, provider alert, follow-up check, reply handling | **Implemented, not yet tested against a live Africa's Talking account** |
| Coordinator view of unresolved / incomplete referrals (SQL view) | **Working** (no UI yet) |
| Chichewa translations | **Draft**, needs clinical wording and clinical review |
| Voice (IVR) | Planned |
| AI-assisted classification and prioritisation of incomplete referrals | **Working**  |
| GIS / distance-based matching (lat/long columns reserved) | **Working**  |
| Provider/coordinator dashboard (acknowledge referrals, edit directory) | Planned |
| Verified real directory of facilities, NGOs and CHWs | Planned (needs partners) |

## Requirements

PHP 8.1+ with `pdo_mysql`, `mbstring`, `curl`; MySQL 8 or MariaDB 10.5+.

## Setup

```bash
# 1. Database (creates `carelink`, schema, districts and FICTIONAL sample providers)
mysql -u root -p < database/carelink.sql
mysql -u root -p -e "CREATE USER 'carelink'@'localhost' IDENTIFIED BY 'strong_password';
                     GRANT ALL ON carelink.* TO 'carelink'@'localhost';"

# 2. Config
cp .env.example .env      # then edit DB_PASS etc. Never commit .env

# 3. Try it locally, no gateway needed
php tests/simulate.php            # interactive USSD simulator
php tests/flow_test.php           # automated checks (writes test rows: use a dev database)

# 4. Serve it
php -S 0.0.0.0:8000 -t public     # dev only. Use nginx/Apache + PHP-FPM + HTTPS in production
```

### Connect Africa's Talking

1. Create a USSD channel (sandbox first) and set its callback URL to `https://your-domain/ussd.php`.
2. For SMS: set `SMS_ENABLED=1`, `AT_USERNAME`, `AT_API_KEY` (and `AT_SENDER_ID` in production), and set the incoming-SMS callback to `https://your-domain/sms_incoming.php`.
3. Schedule the follow-up job: `*/15 * * * * php /path/to/cron/send_followups.php`
4. Restrict `ussd.php` and `sms_incoming.php` to Africa's Talking IP ranges at the web-server or firewall level (their callbacks are not signed).

## How the USSD flow works

Africa's Talking POSTs `sessionId`, `serviceCode`, `phoneNumber` and `text`. `text` holds every choice so far joined by `*`, and we reply `CON ...` (continue) or `END ...` (finish). Each screen stays under the 182-character limit (checked by the tests).

```
""            Language (English / Chichewa)
L             Main menu
L*1           Report a health need
              L*1*group*need*region*[8*]district*consent
L*2           Find a service
              L*2*category*region*[8*]district
L*3           Emergency advice
L*4           My referral status
L*5           About & privacy
```

Entry point: `public/ussd.php`. Menu logic: `src/ussd_flow.php`. Menus and needs: `src/data.php`. Text (EN/NY): `src/strings.php`.

## Repository layout

```
public/ussd.php            Africa's Talking USSD callback
public/sms_incoming.php    Incoming SMS callback (follow-up replies)
src/ussd_flow.php          Menu state machine
src/services.php           Directory matching, case/referral/follow-up records
src/data.php               Groups, needs, urgency flags, district menus
src/strings.php            All user-facing text, English + Chichewa
src/sms.php                Africa's Talking SMS helper
cron/send_followups.php    Sends follow-up checks, flags incomplete referrals
database/carelink.sql      Schema, districts, categories, fictional sample data
tests/                     Simulator and automated flow tests
docs/                      Architecture and privacy/data-handling
AI Models                  AI classification models and Tests
```

## Before any pilot Light Technologies will do the following)

- Replace sample data: `DELETE FROM providers WHERE is_sample = 1;`, add real providers, set `verified = 1` only after confirming details with the facility, and set `ALLOW_SAMPLE_DATA=0`.
- Have clinicians approve the need categories, urgency flags and emergency wording in `src/data.php` and `src/strings.php`.
- Have native Chichewa speakers review `src/strings.php`.
- Complete privacy and security review, approvals and partner agreements.

## Licence

MIT (see `LICENSE`).
