"""
Safety layer: combines the clinical RULES with the model.

The model only ever ESCALATES follow-up priority. It can never lower a tier
that the rules assigned, and it never makes clinical decisions. Urgent danger
signs always stay urgent, whatever the model says.

Tiers: urgent = contact now | high = contact within 24h | normal = routine follow-up
"""
from config import HIGH_RISK_THRESHOLD

TIERS = ["normal", "high", "urgent"]


def rule_tier(rule_urgency: str) -> str:
    """Map the app's rule-based urgency flag to a tier."""
    return {"urgent": "urgent", "priority": "high"}.get(rule_urgency, "normal")


def priority_tier(rule_urgency: str, risk_score, high_threshold: float = HIGH_RISK_THRESHOLD) -> str:
    base = rule_tier(rule_urgency)
    if base != "normal":
        return base                           # rules are a floor the model cannot lower
    if risk_score is not None and risk_score >= high_threshold:
        return "high"                         # model may escalate routine cases
    return "normal"
