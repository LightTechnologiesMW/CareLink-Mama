"""
Generate SYNTHETIC cases to test the pipeline end to end.

!! The relationships below are INVENTED. A model trained on this data says
!! NOTHING about real-world performance. It exists only so the code can be
!! tested before pilot data exists. Never report its metrics as evidence.
"""
import argparse
import numpy as np
import pandas as pd

# code -> (stage, category, urgency). Keep in sync with src/data.php.
NEEDS = {
    "ANC_VISIT": ("pregnant", "ANC", "routine"), "TIRED_PALE": ("pregnant", "ANAEMIA", "priority"),
    "DANGER_SIGNS_PREG": ("pregnant", "URGENT_CARE", "urgent"), "BIRTH_PREP": ("pregnant", "BIRTH", "routine"),
    "OTHER_PREG": ("pregnant", "ANC", "routine"),
    "PNC_MOTHER": ("postnatal_0_90d", "PNC_NEWBORN", "routine"), "NEWBORN_CHECK": ("postnatal_0_90d", "PNC_NEWBORN", "routine"),
    "DANGER_SIGNS_NEWBORN": ("postnatal_0_90d", "URGENT_CARE", "urgent"),
    "SRH_ADVICE": ("adolescent", "ADOLESCENT_SRH", "routine"), "FAMILY_PLANNING": ("adolescent", "FAMILY_PLANNING", "routine"),
    "MAYBE_PREGNANT": ("adolescent", "ANC", "routine"), "SUPPORT": ("adolescent", "SUPPORT", "routine"),
}


def generate(n: int, seed: int) -> pd.DataFrame:
    rng = np.random.default_rng(seed)
    codes = rng.choice(list(NEEDS), size=n, p=(w := np.array([14, 12, 4, 10, 8, 8, 12, 4, 8, 8, 6, 6])) / w.sum())
    stage = [NEEDS[c][0] for c in codes]
    urgency = [NEEDS[c][2] for c in codes]
    region = rng.choice([1, 2, 3], size=n, p=[0.2, 0.4, 0.4])
    language = rng.choice(["en", "ny"], size=n, p=[0.45, 0.55])
    ptype = rng.choice(["hospital", "health_centre", "clinic", "ngo"], size=n, p=[0.35, 0.4, 0.15, 0.1])
    is_free = (rng.random(n) < np.where(np.isin(ptype, ["hospital", "health_centre"]), 0.9, 0.35)).astype(int)
    hour = rng.integers(0, 24, size=n)
    weekday = rng.integers(1, 8, size=n)
    prior = rng.choice([0, 1, 2, 3], size=n, p=[0.7, 0.18, 0.08, 0.04])

    # INVENTED effects (log-odds of the referral NOT being confirmed)
    z = (-0.3
         + 0.6 * (1 - is_free)
         + 0.5 * ((hour >= 17) | (hour < 6))
         + 0.4 * np.isin(weekday, [1, 7])
         + 0.4 * (region == 1)
         + 0.5 * (np.array(stage) == "adolescent")
         + 0.2 * (language == "ny")
         - 0.6 * np.minimum(prior, 2)
         - 0.4 * (np.array(urgency) == "urgent"))
    p = 1 / (1 + np.exp(-z))
    label = (rng.random(n) < p).astype(int)

    return pd.DataFrame({
        "reference": [f"CL-{i:06X}" for i in range(n)],
        "stage": stage, "need_code": codes, "urgency": urgency, "region_id": region,
        "language": language, "is_free": is_free, "provider_type": ptype,
        "hour_created": hour, "weekday_created": weekday, "prior_cases": prior,
        "label_incomplete": label,
    })


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int, default=1500)
    ap.add_argument("--seed", type=int, default=7)
    ap.add_argument("--out", default="data/synthetic_cases.csv")
    a = ap.parse_args()
    df = generate(a.n, a.seed)
    df.to_csv(a.out, index=False)
    print(f"Wrote {len(df)} SYNTHETIC rows to {a.out} (incomplete rate {df.label_incomplete.mean():.0%})")
