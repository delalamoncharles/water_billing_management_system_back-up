-- ============================================================
--  AquaBill Water Billing System — MySQL Database Schema
--  Compatible with MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS billing_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE billing_system;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE users (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  full_name   VARCHAR(100)    NOT NULL,
  username    VARCHAR(50)     NOT NULL UNIQUE,
  email       VARCHAR(150)    NOT NULL UNIQUE,
  address     VARCHAR(255)    NOT NULL,
  barangay    VARCHAR(100)    NOT NULL DEFAULT '',
  password    VARCHAR(255)    NOT NULL COMMENT 'bcrypt hashed',
  role        ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_email      (email),
  INDEX idx_role       (role),
  INDEX idx_is_active  (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: bills
-- ============================================================
CREATE TABLE bills (
  id             INT UNSIGNED         NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED         NOT NULL,
  barangay       VARCHAR(100)         NOT NULL DEFAULT '',
  billing_month  TINYINT UNSIGNED     NOT NULL COMMENT '1=January … 12=December',
  billing_year   SMALLINT UNSIGNED    NOT NULL,
  usage_m3       DECIMAL(8,2)         NOT NULL DEFAULT 0.00 COMMENT 'Cubic metres consumed',
  amount         DECIMAL(10,2)        NOT NULL DEFAULT 0.00 COMMENT 'Auto-computed: max(usage*rate, min_charge)',
  status         ENUM('paid','unpaid') NOT NULL DEFAULT 'unpaid',
  paid_at        TIMESTAMP            NULL     DEFAULT NULL,
  created_at     TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE  KEY uq_user_period (user_id, billing_month, billing_year),
  INDEX   idx_status         (status),
  INDEX   idx_period         (billing_year, billing_month),
  CONSTRAINT fk_bills_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: billing_rates  (single-row config table)
-- ============================================================
CREATE TABLE billing_rates (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  rate_per_m3  DECIMAL(8,2)  NOT NULL DEFAULT 20.00 COMMENT 'Price per cubic metre',
  min_charge   DECIMAL(8,2)  NOT NULL DEFAULT 100.00 COMMENT 'Minimum bill amount',
  effective_from DATE        NOT NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ============================================================
-- SEED: Default Admin Account
-- Password: admin123  (bcrypt hash — regenerate in production!)
-- ============================================================
INSERT INTO users (full_name, username, email, address, password, role, is_active)
VALUES (
  'System Administrator',
  'admin',
  'admin@gmail.com',
  'AquaBill Office, Main St.',
  '$2y$12$7Kp9x3mQwLvZoNeX2Gy9i.NbzKsJR1Vd5HBqA8dTRYfPuLcMwEXCa',
  'admin',
  1
);

-- ============================================================
-- SEED: Sample Users
-- Password for all: user123
-- ============================================================
INSERT INTO users (full_name, username, email, address, password, role, is_active) VALUES
('Juan',  'jdelacruz', 'juan@email.com',     '123 Mabuhay St., Cebu City',    '$2y$12$SampleHashForUser1xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'user', 1),
('Maria',     'mreyes',    'maria@email.com',    '45 Sampaguita Ave., Mandaue',   '$2y$12$SampleHashForUser2xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'user', 1),
('Antonio',  'asantos',   'antonio@email.com',  '789 Rizal Blvd., Lapu-Lapu',   '$2y$12$SampleHashForUser3xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'user', 0),
('Beatriz',  'bmanalo',   'bea@email.com',      '12 Kalikasan St., Talisay',     '$2y$12$SampleHashForUser4xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'user', 1),
('Carlos',    'clopez',    'carlos@email.com',   '67 Freedom Ave., Consolacion',  '$2y$12$SampleHashForUser5xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'user', 1);

-- ============================================================
-- SEED: Billing Rate
-- ============================================================
INSERT INTO billing_rates (rate_per_m3, min_charge, effective_from)
VALUES (20.00, 100.00, '2024-01-01');

-- ============================================================
-- SEED: Sample Bills  (user_id 2 = jdelacruz)
-- ============================================================
INSERT INTO bills (user_id, billing_month, billing_year, usage_m3, amount, status, paid_at) VALUES
(2, 4, 2025, 18.00, 360.00, 'paid',   '2025-04-10 09:15:00'),
(2, 3, 2025, 22.00, 440.00, 'paid',   '2025-03-08 10:30:00'),
(2, 2, 2025, 18.00, 360.00, 'unpaid', NULL),
(2, 1, 2025, 22.00, 440.00, 'paid',   '2025-01-12 14:22:00'),
(3, 4, 2025, 24.00, 480.00, 'unpaid', NULL),
(3, 3, 2025,  5.00, 100.00, 'paid',   '2025-03-05 08:00:00'),
(4, 4, 2025, 12.00, 240.00, 'paid',   '2025-04-09 11:45:00'),
(5, 3, 2025, 31.00, 620.00, 'unpaid', NULL),
(6, 3, 2025,  9.00, 180.00, 'paid',   '2025-03-07 16:10:00');

-- ============================================================
-- USEFUL VIEWS
-- ============================================================

-- Admin billing report view
CREATE OR REPLACE VIEW vw_billing_report AS
SELECT
  u.id          AS user_id,
  u.username,
  u.full_name,
  b.id          AS bill_id,
  CONCAT(
    CASE b.billing_month
      WHEN  1 THEN 'January'  WHEN  2 THEN 'February' WHEN  3 THEN 'March'
      WHEN  4 THEN 'April'    WHEN  5 THEN 'May'       WHEN  6 THEN 'June'
      WHEN  7 THEN 'July'     WHEN  8 THEN 'August'    WHEN  9 THEN 'September'
      WHEN 10 THEN 'October'  WHEN 11 THEN 'November'  WHEN 12 THEN 'December'
    END, ' ', b.billing_year
  )             AS billing_month,
  b.usage_m3    AS `usage`,
  b.amount,
  b.status,
  b.paid_at,
  b.created_at
FROM bills b
JOIN users u ON u.id = b.user_id
ORDER BY b.billing_year DESC, b.billing_month DESC;

-- Monthly revenue summary
CREATE OR REPLACE VIEW vw_monthly_revenue AS
SELECT
  billing_year,
  billing_month,
  COUNT(*)                          AS total_bills,
  SUM(usage_m3)                     AS total_usage,
  SUM(amount)                       AS total_billed,
  SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS collected,
  SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END) AS outstanding
FROM bills
GROUP BY billing_year, billing_month
ORDER BY billing_year DESC, billing_month DESC;

-- ============================================================
-- STORED PROCEDURE: Compute and insert a new bill
-- ============================================================
DELIMITER $$

CREATE PROCEDURE sp_create_bill(
  IN  p_user_id       INT UNSIGNED,
  IN  p_month         TINYINT UNSIGNED,
  IN  p_year          SMALLINT UNSIGNED,
  IN  p_usage         DECIMAL(8,2),
  OUT p_bill_id       INT UNSIGNED,
  OUT p_amount        DECIMAL(10,2)
)
BEGIN
  DECLARE v_rate      DECIMAL(8,2);
  DECLARE v_min       DECIMAL(8,2);

  -- Fetch the latest billing rate
  SELECT rate_per_m3, min_charge
    INTO v_rate, v_min
    FROM billing_rates
   ORDER BY effective_from DESC
   LIMIT 1;

  -- Compute amount
  SET p_amount = GREATEST(p_usage * v_rate, v_min);

  -- Insert bill
  INSERT INTO bills (user_id, billing_month, billing_year, usage_m3, amount, status)
  VALUES (p_user_id, p_month, p_year, p_usage, p_amount, 'unpaid');

  SET p_bill_id = LAST_INSERT_ID();
END $$

DELIMITER ;

-- ============================================================
-- EXAMPLE QUERIES (for backend reference)
-- ============================================================

-- 1. Get all bills for a user
-- SELECT * FROM vw_billing_report WHERE user_id = ?;

-- 2. Mark bill as paid
-- UPDATE bills SET status='paid', paid_at=NOW() WHERE id = ?;

-- 3. Get dashboard stats
-- SELECT
--   (SELECT COUNT(*) FROM users WHERE role='user') AS total_users,
--   (SELECT COUNT(*) FROM bills)                  AS total_bills,
--   (SELECT COUNT(*) FROM bills WHERE status='paid')   AS paid_bills,
--   (SELECT COUNT(*) FROM bills WHERE status='unpaid') AS unpaid_bills,
--   (SELECT SUM(amount) FROM bills WHERE status='paid') AS total_revenue;

-- 4. Call stored procedure to create a new bill
-- CALL sp_create_bill(2, 5, 2025, 18.5, @bid, @amt);
-- SELECT @bid AS bill_id, @amt AS computed_amount;
