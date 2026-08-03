-- ============================================================
-- Migration: Roles & Role Permissions
-- Adds the three application roles (superadmin / admin / user) and
-- a per-role access matrix that powers admin/role_permissions.php.
--
-- superadmin ALWAYS has full access and is NOT stored here (it is
-- forced to "allowed" in code). Only admin and user rows are stored
-- so they can be toggled from the Role Permissions screen.
--
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

-- ------------------------------------------------------------
-- Per-role permission flags. One row per (role, permission).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS m_role_permission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'user') NOT NULL,
    perm_key VARCHAR(50) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_role_perm (role, perm_key)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed default access matrix.
-- These are only inserted if the row does not exist yet, so
-- re-running the migration will not overwrite manual changes.
-- ------------------------------------------------------------
INSERT INTO m_role_permission (role, perm_key, allowed) VALUES
    -- Admin: full workspace + master data + audit, but NOT user management
    ('admin', 'workspace.checksheet', 1),
    ('admin', 'workspace.view',       1),
    ('admin', 'workspace.drafts',     1),
    ('admin', 'masterdata.manage',    1),
    ('admin', 'users.manage',         0),
    ('admin', 'audit.view',           1),
    -- User: fill / view / draft check sheets only
    ('user',  'workspace.checksheet', 1),
    ('user',  'workspace.view',       1),
    ('user',  'workspace.drafts',     1),
    ('user',  'masterdata.manage',    0),
    ('user',  'users.manage',         0),
    ('user',  'audit.view',           0)
ON DUPLICATE KEY UPDATE perm_key = VALUES(perm_key);
