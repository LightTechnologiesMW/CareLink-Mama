-- Run in phpMyAdmin (SQL tab), then click Export -> CSV and save as data/real_cases.csv
-- Contains NO phone numbers. 'reference' is the only identifier. Treat the file as sensitive.
SELECT
  c.reference, c.stage, c.need_code, c.urgency,
  d.region_id,
  u.language,
  COALESCE(p.is_free, 0)              AS is_free,
  COALESCE(p.provider_type, 'none')   AS provider_type,
  HOUR(c.created_at)                  AS hour_created,
  DAYOFWEEK(c.created_at)             AS weekday_created,
  (SELECT COUNT(*) FROM cases c2
     WHERE c2.user_id = c.user_id AND c2.created_at < c.created_at) AS prior_cases,
  CASE c.status WHEN 'attended' THEN 0 WHEN 'incomplete' THEN 1 ELSE NULL END AS label_incomplete
FROM cases c
JOIN users u     ON u.id = c.user_id
JOIN districts d ON d.id = c.district_id
LEFT JOIN providers p ON p.id = c.matched_provider_id
ORDER BY c.id;
