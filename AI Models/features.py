"""Turn an exported cases table into model features."""
import pandas as pd

from config import REQUIRED_COLUMNS


def load_cases(path: str) -> pd.DataFrame:
    df = pd.read_csv(path, na_values=["NULL", "null", ""])
    missing = [c for c in REQUIRED_COLUMNS if c not in df.columns]
    if missing:
        raise SystemExit(f"Input CSV is missing columns: {missing}. Use export_features.sql.")
    return df


def add_features(df: pd.DataFrame) -> pd.DataFrame:
    df = df.copy()
    df["region"] = df["region_id"].astype(str)
    df["language"] = df["language"].fillna("en")
    df["provider_type"] = df["provider_type"].fillna("none")
    df["not_free"] = 1 - pd.to_numeric(df["is_free"], errors="coerce").fillna(0).astype(int)
    hour = pd.to_numeric(df["hour_created"], errors="coerce").fillna(12)
    df["is_evening"] = ((hour >= 17) | (hour < 6)).astype(int)
    # MySQL DAYOFWEEK: 1 = Sunday ... 7 = Saturday
    df["is_weekend"] = pd.to_numeric(df["weekday_created"], errors="coerce").isin([1, 7]).astype(int)
    df["prior_cases"] = pd.to_numeric(df["prior_cases"], errors="coerce").fillna(0).clip(upper=5)
    return df
