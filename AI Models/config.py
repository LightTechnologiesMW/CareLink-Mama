"""Shared settings for the CareLink referral-risk model."""

# Features (all derived from what the USSD app already stores)
CAT_FEATURES = ["stage", "need_code", "urgency", "region", "language", "provider_type"]
BIN_FEATURES = ["not_free", "is_evening", "is_weekend"]
NUM_FEATURES = ["prior_cases"]
LABEL = "label_incomplete"          # 1 = referral NOT confirmed as attended, 0 = attended

# Columns the exported CSV must contain (see export_features.sql)
REQUIRED_COLUMNS = [
    "reference", "stage", "need_code", "urgency", "region_id", "language",
    "is_free", "provider_type", "hour_created", "weekday_created", "prior_cases",
]

# Priority tiers
HIGH_RISK_THRESHOLD = 0.60          # risk_score at/above this => "high" priority for follow-up.
                                    # Tune to coordinator capacity once real data exists.

# Guards: refuse to train on too little REAL data
MIN_LABELED_REAL = 150
MIN_PER_CLASS_REAL = 30

# Evaluation
TOP_FRACTION = 0.30                 # "if coordinators can chase the riskiest 30%..."
MIN_GROUP_SIZE = 30                 # smallest group audited for fairness
