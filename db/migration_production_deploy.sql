-- ============================================================
-- COMBINED PRODUCTION DEPLOY SCRIPT
-- Bundles every migration behind this session's Users/Roles/Checked By
-- routing feature, in dependency order:
--   1. migration_checker_roles_table.sql
--   2. migration_users_table.sql
--   3. migration_dynamic_roles.sql
--   4. migration_user_checker_role.sql
--   5. migration_user_sections.sql
--   6. migration_checker_section.sql
--
-- SAFE TO RUN: every step only CREATEs new tables or ADDs new nullable
-- columns/rows. Nothing here deletes or overwrites existing rows in
-- t_checksheet_header, t_fopump_header, m_checker, m_department, or any
-- other table that already holds your production data. Every step is
-- also idempotent — re-running this whole script a second time (e.g. if
-- it's interrupted partway) is safe and will not error or duplicate
-- anything, because each step checks whether it already applied first.
--
-- Adjust the database name below if your production DB isn't cs_painting.
-- ============================================================

USE cs_painting;

-- ------------------------------------------------------------
-- Helper procedures: add a column / foreign key only if missing.
-- Dropped again at the end of this script, so they don't linger.
-- ------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS _deploy_add_column_if_missing $$
CREATE PROCEDURE _deploy_add_column_if_missing(
    IN tbl VARCHAR(64), IN col VARCHAR(64), IN coldef VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', coldef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS _deploy_add_fk_if_missing $$
CREATE PROCEDURE _deploy_add_fk_if_missing(
    IN tbl VARCHAR(64), IN fkname VARCHAR(64), IN fkdef VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND CONSTRAINT_NAME = fkname
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD CONSTRAINT `', fkname, '` ', fkdef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- ============================================================
-- 1. Checked By roles master table (Foreman/Supervisor/Operator/...)
-- ============================================================
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

-- ============================================================
-- 2. Users (role-assignment list, not a login/password table)
-- ============================================================
CREATE TABLE IF NOT EXISTS m_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. Dynamic app roles (m_role) — replaces the fixed superadmin/admin/user set
-- ============================================================
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

-- m_role_permission must already exist from the earlier migration_roles.sql.
ALTER TABLE m_role_permission
    MODIFY COLUMN role VARCHAR(50) NOT NULL;

ALTER TABLE m_user
    MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user';

-- ============================================================
-- 4. Checked By role shown alongside a user's app role
-- ============================================================
CALL _deploy_add_column_if_missing('m_user', 'checker_role', 'checker_role VARCHAR(50) NULL AFTER role');

-- ============================================================
-- 5. Per-section access routing for Users
-- ============================================================
CREATE TABLE IF NOT EXISTS m_user_section (
    user_id INT NOT NULL,
    section_id INT NOT NULL,
    PRIMARY KEY (user_id, section_id),
    CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES m_user(id) ON DELETE CASCADE,
    CONSTRAINT fk_us_section FOREIGN KEY (section_id) REFERENCES m_checksheet_section(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. Section scoping for Checked By entries (m_checker)
-- ============================================================
CALL _deploy_add_column_if_missing('m_checker', 'section_id', 'section_id INT NULL AFTER department_id');
CALL _deploy_add_fk_if_missing('m_checker', 'fk_checker_section', 'FOREIGN KEY (section_id) REFERENCES m_checksheet_section(id) ON DELETE SET NULL');

-- Painting only ever had one section, so its existing rows can be
-- unambiguously backfilled straight away. Assembling's rows are left
-- NULL (unassigned) — they'll keep showing on every Assembling section
-- until routed to one specifically via admin/checkers.php or admin/users.php.
UPDATE m_checker c
JOIN m_department d ON d.id = c.department_id AND d.name = 'Painting'
JOIN m_checksheet_section s ON s.department_id = d.id AND s.route = 'painting_list.php'
SET c.section_id = s.id
WHERE c.section_id IS NULL;

-- ------------------------------------------------------------
-- Clean up helper procedures.
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _deploy_add_column_if_missing;
DROP PROCEDURE IF EXISTS _deploy_add_fk_if_missing;
