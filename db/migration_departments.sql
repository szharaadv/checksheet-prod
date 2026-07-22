-- ============================================================
-- Migration: add Department as a new top-level module
-- (Painting, Assembling, ...) above Condition.
-- Run this ONCE on your existing cs_painting database
-- (safe to re-run: guarded with IF NOT EXISTS / conditional checks).
-- ============================================================

USE cs_painting;

CREATE TABLE IF NOT EXISTS m_department (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO m_department (name, sort_order) VALUES ('Painting', 1);

ALTER TABLE m_condition
    ADD COLUMN department_id INT NULL AFTER id;

UPDATE m_condition
    SET department_id = (SELECT id FROM m_department WHERE name = 'Painting')
    WHERE department_id IS NULL;

ALTER TABLE m_condition
    MODIFY COLUMN department_id INT NOT NULL,
    ADD CONSTRAINT fk_condition_department FOREIGN KEY (department_id) REFERENCES m_department(id);

ALTER TABLE t_checksheet_header
    ADD COLUMN department_id INT NULL AFTER condition_id;

UPDATE t_checksheet_header h
    JOIN m_condition c ON c.id = h.condition_id
    SET h.department_id = c.department_id
    WHERE h.department_id IS NULL;

ALTER TABLE t_checksheet_header
    MODIFY COLUMN department_id INT NOT NULL,
    ADD CONSTRAINT fk_header_department FOREIGN KEY (department_id) REFERENCES m_department(id);
