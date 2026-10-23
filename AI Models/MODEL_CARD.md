# Model card: CareLink referral-risk model

**Owner:** Light Technologies | **Status:** prototype, not validated on real data

## Intended use
Rank open referrals so coordinators contact the people most likely to be lost to follow-up first. Output is a risk score (0-1), a priority tier and up to three plain-language reasons. It supports staff decisions and is never shown to users.

## Not intended for
Diagnosing or assessing clinical severity; deciding who receives care; lowering or suppressing urgent cases; denying or delaying any service; use on populations or channels it hasn't been validated for.

## Prediction target
`label_incomplete = 1` when a referral is **not confirmed as attended** (user replied "No", or no reply 72h after the follow-up SMS). `0` when the user confirmed attending.

## Known limitations of the label
- **Silence is not proof of non-attendance.** Some women attend and don't reply. The model really predicts "unconfirmed", which is still what coordinators need to chase.
- **Selection bias:** only referrals with a matched provider get a follow-up, so unmatched cases never receive a label. The model can't learn about them; coordinators already see those cases through `status = 'new'`.
- **Self-report:** confirmation is by SMS reply from a basic phone, so reply behaviour (literacy, phone sharing, credit) mixes into the label.

## Features
Stage, need code, rule-based urgency, region, language, provider type, service not free, evening/night report, weekend report, number of earlier cases. No phone numbers, names, or free text.

## Fairness considerations
Region, language and stage can reflect structural disadvantage (distance, literacy, confidentiality barriers for adolescents). Because the model only **adds** outreach and never withholds care, the main risk is being *less accurate* for some groups and so missing them. `train.py` reports cross-validated AUC and recall per stage, region and language (`group_audit` in `metadata.json`); review it at every retrain and do not deploy if a group is clearly worse served. Groups under 30 cases aren't audited and should be treated as unknown.

## Performance
**No real-world performance has been measured.** The included model was trained on synthetic data with invented effects (cross-validated AUC about 0.65, deliberately noisy). Do not quote these figures to funders as evidence. Real metrics will be reported after training on pilot data.

## Safety
`safety.py` makes the clinical rules a floor: urgent stays urgent, priority stays at least high, and the model can never create `urgent`. Seven unit tests enforce this. Emergency wording and urgency rules require clinical sign-off independent of this model.

## Privacy
Training/scoring files contain case references only (no phone numbers) and stay off GitHub. Scores are stored in `case_risk_scores`. Reasons are generic factors, not personal details.

## Retraining and monitoring
Retrain when enough new labelled cases accumulate; compare to rules-only on a held-out period before switching on; re-run the group audit each time; log model version via `metadata.json`.
