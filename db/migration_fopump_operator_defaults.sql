-- ============================================================
-- Migration bundle for the FO Pump Assy changes made in this session:
--   1. Add 'operator' to the Checked By role enum.
--   2. Add "Mita" (Supervisor) and "Reza Kurnia S" (Operator) to Checked By
--      for the Assembling department, so the FO Pump Assy Excel template's
--      pre-filled Foreman/Supervisor/Operator defaults (Trisna / Mita /
--      Reza Kurnia S) resolve correctly on import.
--   3. Remove any fully-empty FO Pump Assy reports (no employee/shift/
--      signatures/convert and no filled production line at all) — leftover
--      stubs from importing a template before the "skip blank days" fix.
--
-- Safe to run more than once: steps 1-2 check for existing data first,
-- step 3 only deletes rows matching the same "completely blank" criteria
-- used by the app itself.
--
-- Adjust the department name below ('Assembling') if your production
-- department is named differently — check with:
--   SELECT id, name FROM m_department;
-- ============================================================

USE cs_painting;

-- 1. Extend the role enum (no-op if already applied).
ALTER TABLE m_checker
    MODIFY COLUMN role ENUM('foreman', 'supervisor', 'operator') NULL;

-- 2a. Add "Mita" as Supervisor, only if not already present.
INSERT INTO m_checker (name, role, department_id, is_active)
SELECT 'Mita', 'supervisor', d.id, 1
FROM m_department d
WHERE d.name = 'Assembling'
  AND NOT EXISTS (
      SELECT 1 FROM m_checker c
      WHERE c.department_id = d.id AND c.role = 'supervisor' AND LOWER(c.name) = 'mita'
  );

-- 2b. Add "Reza Kurnia S" as Operator, only if not already present.
INSERT INTO m_checker (name, role, department_id, is_active)
SELECT 'Reza Kurnia S', 'operator', d.id, 1
FROM m_department d
WHERE d.name = 'Assembling'
  AND NOT EXISTS (
      SELECT 1 FROM m_checker c
      WHERE c.department_id = d.id AND c.role = 'operator' AND LOWER(c.name) = 'reza kurnia s'
  );

-- 3. Delete fully-blank FO Pump Assy reports (safe: run the SELECT first to review).
-- SELECT h.id, h.tanggal FROM t_fopump_header h WHERE ... (see below) to preview.
DELETE d FROM t_fopump_detail d
JOIN t_fopump_header h ON h.id = d.header_id
WHERE h.id IN (
    SELECT id FROM (
        SELECT h2.id
        FROM t_fopump_header h2
        WHERE TRIM(COALESCE(h2.employee, '')) = '' AND TRIM(COALESCE(h2.working_time, '')) = ''
          AND TRIM(COALESCE(h2.shift, '')) = '' AND TRIM(COALESCE(h2.operator_name, '')) = ''
          AND h2.foreman_id IS NULL AND h2.supervisor_id IS NULL
          AND TRIM(COALESCE(h2.convert_prod, '')) = '' AND TRIM(COALESCE(h2.convert_assy, '')) = ''
          AND TRIM(COALESCE(h2.convert_export, '')) = ''
          AND NOT EXISTS (
              SELECT 1 FROM t_fopump_detail d2
              WHERE d2.header_id = h2.id
                AND (TRIM(COALESCE(d2.prod_model,'')) <> '' OR TRIM(COALESCE(d2.prod_qty,'')) <> ''
                     OR TRIM(COALESCE(d2.assy_model,'')) <> '' OR TRIM(COALESCE(d2.assy_qty,'')) <> ''
                     OR TRIM(COALESCE(d2.export_model,'')) <> '' OR TRIM(COALESCE(d2.export_qty,'')) <> '')
          )
    ) AS blank_ids
);

DELETE FROM t_fopump_header
WHERE TRIM(COALESCE(employee, '')) = '' AND TRIM(COALESCE(working_time, '')) = ''
  AND TRIM(COALESCE(shift, '')) = '' AND TRIM(COALESCE(operator_name, '')) = ''
  AND foreman_id IS NULL AND supervisor_id IS NULL
  AND TRIM(COALESCE(convert_prod, '')) = '' AND TRIM(COALESCE(convert_assy, '')) = ''
  AND TRIM(COALESCE(convert_export, '')) = ''
  AND id NOT IN (SELECT DISTINCT header_id FROM t_fopump_detail);
