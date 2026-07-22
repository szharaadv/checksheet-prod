-- ============================================================
-- Master data: m_checklist_item
-- Run this AFTER schema.sql (needs m_condition rows to already exist)
--
-- Assumptions made while mapping the source spreadsheet:
--   - Rows with Standard Min = Standard Max as TEXT (e.g. "Tidak Bocor")
--     become a dropdown (actual_input_type = 'select') with the standard
--     value plus a logical opposite as the two options.
--   - "Water Daily Phosphate" had the same 6 rows listed twice; assumed
--     to be two lines/tanks and labeled "(Line 1)" / "(Line 2)".
--     Rename via UPDATE if that assumption is wrong.
--   - "Rail conveyor condition" (48Hz/48Hz) and the two "Penambahan ..."
--     rows in Water Daily Phosphate ("0.5 = 1", "0.5 = 400") are not
--     plain numbers, so actual_input_type = 'text' (free text entry).
-- ============================================================

USE cs_painting;

SET @pretreatment = (SELECT id FROM m_condition WHERE name = 'Pretreatment');
SET @painting_line = (SELECT id FROM m_condition WHERE name = 'Painting Line');
SET @water_phosphate = (SELECT id FROM m_condition WHERE name = 'Water Daily Phosphate');

-- ------------------------------------------------------------
-- Pretreatment
-- ------------------------------------------------------------
INSERT INTO m_checklist_item
    (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan, actual_input_type, actual_options, sort_order)
VALUES
(@pretreatment, 'Water pump pressure (tank 1)',        'Visual', '1.2', '1.4', '-', '-', 'number', NULL, 1),
(@pretreatment, 'Water phosphat temperature (tank 1)',  'Visual', '50',  '60',  '-', '-', 'number', NULL, 2),
(@pretreatment, 'Water pump leak tube1',                'Visual', 'Tidak Bocor', 'Tidak Bocor', '-', '-', 'select', 'Tidak Bocor,Bocor', 3),
(@pretreatment, 'Filter condition tank1',                'Visual', 'Tidak Mampet', 'Tidak Mampet', '-', '-', 'select', 'Tidak Mampet,Mampet', 4),
(@pretreatment, 'Water pump pressure (tank 2)',         'Visual', '1.2', '1.4', '-', '-', 'number', NULL, 5),
(@pretreatment, 'Water phosphat temperature (tank 2)',  'Visual', '50',  '60',  '-', '-', 'number', NULL, 6),
(@pretreatment, 'Water pump leak tube2',                'Visual', 'Tidak Bocor', 'Tidak Bocor', '-', '-', 'select', 'Tidak Bocor,Bocor', 7),
(@pretreatment, 'Filter condition tank2',                'Visual', 'Tidak Mampet', 'Tidak Mampet', '-', '-', 'select', 'Tidak Mampet,Mampet', 8),
(@pretreatment, 'Water pump pressure (tank 3)',         'Visual', '1.2', '1.5', '-', '-', 'number', NULL, 9),
(@pretreatment, 'Water phosphat temperature (tank 3)',  'Visual', '60',  '70',  '-', '-', 'number', NULL, 10),
(@pretreatment, 'Water pump leak tube3',                'Visual', 'Tidak Bocor', 'Tidak Bocor', '-', '-', 'select', 'Tidak Bocor,Bocor', 11),
(@pretreatment, 'Filter condition tank3',                'Visual', 'Tidak Mampet', 'Tidak Mampet', '-', '-', 'select', 'Tidak Mampet,Mampet', 12),
(@pretreatment, 'Visual phosphat spray tank1',          'Visual', 'Kabut', 'Kabut', '-', '-', 'select', 'Kabut,Tidak Kabut', 13),
(@pretreatment, 'Visual phosphat spray tank2',          'Visual', 'Kabut', 'Kabut', '-', '-', 'select', 'Kabut,Tidak Kabut', 14),
(@pretreatment, 'Visual phosphat spray tank3',          'Visual', 'Kabut', 'Kabut', '-', '-', 'select', 'Kabut,Tidak Kabut', 15),
(@pretreatment, 'Total Acid phosphate value',           'Test',   '6.5', '7.5', '-', '-', 'number', NULL, 16);

-- ------------------------------------------------------------
-- Painting Line
-- ------------------------------------------------------------
INSERT INTO m_checklist_item
    (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan, actual_input_type, actual_options, sort_order)
