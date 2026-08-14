-- ============================================================
-- Migration: scope Checked By entries per section, not just per
-- department. Assembling has 4 sections (Torque, Washing, Sub Assembly,
-- FO Pump Assy) sharing one department_id, so without this a Checked By
-- entry added for one shows up on all of them.
-- section_id is nullable and NULL means "unassigned" — such entries keep
-- showing on every section of their department (old behavior) until an
-- admin assigns them a specific section in admin/checkers.php.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

ALTER TABLE m_checker
    ADD COLUMN section_id INT NULL AFTER department_id,
    ADD CONSTRAINT fk_checker_section FOREIGN KEY (section_id) REFERENCES m_checksheet_section(id) ON DELETE SET NULL;

-- Painting only ever had one section, so its existing rows can be
-- unambiguously backfilled straight away.
UPDATE m_checker c
JOIN m_department d ON d.id = c.department_id AND d.name = 'Painting'
JOIN m_checksheet_section s ON s.department_id = d.id AND s.route = 'painting_list.php'
SET c.section_id = s.id
WHERE c.section_id IS NULL;
