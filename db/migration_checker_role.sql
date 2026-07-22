-- ============================================================
-- Migration: add optional role (Foreman / Supervisor) to Checked By
-- Used by Washing Machine Liquid Monitoring to split the Checker
-- and Control dropdowns. Leave NULL for general-purpose checkers
-- (Painting / Torque keep showing everyone regardless of role).
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

ALTER TABLE m_checker
    ADD COLUMN role ENUM('foreman', 'supervisor') NULL AFTER name;
