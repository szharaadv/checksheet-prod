-- ============================================================
-- Migration: "FO Pump Assy" daily report (Assembling department)
-- Source form: F-FIP-03 "DAILY REPORT FO PUMP ASSY".
-- One report per department per calendar day (header + 9 rows).
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

-- New section under Assembling.
INSERT INTO m_checksheet_section (department_id, name, route, section_type, sort_order) VALUES
    ((SELECT id FROM m_department WHERE name = 'Assembling'), 'FO Pump Assy', 'fopump_list.php', 'fopump_report', 4);

-- ------------------------------------------------------------
-- Header: one row per report (per department per day).
-- ------------------------------------------------------------
CREATE TABLE t_fopump_header (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    tanggal DATE NOT NULL,
    employee VARCHAR(150) DEFAULT NULL,
    working_time VARCHAR(100) DEFAULT NULL,
    shift VARCHAR(50) DEFAULT NULL,
    operator_name VARCHAR(150) DEFAULT NULL,
    foreman_id INT DEFAULT NULL,
    supervisor_id INT DEFAULT NULL,
    convert_prod VARCHAR(50) DEFAULT NULL,
    convert_assy VARCHAR(50) DEFAULT NULL,
    convert_export VARCHAR(50) DEFAULT NULL,
    accum_prod VARCHAR(50) DEFAULT NULL,
    accum_assy VARCHAR(50) DEFAULT NULL,
    accum_export VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dept_date (department_id, tanggal),
    CONSTRAINT fk_fopump_department FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_fopump_foreman FOREIGN KEY (foreman_id) REFERENCES m_checker(id),
    CONSTRAINT fk_fopump_supervisor FOREIGN KEY (supervisor_id) REFERENCES m_checker(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Detail: the 9 numbered production rows.
-- ------------------------------------------------------------
CREATE TABLE t_fopump_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    header_id INT NOT NULL,
    row_no INT NOT NULL,
    prod_model VARCHAR(100) DEFAULT NULL,
    prod_qty VARCHAR(50) DEFAULT NULL,
    assy_model VARCHAR(100) DEFAULT NULL,
    assy_qty VARCHAR(50) DEFAULT NULL,
    export_model VARCHAR(100) DEFAULT NULL,
    export_qty VARCHAR(50) DEFAULT NULL,
    CONSTRAINT fk_fopump_detail_header FOREIGN KEY (header_id) REFERENCES t_fopump_header(id) ON DELETE CASCADE
) ENGINE=InnoDB;
