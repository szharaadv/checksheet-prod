-- ============================================================
-- CS Painting Checksheet - Database Schema
-- Web replacement for the PowerApps "Production - Painting List" checksheet
-- ============================================================

CREATE DATABASE IF NOT EXISTS cs_painting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cs_painting;

-- ------------------------------------------------------------
-- Master: Department (Painting, Assembling, ...) - top level module
-- ------------------------------------------------------------
CREATE TABLE m_department (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Master: Condition (Pretreatment, Painting Line, Water Daily Phosphate, ...)
-- belongs to a Department
-- ------------------------------------------------------------
CREATE TABLE m_condition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_condition_department FOREIGN KEY (department_id) REFERENCES m_department(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Master: Checker / "Checked by" list
-- ------------------------------------------------------------
CREATE TABLE m_checker (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('foreman', 'supervisor', 'operator') NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_checker_department FOREIGN KEY (department_id) REFERENCES m_department(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Master: Shift (Non Shift, Shift 1, Shift 2, ...)
-- ------------------------------------------------------------
CREATE TABLE m_shift (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Master: Checklist item per condition (the rows in the middle table)
-- actual_input_type controls how "Actual Result" is rendered:
--   'number'  -> free text/number input (e.g. pressure, temperature)
--   'select'  -> dropdown using actual_options (e.g. Tidak Bocor / Bocor)
-- category_options: comma separated options for the "Category" dropdown
--   (e.g. "OK,NG" or "Good,No Good"), nullable if not used
-- ------------------------------------------------------------
CREATE TABLE m_checklist_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    condition_id INT NOT NULL,
    checking_item VARCHAR(255) NOT NULL,
    metode_pengecekan VARCHAR(100) NOT NULL DEFAULT 'Visual',
    standard_min VARCHAR(50) DEFAULT NULL,
    standard_max VARCHAR(50) DEFAULT NULL,
    tank_tube VARCHAR(50) DEFAULT '-',
    satuan VARCHAR(50) DEFAULT '-',
    actual_input_type ENUM('number','text','select') NOT NULL DEFAULT 'number',
    actual_options VARCHAR(255) DEFAULT NULL,
    category_options VARCHAR(255) DEFAULT 'OK,NG',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_item_condition FOREIGN KEY (condition_id) REFERENCES m_condition(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Transaction header: one row per submitted checksheet
-- (Date + Condition + Checked by + Jam + Shift, matches top form)
-- ------------------------------------------------------------
CREATE TABLE t_checksheet_header (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    department_id INT NOT NULL,
    condition_id INT NOT NULL,
    checker_id INT NOT NULL,
    jam TIME NOT NULL,
    shift_id INT NOT NULL,
    status ENUM('draft', 'submitted') NOT NULL DEFAULT 'submitted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_header_department FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_header_condition FOREIGN KEY (condition_id) REFERENCES m_condition(id),
    CONSTRAINT fk_header_checker FOREIGN KEY (checker_id) REFERENCES m_checker(id),
    CONSTRAINT fk_header_shift FOREIGN KEY (shift_id) REFERENCES m_shift(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Transaction detail: one row per checklist item filled in
-- ------------------------------------------------------------
CREATE TABLE t_checksheet_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    header_id INT NOT NULL,
    checklist_item_id INT NOT NULL,
    actual_result VARCHAR(100) DEFAULT NULL,
    category VARCHAR(50) DEFAULT NULL,
    CONSTRAINT fk_detail_header FOREIGN KEY (header_id) REFERENCES t_checksheet_header(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_item FOREIGN KEY (checklist_item_id) REFERENCES m_checklist_item(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed: base master data (conditions, shift, sample checker)
-- Checklist items (m_checklist_item) master data will be provided later.
-- ------------------------------------------------------------
INSERT INTO m_department (name, sort_order) VALUES
    ('Painting', 1),
    ('Assembling', 2);

INSERT INTO m_condition (department_id, name, sort_order) VALUES
    ((SELECT id FROM m_department WHERE name = 'Painting'), 'Pretreatment', 1),
    ((SELECT id FROM m_department WHERE name = 'Painting'), 'Painting Line', 2),
    ((SELECT id FROM m_department WHERE name = 'Painting'), 'Water Daily Phosphate', 3);

INSERT INTO m_shift (name, sort_order) VALUES
    ('Non Shift', 1),
    ('Shift 1', 2),
    ('Shift 2', 3);

INSERT INTO m_checker (department_id, name) VALUES
    ((SELECT id FROM m_department WHERE name = 'Painting'), 'Tri');
