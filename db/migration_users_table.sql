-- ============================================================
-- Migration: local Users / role-assignment list
-- This app does not own login (see includes/auth.php — ONE Yadin SSO
-- populates the session), so this table is NOT a password store. It's
-- a Name -> Role/Status assignment list: when someone logs in via SSO,
-- current_user() looks their name up here to decide their actual role
-- (and whether they're deactivated), instead of trusting SSO's role.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

CREATE TABLE IF NOT EXISTS m_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
