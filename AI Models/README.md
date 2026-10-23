# CareLink referral-risk model

Helps CareLink coordinators decide **which referrals to follow up first**. It predicts which referrals are likely to go *unconfirmed* (the woman did not confirm she reached care), so limited coordinator time goes where it is most needed.

> **The model assists coordinators. It never diagnoses, never makes clinical decisions, and can never lower a priority set by the clinical rules.**

## What AI does here (and what it deliberately doesn't)

| Task | Approach | Why |
|---|---|---|
| **Urgency of a report** (danger signs) | **Rules** (`src/data.php`), approved by clinicians | Users pick from ~12 menu options, so there is nothing for a model to learn that the rule table doesn't already say. Safety-critical decisions should stay explicit and auditable. |
| **Which referrals are likely to be lost** | **This model** | Real signal exists (service cost, timing, earlier contacts, region) and it's what the application proposes: prioritising incomplete referrals. |
| Urgency from **free text / voice** (planned SMS, WhatsApp, IVR) | Not built yet | Needs clinician-labelled examples in Chichewa and English and clinical validation. See Roadmap. |

## Safety layer (`safety.py`, covered by tests)

- `urgent` rule  -> always **urgent** (contact now), whatever the model says
- `priority` rule -> at least **high** (contact within 24h)
- `routine` rule -> **high** only if model risk >= 0.60, otherwise normal
- The model can **escalate, never downgrade**, and can never create `urgent`.
- No score available -> falls back to rules only.

## Status: pipeline works, no real model yet

The code is tested end to end (export -> train -> score -> import -> coordinator view). The model in this package is trained on **synthetic data with invented relationships**. Its metrics mean **nothing** about real performance and scores carry `model_source = synthetic`. `train.py` **refuses** to train on real data until there are >= 150 labelled cases with >= 30 in each outcome.

**Heads-up:** the planned pilot (about 200 users) may not produce enough *labelled* outcomes to reach that bar, since only matched referrals with a follow-up reply or a 72h timeout get a label. Plan on pooling across pilot phases, and keep using the rules until you have enough data. That's a feature, not a bug: a model trained on 40 cases would be noise.

## Workflow

```bash
pip install -r requirements.txt

# A. Test the pipeline on synthetic data
python make_synthetic_data.py
python train.py --data data/synthetic_cases.csv --source synthetic
python -m unittest discover -s tests

# B. With real pilot data
# 1. phpMyAdmin -> SQL tab -> run export_features.sql -> Export as CSV -> data/real_cases.csv
python train.py --data data/real_cases.csv --source real
# 2. Once, in phpMyAdmin SQL tab: run ai_priority.sql (adds case_risk_scores + v_cases_prioritised)
python score.py --input data/real_cases.csv
# 3. phpMyAdmin -> Import -> outputs/case_scores.sql   (safe to re-run; updates in place)
# 4. Coordinators: SELECT * FROM v_cases_prioritised;
```

The exported CSV contains **no phone numbers** (only the case reference). Treat it as sensitive and keep `data/real_*` and `outputs/` out of GitHub (the `.gitignore` already does this).

Why CSV instead of connecting directly: shared hosting (such as AwardSpace) normally blocks remote database connections and can't run Python, so training and scoring happen on your own computer.

## Files

```
config.py               Features, thresholds, data-size guards
features.py             Turns exported cases into features
safety.py               Rules-as-floor priority logic
make_synthetic_data.py  Invented data for pipeline testing only
train.py                Cross-validated training, group audit, saves model
score.py                Scores cases -> CSV + importable SQL, with plain-language reasons
export_features.sql     Query to export cases (no phone numbers)
ai_priority.sql         case_risk_scores table + coordinator view
tests/test_safety.py    Proves the model can never lower urgency
MODEL_CARD.md           Intended use, data, limits, fairness
```

## Roadmap

1. Collect pilot outcomes; retrain when the data-size guard passes; compare against "rules only" before switching on.
2. Add features once available: distance to facility (GIS), reminder sent/answered, transport cost.
3. Free-text triage for SMS/WhatsApp/IVR (a separate, clinician-validated module; it must escalate only).
4. Monitor drift and per-group performance every retrain (`group_audit` in `metadata.json`).
