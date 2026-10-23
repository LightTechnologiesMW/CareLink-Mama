-- Run once in phpMyAdmin (SQL tab) on the CareLink database.
-- Adds storage for model scores and a coordinator view that works with or without them.

CREATE TABLE IF NOT EXISTS case_risk_scores (
  reference      CHAR(9) NOT NULL PRIMARY KEY,
  risk_score     DECIMAL(4,3) NULL,
  priority_tier  ENUM('normal','high','urgent') NOT NULL,
  reasons        VARCHAR(255) NULL,
  model_source   ENUM('synthetic','real','rules_only') NOT NULL,
  scored_at      DATETIME NOT NULL,
  CONSTRAINT fk_scores_case FOREIGN KEY (reference) REFERENCES cases(reference) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Open cases, most urgent first. Falls back to the rule-based urgency when a case has no score.
-- Excludes phone numbers.
CREATE OR REPLACE VIEW v_cases_prioritised AS
SELECT
  c.reference,
  COALESCE(s.priority_tier,
           CASE c.urgency WHEN 'urgent' THEN 'urgent' WHEN 'priority' THEN 'high' ELSE 'normal' END) AS priority_tier,
  s.risk_score, s.reasons, s.model_source,
  c.status, c.need_code, d.name AS district, c.created_at,
  TIMESTAMPDIFF(HOUR, c.created_at, NOW()) AS hours_open
FROM cases c
JOIN districts d ON d.id = c.district_id
LEFT JOIN case_risk_scores s ON s.reference = c.reference
WHERE c.status IN ('new','matched','notified','acknowledged','incomplete')
ORDER BY FIELD(priority_tier, 'urgent', 'high', 'normal'), c.created_at;
