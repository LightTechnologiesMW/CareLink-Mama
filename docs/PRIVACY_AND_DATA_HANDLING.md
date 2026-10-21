# Privacy and data handling

This repository contains **no real patient information or credentials**. Sample providers are fictional.

## What is stored, and when

| Data | Stored when | Purpose |
|---|---|---|
| Phone number, language, consent time | User taps "I agree" on the consent screen | Send details, reminders and follow-up |
| Reported need category, stage, urgency, district | Same | Service matching and referral |
| Referral and follow-up status | Same | Track whether the user reached care |

Nothing is stored if the user cancels, or uses "Find a service", "Emergency help", "About" or just browses. The system stores what the user **reports**, never a diagnosis.

## Controls in this code

- Consent screen precedes any write; cancelling stores nothing.
- Secrets live in `.env` (git-ignored). Database queries use prepared statements.
- SMS bodies and phone numbers are never written to logs.
- The coordinator view excludes phone numbers.
- Provider alerts include only what is needed to act (stage, need, urgency, client phone) and are sent only after consent.

## Still required before a pilot

- Data protection impact assessment and review against Malawi's data protection law, plus approvals from partners and ethics bodies where applicable.
- Encryption in transit (HTTPS) and at rest, database access controls, backups and a retention/deletion policy.
- A way for users to ask for their data to be deleted.
- Role-based access and use of `audit_log` for any staff interface.
- Clinical sign-off on emergency escalation wording and urgency rules.
- Restrict callbacks to Africa's Talking IP ranges.
