-- ============================================================
-- Migration: Bake Oven Temperature check sheet (Painting)
-- Matches the paper form "FORM PENGECEKAN TEMPERATUR BAKE OVEN"
-- (F-PS-07): 13 fixed check times per day across a whole month, one
-- Paraf (checker) per day, plus a single Asst. Foreman / Foreman /
-- Supervisor sign-off + Keterangan for the whole month.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

INSERT INTO m_checksheet_section (department_id, name, route, section_type, sort_order)
SELECT id, 'Bake Oven Temperature', 'bakeoven_list.php', 'bakeoven_monitor', 2
FROM m_department WHERE name = 'Painting'
AND NOT EXISTS (SELECT 1 FROM m_checksheet_section WHERE route = 'bakeoven_list.php');

INSERT IGNORE INTO m_checker_role (name, label, sort_order) VALUES ('asst_foreman', 'Asst. Foreman', 4);

-- ------------------------------------------------------------
-- One row per day: 13 fixed check times + who paraf'd that day.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_bakeoven_entry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    tanggal DATE NOT NULL,
    t_0700 DECIMAL(5,1) DEFAULT NULL,
    t_0900 DECIMAL(5,1) DEFAULT NULL,
    t_1100 DECIMAL(5,1) DEFAULT NULL,
    t_1300 DECIMAL(5,1) DEFAULT NULL,
    t_1400 DECIMAL(5,1) DEFAULT NULL,
    t_1600 DECIMAL(5,1) DEFAULT NULL,
    t_1800 DECIMAL(5,1) DEFAULT NULL,
    t_2000 DECIMAL(5,1) DEFAULT NULL,
    t_2200 DECIMAL(5,1) DEFAULT NULL,
    t_2400 DECIMAL(5,1) DEFAULT NULL,
    t_0200 DECIMAL(5,1) DEFAULT NULL,
    t_0400 DECIMAL(5,1) DEFAULT NULL,
    t_0600 DECIMAL(5,1) DEFAULT NULL,
    checker_id INT DEFAULT NULL,
    UNIQUE KEY uq_bakeoven_dept_date (department_id, tanggal),
    CONSTRAINT fk_bakeoven_entry_dept FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_bakeoven_entry_checker FOREIGN KEY (checker_id) REFERENCES m_checker(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- One row per (department, month, year): the sheet-level sign-off
-- fields that only appear once per page on the paper form.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_bakeoven_month (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    month TINYINT NOT NULL,
    year SMALLINT NOT NULL,
    asst_foreman_id INT DEFAULT NULL,
    foreman_id INT DEFAULT NULL,
    supervisor_id INT DEFAULT NULL,
    keterangan VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_bakeoven_month (department_id, month, year),
    CONSTRAINT fk_bakeoven_month_dept FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_bakeoven_month_asst FOREIGN KEY (asst_foreman_id) REFERENCES m_checker(id),
    CONSTRAINT fk_bakeoven_month_foreman FOREIGN KEY (foreman_id) REFERENCES m_checker(id),
    CONSTRAINT fk_bakeoven_month_supervisor FOREIGN KEY (supervisor_id) REFERENCES m_checker(id)
) ENGINE=InnoDB;
