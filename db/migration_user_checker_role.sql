-- ============================================================
-- Migration: show each user's Checked By role (Foreman/Supervisor/
-- Operator/...) alongside their app access role on admin/users.php,
-- e.g. "Rinaldi - Supervisor - Admin". Purely informational — does not
-- link to a specific department-scoped m_checker row.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

ALTER TABLE m_user
    ADD COLUMN checker_role VARCHAR(50) NULL AFTER role;
