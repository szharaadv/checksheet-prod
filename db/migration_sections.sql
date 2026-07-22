-- ============================================================
-- Migration: multiple checksheet "sections" per department
-- (e.g. Assembling now has both the Engine Checklist and the
-- Washing Machine Liquid Monitoring log)
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

CREATE TABLE m_checksheet_section (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    route VARCHAR(100) NOT NULL,
    section_type VARCHAR(30) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_section_department FOREIGN KEY (department_id) REFERENCES m_department(id)
) ENGINE=InnoDB;

INSERT INTO m_checksheet_section (department_id, name, route, section_type, sort_order) VALUES
    ((SELECT id FROM m_department WHERE name = 'Painting'), 'Painting Checklist', 'painting_list.php', 'painting_checklist', 1),
    ((SELECT id FROM m_department WHERE name = 'Assembling'), 'Torque', 'assembly_list.php', 'assembly_checklist', 1),
    ((SELECT id FROM m_department WHERE name = 'Assembling'), 'Washing Machine Liquid Monitoring', 'washing_liquid_list.php', 'washing_monitor', 2);

-- ------------------------------------------------------------
-- Washing Machine Liquid Monitoring: one row per calendar day
-- ------------------------------------------------------------
CREATE TABLE t_washing_entry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    tanggal DATE NOT NULL,
    ganti_air VARCHAR(50) DEFAULT NULL,
    temperatur_air VARCHAR(50) DEFAULT NULL,
    penambahan_gildaon VARCHAR(50) DEFAULT NULL,
    total_acid VARCHAR(50) DEFAULT NULL,
    checker_id INT DEFAULT NULL,
    supervisor_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dept_date (department_id, tanggal),
    CONSTRAINT fk_washing_department FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_washing_checker FOREIGN KEY (checker_id) REFERENCES m_checker(id),
    CONSTRAINT fk_washing_supervisor FOREIGN KEY (supervisor_id) REFERENCES m_checker(id)
) ENGINE=InnoDB;
