-- =============================================================================
-- CareLink Mama & Baby - database schema and reference data
-- Target: MySQL 8 / MariaDB 10.5+   |   Import: mysql -u root -p < database/carelink.sql
--
-- PRIVACY: this file contains NO real patient data. The sample providers at the
-- bottom are fictional (is_sample = 1) and must be removed before any pilot.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS carelink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE carelink;

-- ----------------------------------------------------------------------------
-- Geography
-- ----------------------------------------------------------------------------
CREATE TABLE regions (
  id    TINYINT UNSIGNED PRIMARY KEY,
  name  VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE districts (
  id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  region_id  TINYINT UNSIGNED NOT NULL,
  name       VARCHAR(40) NOT NULL UNIQUE,
  CONSTRAINT fk_districts_region FOREIGN KEY (region_id) REFERENCES regions(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Verified service directory
-- ----------------------------------------------------------------------------
CREATE TABLE service_categories (
  code     VARCHAR(30) PRIMARY KEY,
  name_en  VARCHAR(80) NOT NULL,
  name_ny  VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE providers (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(120) NOT NULL,
  provider_type      ENUM('hospital','health_centre','clinic','ngo','chw_programme','other') NOT NULL,
  district_id        SMALLINT UNSIGNED NOT NULL,
  address            VARCHAR(200) NULL,
  phone              VARCHAR(20)  NULL,
  notify_phone       VARCHAR(20)  NULL COMMENT 'Receives consent-based referral alerts',
  opening_hours      VARCHAR(120) NULL,
  is_free            TINYINT(1) NOT NULL DEFAULT 0,
  is_subsidised      TINYINT(1) NOT NULL DEFAULT 0,
  eligibility_notes  VARCHAR(255) NULL,
  latitude           DECIMAL(9,6) NULL,   -- reserved for planned GIS matching
  longitude          DECIMAL(9,6) NULL,
  verified           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Only verified rows are recommended',
  verified_by        VARCHAR(80) NULL,
  verified_at        DATETIME NULL,
  is_sample          TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Fictional dev data; remove before pilot',
  active             TINYINT(1) NOT NULL DEFAULT 1,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_match (district_id, verified, active),
  CONSTRAINT fk_providers_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB;

CREATE TABLE provider_services (
  provider_id    INT UNSIGNED NOT NULL,
  category_code  VARCHAR(30) NOT NULL,
  PRIMARY KEY (provider_id, category_code),
  CONSTRAINT fk_ps_provider FOREIGN KEY (provider_id)   REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_category FOREIGN KEY (category_code) REFERENCES service_categories(code)
) ENGINE=InnoDB;

CREATE TABLE community_health_workers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  phone        VARCHAR(20)  NOT NULL,
  district_id  SMALLINT UNSIGNED NOT NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_chw_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Users, cases, referrals, follow-up
-- Only created AFTER the user explicitly consents on the USSD consent screen.
-- ----------------------------------------------------------------------------
CREATE TABLE users (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone       VARCHAR(20) NOT NULL UNIQUE,
  language    ENUM('en','ny') NOT NULL DEFAULT 'en',
  consent_at  DATETIME NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE cases (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference             CHAR(9) NOT NULL UNIQUE,            -- e.g. CL-A1B2C3
  user_id               BIGINT UNSIGNED NOT NULL,
  session_id            VARCHAR(80) NULL,
  stage                 ENUM('pregnant','postnatal_0_90d','adolescent') NOT NULL,
  need_code             VARCHAR(40) NOT NULL,               -- what the user reported (not a diagnosis)
  category_code         VARCHAR(30) NOT NULL,
  urgency               ENUM('routine','priority','urgent') NOT NULL DEFAULT 'routine',
  district_id           SMALLINT UNSIGNED NOT NULL,
  matched_provider_id   INT UNSIGNED NULL,
  status                ENUM('new','matched','notified','acknowledged','attended','incomplete','closed') NOT NULL DEFAULT 'new',
  consent_given         TINYINT(1) NOT NULL DEFAULT 1,
  coordinator_notes     TEXT NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_user_created (user_id, created_at),
  KEY idx_status_urgency (status, urgency),
  CONSTRAINT fk_cases_user     FOREIGN KEY (user_id)             REFERENCES users(id),
  CONSTRAINT fk_cases_category FOREIGN KEY (category_code)       REFERENCES service_categories(code),
  CONSTRAINT fk_cases_district FOREIGN KEY (district_id)         REFERENCES districts(id),
  CONSTRAINT fk_cases_provider FOREIGN KEY (matched_provider_id) REFERENCES providers(id)
) ENGINE=InnoDB;

CREATE TABLE referrals (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id          BIGINT UNSIGNED NOT NULL,
  provider_id      INT UNSIGNED NOT NULL,
  status           ENUM('pending','notified','acknowledged','attended','not_attended') NOT NULL DEFAULT 'pending',
  notified_at      DATETIME NULL,
  acknowledged_at  DATETIME NULL,
  attended_at      DATETIME NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_case (case_id),
  CONSTRAINT fk_ref_case     FOREIGN KEY (case_id)     REFERENCES cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_ref_provider FOREIGN KEY (provider_id) REFERENCES providers(id)
) ENGINE=InnoDB;

CREATE TABLE followups (
  id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id   BIGINT UNSIGNED NOT NULL,
  kind      ENUM('ATTENDANCE_CHECK','ANC_REMINDER','NEWBORN_CHECK') NOT NULL DEFAULT 'ATTENDANCE_CHECK',
  due_at    DATETIME NOT NULL,
  status    ENUM('pending','sent','completed','missed') NOT NULL DEFAULT 'pending',
  sent_at   DATETIME NULL,
  response  VARCHAR(20) NULL,
  KEY idx_due (status, due_at),
  CONSTRAINT fk_fu_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor       VARCHAR(60) NOT NULL,
  action      VARCHAR(60) NOT NULL,
  entity      VARCHAR(40) NOT NULL,
  entity_id   BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Coordinator view: flags unresolved referrals. Deliberately excludes phone numbers.
CREATE VIEW v_cases_needing_attention AS
SELECT c.reference, c.urgency, c.status, c.need_code, d.name AS district,
       c.created_at, TIMESTAMPDIFF(HOUR, c.created_at, NOW()) AS hours_open
FROM cases c
JOIN districts d ON d.id = c.district_id
WHERE c.status IN ('new','incomplete')
   OR (c.status IN ('matched','notified') AND c.created_at < (NOW() - INTERVAL 48 HOUR));

-- ----------------------------------------------------------------------------
-- Reference data
-- ----------------------------------------------------------------------------
INSERT INTO regions (id, name) VALUES (1,'Northern'), (2,'Central'), (3,'Southern');

INSERT INTO districts (region_id, name) VALUES
 (1,'Chitipa'),(1,'Karonga'),(1,'Likoma'),(1,'Mzimba'),(1,'Nkhata Bay'),(1,'Rumphi'),
 (2,'Dedza'),(2,'Dowa'),(2,'Kasungu'),(2,'Lilongwe'),(2,'Mchinji'),(2,'Nkhotakota'),(2,'Ntcheu'),(2,'Ntchisi'),(2,'Salima'),
 (3,'Balaka'),(3,'Blantyre'),(3,'Chikwawa'),(3,'Chiradzulu'),(3,'Machinga'),(3,'Mangochi'),(3,'Mulanje'),
 (3,'Mwanza'),(3,'Neno'),(3,'Nsanje'),(3,'Phalombe'),(3,'Thyolo'),(3,'Zomba');

INSERT INTO service_categories (code, name_en, name_ny) VALUES
 ('ANC',             'Antenatal care',                   'Kukayezetsa pakati'),
 ('ANAEMIA',         'Anaemia testing and care',         'Za kuchepa magazi'),
 ('BIRTH',           'Birth and delivery',               'Kubereka'),
 ('PNC_NEWBORN',     'Postnatal and newborn care',       'Mwana ndi pambuyo pobereka'),
 ('ADOLESCENT_SRH',  'Adolescent-friendly reproductive health', 'Chithandizo cha achinyamata'),
 ('FAMILY_PLANNING', 'Family planning',                  'Kulera'),
 ('SUPPORT',         'Counselling and support',          'Uphungu ndi chithandizo'),
 ('URGENT_CARE',     'Emergency obstetric / newborn care', 'Chithandizo chadzidzidzi');

-- ----------------------------------------------------------------------------
-- SAMPLE providers (FICTIONAL - development only). Delete before any pilot:
--   DELETE FROM providers WHERE is_sample = 1;
-- ----------------------------------------------------------------------------
INSERT INTO providers
 (name, provider_type, district_id, address, phone, opening_hours, is_free, eligibility_notes, verified, verified_by, is_sample)
VALUES
 ('SAMPLE District Hospital',   'hospital',      (SELECT id FROM districts WHERE name='Lilongwe'), 'Sample address, Lilongwe', '+265000000001', '24 hours',        1, 'Sample record', 1, 'dev', 1),
 ('SAMPLE Health Centre A',     'health_centre', (SELECT id FROM districts WHERE name='Lilongwe'), 'Sample address, Lilongwe', '+265000000002', 'Mon-Fri 7:30-16:00', 1, 'Sample record', 1, 'dev', 1),
 ('SAMPLE Central Hospital',    'hospital',      (SELECT id FROM districts WHERE name='Blantyre'), 'Sample address, Blantyre', '+265000000003', '24 hours',        1, 'Sample record', 1, 'dev', 1),
 ('SAMPLE Youth Clinic',        'clinic',        (SELECT id FROM districts WHERE name='Blantyre'), 'Sample address, Blantyre', '+265000000004', 'Mon-Sat 8:00-17:00', 0, 'Sample record', 1, 'dev', 1),
 ('SAMPLE District Hospital N', 'hospital',      (SELECT id FROM districts WHERE name='Mzimba'),   'Sample address, Mzimba',   '+265000000005', '24 hours',        1, 'Sample record', 1, 'dev', 1),
 ('SAMPLE District Hospital S', 'hospital',      (SELECT id FROM districts WHERE name='Zomba'),    'Sample address, Zomba',    '+265000000006', '24 hours',        1, 'Sample record', 1, 'dev', 1);

INSERT INTO provider_services (provider_id, category_code)
SELECT p.id, c.code FROM providers p CROSS JOIN service_categories c WHERE p.is_sample = 1;
