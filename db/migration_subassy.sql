-- ============================================================
-- Migration: "Sub Assembly" monthly jig check sheet
-- (Assembling department) — mirrors the Washing Machine Liquid
-- Monitoring log: one row per calendar day.
-- Source form: F-AS-01 "CHECK SHEET - SUB ASSEMBLY /
-- JIG FOR GUIDEN ASSEMBLY OIL SEAL STARTING SHAFT".
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

-- New section under Assembling.
INSERT INTO m_checksheet_section (department_id, name, route, section_type, sort_order) VALUES
    ((SELECT id FROM m_department WHERE name = 'Assembling'), 'Sub Assembly', 'subassy_list.php', 'subassy_monitor', 3);

-- ------------------------------------------------------------
-- Sub Assembly jig check: one row per calendar day.
-- Each check column stores 'OK' / 'NG' (V = OK, X = NG on paper).
-- ------------------------------------------------------------
CREATE TABLE t_subassy_entry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    tanggal DATE NOT NULL,
    surface_outside VARCHAR(10) DEFAULT NULL,
    parting_line VARCHAR(10) DEFAULT NULL,
    surface_upper VARCHAR(10) DEFAULT NULL,
    cleanliness VARCHAR(10) DEFAULT NULL,
    checker_id INT DEFAULT NULL,
    supervisor_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dept_date (department_id, tanggal),
    CONSTRAINT fk_subassy_department FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_subassy_checker FOREIGN KEY (checker_id) REFERENCES m_checker(id),
    CONSTRAINT fk_subassy_supervisor FOREIGN KEY (supervisor_id) REFERENCES m_checker(id)
) ENGINE=InnoDB;
