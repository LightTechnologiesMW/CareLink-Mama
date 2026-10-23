"""
Train the referral-risk model: predicts which referrals are likely to go
unconfirmed, so coordinators can follow those up first.

Usage:
  python train.py --data data/synthetic_cases.csv --source synthetic
  python train.py --data data/real_cases.csv      --source real
"""
import argparse
import datetime as dt
import json
import os
import sys

import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import average_precision_score, brier_score_loss, roc_auc_score
from sklearn.model_selection import StratifiedKFold, cross_val_predict
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler

from config import (BIN_FEATURES, CAT_FEATURES, LABEL, MIN_GROUP_SIZE, MIN_LABELED_REAL,
                    MIN_PER_CLASS_REAL, NUM_FEATURES, TOP_FRACTION)
from features import add_features, load_cases


def recall_at_top(y, s, frac=TOP_FRACTION):
    k = max(1, int(round(len(s) * frac)))
    top = np.argsort(-s)[:k]
    return float(y[top].sum() / max(1, y.sum()))


def build_pipeline(cats, bins, nums, model):
    pre = ColumnTransformer([
        ("cat", OneHotEncoder(handle_unknown="ignore", sparse_output=False), cats),
        ("bin", "passthrough", bins),
        ("num", StandardScaler(), nums),
    ])
    return Pipeline([("pre", pre), ("clf", model)])


def candidates():
    return {
        "logistic_regression": LogisticRegression(C=0.5, max_iter=1000),
        "gradient_boosting": GradientBoostingClassifier(
            n_estimators=150, max_depth=2, learning_rate=0.05, subsample=0.8, random_state=0),
    }


def evaluate(y, s):
    return {
        "roc_auc": round(float(roc_auc_score(y, s)), 3),
        "pr_auc": round(float(average_precision_score(y, s)), 3),
        "brier": round(float(brier_score_loss(y, s)), 3),
        f"recall_in_top_{int(TOP_FRACTION*100)}pct": round(recall_at_top(y, s), 3),
    }


def audit_groups(df, y, s):
    """Performance per group, to catch a model that works worse for some women."""
    rows = []
    for col in ["stage", "region", "language"]:
        for val, g in df.groupby(col):
            idx = g.index.to_numpy()
            if len(g) < MIN_GROUP_SIZE or len(np.unique(y[idx])) < 2:
                continue
            rows.append({"group": f"{col}={val}", "n": int(len(g)),
                         "incomplete_rate": round(float(y[idx].mean()), 3),
                         "roc_auc": round(float(roc_auc_score(y[idx], s[idx])), 3),
                         "recall_top": round(recall_at_top(y[idx], s[idx]), 3)})
    return rows


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--data", required=True)
    ap.add_argument("--source", required=True, choices=["synthetic", "real"])
    ap.add_argument("--out-dir", default="models")
    a = ap.parse_args()

    df = add_features(load_cases(a.data))
    if LABEL not in df.columns:
        sys.exit(f"Column {LABEL} not found.")
    df = df[df[LABEL].isin([0, 1])].reset_index(drop=True)      # keep only cases with a known outcome
    y = df[LABEL].astype(int).to_numpy()
    n, pos = len(df), int(y.sum())
    print(f"Labelled cases: {n} | incomplete: {pos} ({pos/max(n,1):.0%}) | source: {a.source}")

    if a.source == "real" and (n < MIN_LABELED_REAL or min(pos, n - pos) < MIN_PER_CLASS_REAL):
        sys.exit(f"\nNot enough real data to train safely (need >= {MIN_LABELED_REAL} labelled cases and "
                 f">= {MIN_PER_CLASS_REAL} in each outcome). Keep using the rules and collect more pilot data.")

    # Drop features with no variation in this dataset (cannot be learned from)
    def keep(cols):
        return [c for c in cols if df[c].nunique() > 1]
    cats, bins, nums = keep(CAT_FEATURES), keep(BIN_FEATURES), keep(NUM_FEATURES)
    dropped = sorted(set(CAT_FEATURES + BIN_FEATURES + NUM_FEATURES) - set(cats + bins + nums))
    if dropped:
        print("Dropped constant features:", dropped)
    cols = cats + bins + nums

    cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=0)
    results, preds = {}, {}
    for name, model in candidates().items():
        pipe = build_pipeline(cats, bins, nums, model)
        s = cross_val_predict(pipe, df[cols], y, cv=cv, method="predict_proba")[:, 1]
        preds[name], results[name] = s, evaluate(y, s)
        print(f"\n{name}\n  {results[name]}")

    # Prefer the simpler, explainable model unless boosting is clearly better
    chosen = "logistic_regression"
    if results["gradient_boosting"]["roc_auc"] > results["logistic_regression"]["roc_auc"] + 0.02:
        chosen = "gradient_boosting"
    print(f"\nChosen model: {chosen}")

    audit = audit_groups(df, y, preds[chosen])
    print("\nGroup audit (cross-validated):")
    for r in audit:
        print("  ", r)

    pipe = build_pipeline(cats, bins, nums, candidates()[chosen]).fit(df[cols], y)
    pre = pipe.named_steps["pre"]
    names = list(pre.get_feature_names_out())
    means = pre.transform(df[cols]).mean(axis=0)
    bundle = {"pipeline": pipe, "cats": cats, "bins": bins, "nums": nums, "cols": cols,
              "feature_names": names, "means": means, "is_linear": chosen == "logistic_regression"}

    if bundle["is_linear"]:
        coefs = pipe.named_steps["clf"].coef_[0]
        order = np.argsort(-np.abs(coefs))[:8]
        print("\nStrongest factors (+ raises risk of an unconfirmed referral):")
        for i in order:
            print(f"   {coefs[i]:+.2f}  {names[i]}")

    os.makedirs(a.out_dir, exist_ok=True)
    joblib.dump(bundle, os.path.join(a.out_dir, "risk_model.joblib"))
    meta = {"data_source": a.source, "trained_at": dt.datetime.now().isoformat(timespec="seconds"),
            "n_labelled": n, "incomplete_rate": round(pos / n, 3), "model": chosen,
            "features": cols, "dropped_constant_features": dropped,
            "cv_metrics": results[chosen], "group_audit": audit}
    with open(os.path.join(a.out_dir, "metadata.json"), "w") as f:
        json.dump(meta, f, indent=2)
    print(f"\nSaved model to {a.out_dir}/")
    if a.source == "synthetic":
        print("\n*** SYNTHETIC DATA: these metrics are NOT evidence of real-world performance. ***")


if __name__ == "__main__":
    main()
