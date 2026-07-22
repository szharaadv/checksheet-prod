-- ============================================================
-- Migration: split "Checked By" (m_checker) per department
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

ALTER TABLE m_checker
    ADD COLUMN department_id INT NULL AFTER name;

-- Backfill: adjust these assignments manually afterwards via
-- Configuration > Checked By if this guess doesn't match reality.
UPDATE m_checker SET department_id = (SELECT id FROM m_department WHERE name = 'Painting')
    WHERE name IN ('Tri', 'Mifta');
UPDATE m_checker SET department_id = (SELECT id FROM m_department WHERE name = 'Assembling')
    WHERE name = 'Joko Pamiluto';

-- Anything left unassigned defaults to the first department so the FK below doesn't fail.
UPDATE m_checker SET department_id = (SELECT id FROM m_department ORDER BY sort_order LIMIT 1)
    WHERE department_id IS NULL;

ALTER TABLE m_checker
    MODIFY COLUMN department_id INT NOT NULL,
    ADD CONSTRAINT fk_checker_department FOREIGN KEY (department_id) REFERENCES m_department(id);
