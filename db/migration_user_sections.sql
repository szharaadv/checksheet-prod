-- ============================================================
-- Migration: per-section access control for Users
-- Lets each user be assigned to specific check sheet sections
-- (Painting, FO Pump Assy, Torque, Washing, Sub Assembly, ...).
-- Users with a "full access" app role (e.g. Super Admin) bypass this
-- and can open any section regardless of assignment.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

CREATE TABLE IF NOT EXISTS m_user_section (
    user_id INT NOT NULL,
    section_id INT NOT NULL,
    PRIMARY KEY (user_id, section_id),
    CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES m_user(id) ON DELETE CASCADE,
    CONSTRAINT fk_us_section FOREIGN KEY (section_id) REFERENCES m_checksheet_section(id) ON DELETE CASCADE
) ENGINE=InnoDB;
