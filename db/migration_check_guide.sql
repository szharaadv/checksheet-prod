-- ============================================================
-- Migration: editable "Checking Guide" (photo + instruction panel)
-- shown on top of a check sheet section. Managed from
-- admin/check_guides.php. One guide per section, many items.
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

CREATE TABLE m_check_guide (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(200) DEFAULT NULL,
    part_name VARCHAR(200) DEFAULT NULL,
    part_image VARCHAR(255) DEFAULT NULL,
    pic_text VARCHAR(255) DEFAULT NULL,
    legend VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_section (section_id),
    CONSTRAINT fk_guide_section FOREIGN KEY (section_id) REFERENCES m_checksheet_section(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE m_check_guide_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guide_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    method VARCHAR(100) DEFAULT NULL,
    checking_item VARCHAR(200) DEFAULT NULL,
    frequency VARCHAR(100) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    caption VARCHAR(200) DEFAULT NULL,
    CONSTRAINT fk_guide_item_guide FOREIGN KEY (guide_id) REFERENCES m_check_guide(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed the existing Sub Assembly guide from the images already extracted.
INSERT INTO m_check_guide (section_id, title, part_name, part_image, pic_text, legend)
SELECT s.id,
       'JIG FOR GUIDEN ASSEMBLY OIL SEAL STARTING SHAFT',
       'Jig for guiden of assembly oil seal starting shaft',
       'assets/img/subassy/part.png',
       'Operator\nsub assy gear case',
       'V = OK, X = NG'
FROM m_checksheet_section s
WHERE s.route = 'subassy_list.php'
LIMIT 1;

INSERT INTO m_check_guide_item (guide_id, sort_order, method, checking_item, frequency, photo, caption)
SELECT g.id, v.sort_order, 'Touched', v.checking_item, 'Before work activity', v.photo, v.caption
FROM m_check_guide g
JOIN m_checksheet_section s ON s.id = g.section_id AND s.route = 'subassy_list.php'
JOIN (
    SELECT 1 AS sort_order, 'Surface outside of jig'       AS checking_item, 'assets/img/subassy/step1.png' AS photo, '1. Surface outside jig'        AS caption
    UNION ALL SELECT 2, 'Parting line',                'assets/img/subassy/step2.jpg', '2. Parting line'
    UNION ALL SELECT 3, 'Surface upper side',          'assets/img/subassy/step3.jpg', '3. Surface upper side'
    UNION ALL SELECT 4, 'Cleanliness inside/ outside', 'assets/img/subassy/step4.jpg', '4. Cleanliness outside/ inside'
) v;
