-- ============================================================
-- Migration: make Checked By roles admin-manageable
-- Replaces the fixed ENUM('foreman','supervisor','operator') on
-- m_checker.role with a free VARCHAR backed by a new m_checker_role
-- master table, so new roles can be added from the admin UI without
-- a schema change each time.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

CREATE TABLE IF NOT EXISTS m_checker_role (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO m_checker_role (name, label, sort_order) VALUES
    ('foreman', 'Foreman', 1),
    ('supervisor', 'Supervisor', 2),
    ('operator', 'Operator', 3);

ALTER TABLE m_checker
    MODIFY COLUMN role VARCHAR(50) NULL;
