-- ============================================================
-- Migration: make application roles admin-manageable
-- Replaces the fixed superadmin/admin/user set with a m_role master
-- table so new roles can be added freely from the admin UI. Any role
-- flagged is_full_access behaves like the old hardcoded "superadmin"
-- (always allowed, matrix checkbox disabled).
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

CREATE TABLE IF NOT EXISTS m_role (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    is_full_access TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO m_role (name, label, is_full_access, sort_order) VALUES
    ('superadmin', 'Super Admin', 1, 1),
    ('admin', 'Admin', 0, 2),
    ('user', 'User', 0, 3);

-- role used to be ENUM('admin','user') — widen to VARCHAR so custom
-- role names (and 'superadmin', previously never stored here) fit too.
ALTER TABLE m_role_permission
    MODIFY COLUMN role VARCHAR(50) NOT NULL;

ALTER TABLE m_user
    MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user';
