-- ============================================================
-- Migration: add 'operator' to the Checked By role enum
-- Used to pre-fill/select an Operator name on FO Pump Assy check
-- sheet templates, alongside the existing Foreman/Supervisor roles.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

ALTER TABLE m_checker
    MODIFY COLUMN role ENUM('foreman', 'supervisor', 'operator') NULL;
