-- ============================================================
-- Migration: Assembly module (separate structure from Painting)
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

-- Tag each department with which checksheet "shape" it uses,
-- so the app knows whether to route to the Condition-based Painting
-- pages or the Model-based Assembly pages.
ALTER TABLE m_department
    ADD COLUMN form_type ENUM('checklist', 'assembly') NOT NULL DEFAULT 'checklist' AFTER name;

UPDATE m_department SET form_type = 'assembly' WHERE name = 'Assembling';

-- ------------------------------------------------------------
-- Master: Assembly Model (TF70, TF70V, TF90V, ...)
-- ------------------------------------------------------------
CREATE TABLE m_assy_model (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_assymodel_department FOREIGN KEY (department_id) REFERENCES m_department(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Master: Assembly checklist item per model
-- ------------------------------------------------------------
CREATE TABLE m_assy_checklist_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_id INT NOT NULL,
    checking_item VARCHAR(255) NOT NULL,
    standard VARCHAR(255) DEFAULT NULL,
    standard_min VARCHAR(50) DEFAULT NULL,
    standard_max VARCHAR(50) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_assyitem_model FOREIGN KEY (model_id) REFERENCES m_assy_model(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Transaction header: one row per submitted/draft assembly checksheet
-- ------------------------------------------------------------
CREATE TABLE t_assy_header (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    department_id INT NOT NULL,
    model_id INT NOT NULL,
    mark_crank_shaft VARCHAR(100) DEFAULT NULL,
    mark_conrod VARCHAR(100) DEFAULT NULL,
    mark_fo_pump VARCHAR(100) DEFAULT NULL,
    no_cyl_block VARCHAR(100) DEFAULT NULL,
    no_engine VARCHAR(100) DEFAULT NULL,
    detail_model VARCHAR(150) DEFAULT NULL,
    checker_id INT NOT NULL,
    status ENUM('draft', 'submitted') NOT NULL DEFAULT 'submitted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assyheader_department FOREIGN KEY (department_id) REFERENCES m_department(id),
    CONSTRAINT fk_assyheader_model FOREIGN KEY (model_id) REFERENCES m_assy_model(id),
    CONSTRAINT fk_assyheader_checker FOREIGN KEY (checker_id) REFERENCES m_checker(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Transaction detail: one row per checklist item filled in
-- ------------------------------------------------------------
CREATE TABLE t_assy_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    header_id INT NOT NULL,
    checklist_item_id INT NOT NULL,
    actual_result VARCHAR(100) DEFAULT NULL,
    consumable_item VARCHAR(100) DEFAULT NULL,
    CONSTRAINT fk_assydetail_header FOREIGN KEY (header_id) REFERENCES t_assy_header(id) ON DELETE CASCADE,
    CONSTRAINT fk_assydetail_item FOREIGN KEY (checklist_item_id) REFERENCES m_assy_checklist_item(id)
) ENGINE=InnoDB;
