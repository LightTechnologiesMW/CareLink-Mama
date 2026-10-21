# Architecture

```
Basic phone --USSD--> Africa's Talking --HTTPS POST--> public/ussd.php --> src/ussd_flow.php --> MySQL
                                                                                    |
                                                         deferred after response:   +--> SMS to user (details)
                                                                                    +--> SMS alert to provider
cron/send_followups.php --SMS "Did you reach care?"--> user --reply 1/2--> public/sms_incoming.php --> MySQL
```

## Design decisions

- **Stateless USSD**: all state is the `text` string Africa's Talking sends, so there are no server-side sessions to lose.
- **Consent before storage**: nothing is written until the user chooses "I agree". "Find a service" never stores anything.
- **Verified-only matching**: only `providers.verified = 1` rows are recommended; fictional rows are marked `is_sample`.
- **Rule-based matching today**: district + service category. AI classification and GIS ranking are planned and will sit behind the same `find_providers()` interface.
- **No diagnosis**: users select what they are experiencing; the system maps it to a service type and urgency flag, and urgent flags always tell the user to go to a facility now.
- **Slow work after the response**: SMS sending runs after the USSD reply is flushed, so the session never waits on the SMS API.

## Data model (database/carelink.sql)

`regions`, `districts` -> `providers` + `provider_services` (-> `service_categories`) is the directory.
`users` (created only on consent) -> `cases` -> `referrals` and `followups` track each report.
`v_cases_needing_attention` lists unresolved cases for coordinators and deliberately excludes phone numbers.
`audit_log` is reserved for the planned coordinator dashboard.

## Case status lifecycle

`new` (no service found) or `matched` -> `notified` (provider alerted) -> `acknowledged` (planned dashboard) -> `attended` (user replies 1) or `incomplete` (user replies 2 or no reply in 72h) -> `closed`.