VALUES
(@painting_line, 'Temperatur oven (drying after treatment)', 'Baca Digital',   '60', '70', '-', '-', 'number', NULL, 1),
(@painting_line, 'Infra red oven condition',                 'Visual',         'Tidak Pecah/Mati', 'Tidak Pecah/Mati', '-', '-', 'select', 'Tidak Pecah/Mati,Pecah/Mati', 2),
(@painting_line, 'Paint mixer condition 2 unit',              'Visual',         'Tidak Macet', 'Tidak Macet', '-', '-', 'select', 'Tidak Macet,Macet', 3),
(@painting_line, 'Exhaust fan 1',                             'Visual PB lamp', 'PB Lamp ON', 'PB Lamp ON', '-', '-', 'select', 'PB Lamp ON,PB Lamp OFF', 4),
(@painting_line, 'Water circulating pump1',                   'Visual',         'Arah Air Ke Dalam', 'Arah Air Ke Dalam', '-', '-', 'select', 'Arah Air Ke Dalam,Tidak Sesuai', 5),
(@painting_line, 'Paint mixer condition 2 unit',              'Visual',         'Tidak Macet', 'Tidak Macet', '-', '-', 'select', 'Tidak Macet,Macet', 6),
(@painting_line, 'Exhaust fan 2',                             'Visual PB lamp', 'PB Lamp ON', 'PB Lamp ON', '-', '-', 'select', 'PB Lamp ON,PB Lamp OFF', 7),
(@painting_line, 'Water circulating pump2',                   'Visual',         'Arah Air Ke Dalam', 'Arah Air Ke Dalam', '-', '-', 'select', 'Arah Air Ke Dalam,Tidak Sesuai', 8),
(@painting_line, 'Temperatur Burner',                          'Baca Digital',   '300', '300', '-', '-', 'number', NULL, 9),
(@painting_line, 'Temperature in the box drying parts',       'Baca digital',   '160', '165', '-', '-', 'number', NULL, 10),
(@painting_line, 'Paint mixer condition 2 unit',              'Visual',         'Tidak Macet', 'Tidak Macet', '-', '-', 'select', 'Tidak Macet,Macet', 11),
(@painting_line, 'Exhaust fan 3',                             'Visual PB lamp', 'PB Lamp ON', 'PB Lamp ON', '-', '-', 'select', 'PB Lamp ON,PB Lamp OFF', 12),
(@painting_line, 'Water circulating pump3',                   'Visual',         'Arah Air Ke Dalam', 'Arah Air Ke Dalam', '-', '-', 'select', 'Arah Air Ke Dalam,Tidak Sesuai', 13),
(@painting_line, 'Rail conveyor condition',                    'Visual',         '48Hz', '48Hz', '-', '-', 'text', NULL, 14);

-- ------------------------------------------------------------
-- Water Daily Phosphate
-- (Line 1 / Line 2 labeling is an assumption - rename if incorrect)
-- ------------------------------------------------------------
INSERT INTO m_checklist_item
    (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan, actual_input_type, actual_options, sort_order)
VALUES
(@water_phosphate, 'Total Acid Phosphate (Line 1)',                  '-', '6.5', '7.5',     '-', '-',  'number', NULL, 1),
(@water_phosphate, 'Penambahan Phalphost (Line 1)',                  '-', '0.5 = 1', '0.5 = 1', '-', 'kg', 'text', NULL, 2),
(@water_phosphate, 'Total Acid setelah penambahan (Line 1)',         '-', '6.5', '7.5',     '-', '-',  'number', NULL, 3),
(@water_phosphate, 'Acid Consume Phosphate (Line 1)',                '-', '0.4', '0.5',     '-', '-',  'number', NULL, 4),
(@water_phosphate, 'Penambahan netralizier ( Soda as ) (Line 1)',    '-', '0.5 = 400', '0.5 = 400', '-', 'ml', 'text', NULL, 5),
(@water_phosphate, 'Acid Consume setelah penambahan (Line 1)',       '-', '0.4', '0.5',     '-', '-',  'number', NULL, 6),
(@water_phosphate, 'Total Acid Phosphate (Line 2)',                  '-', '6.5', '7.5',     '-', '-',  'number', NULL, 7),
(@water_phosphate, 'Penambahan Phalphost (Line 2)',                  '-', '0.5 = 1', '0.5 = 1', '-', 'kg', 'text', NULL, 8),
(@water_phosphate, 'Total Acid setelah penambahan (Line 2)',         '-', '6.5', '7.5',     '-', '-',  'number', NULL, 9),
(@water_phosphate, 'Acid Consume Phosphate (Line 2)',                '-', '0.4', '0.5',     '-', '-',  'number', NULL, 10),
(@water_phosphate, 'Penambahan netralizier ( Soda as ) (Line 2)',    '-', '0.5 = 400', '0.5 = 400', '-', 'ml', 'text', NULL, 11),
(@water_phosphate, 'Acid Consume setelah penambahan (Line 2)',       '-', '0.4', '0.5',     '-', '-',  'number', NULL, 12);
