import os, sys, unittest
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from safety import TIERS, priority_tier


class SafetyTests(unittest.TestCase):
    def test_urgent_never_downgraded(self):
        for r in [None, 0.0, 0.01, 0.5, 0.99]:
            self.assertEqual(priority_tier("urgent", r), "urgent")

    def test_priority_never_below_high(self):
        for r in [None, 0.0, 0.2, 0.9]:
            self.assertEqual(priority_tier("priority", r), "high")

    def test_model_can_escalate_routine(self):
        self.assertEqual(priority_tier("routine", 0.85), "high")
        self.assertEqual(priority_tier("routine", 0.60), "high")

    def test_low_risk_routine_stays_normal(self):
        self.assertEqual(priority_tier("routine", 0.10), "normal")

    def test_no_score_falls_back_to_rules(self):
        self.assertEqual(priority_tier("routine", None), "normal")

    def test_model_never_lowers_any_tier(self):
        rank = {t: i for i, t in enumerate(TIERS)}
        floor = {"urgent": "urgent", "priority": "high", "routine": "normal"}
        for u in floor:
            for r in [None] + [i / 20 for i in range(21)]:
                self.assertGreaterEqual(rank[priority_tier(u, r)], rank[floor[u]])

    def test_model_never_creates_urgent(self):
        for r in [0.0, 0.5, 1.0]:
            self.assertNotEqual(priority_tier("routine", r), "urgent")


if __name__ == "__main__":
    unittest.main()
