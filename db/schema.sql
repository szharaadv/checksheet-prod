-- ============================================================
-- Check Sheet — full database schema (structure only)
-- Database: cs_painting  (MariaDB 10.4)
-- Reconstructed from information_schema (.frm definitions).
-- Includes all newest tables: users/roles/permissions, checker
-- sections, calendar holidays, check guides, bake oven, FO Pump
-- report, and the F-FIP-01 check sheet (m_fpcs_* / t_fpcs_*).
-- ============================================================

CREATE DATABASE IF NOT EXISTS cs_painting CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE cs_painting;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `m_assy_checklist_item`;
CREATE TABLE `m_assy_checklist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `standard` varchar(255) NULL DEFAULT NULL,
  `standard_min` varchar(50) NULL DEFAULT NULL,
  `standard_max` varchar(50) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_assyitem_model` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_assy_model`;
CREATE TABLE `m_assy_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_assymodel_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_calendar_holiday`;
CREATE TABLE `m_calendar_holiday` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `label` varchar(150) NOT NULL,
  `is_workday` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_checker`;
CREATE TABLE `m_checker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NULL DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `section_id` int(11) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_checker_department` (`department_id`),
  KEY `fk_checker_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_checker_role`;
CREATE TABLE `m_checker_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_checklist_item`;
CREATE TABLE `m_checklist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `condition_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `metode_pengecekan` varchar(100) NOT NULL DEFAULT 'Visual',
  `standard_min` varchar(50) NULL DEFAULT NULL,
  `standard_max` varchar(50) NULL DEFAULT NULL,
  `tank_tube` varchar(50) NULL DEFAULT '-',
  `satuan` varchar(50) NULL DEFAULT '-',
  `actual_input_type` enum('number','text','select') NOT NULL DEFAULT 'number',
  `actual_options` varchar(255) NULL DEFAULT NULL,
  `category_options` varchar(255) NULL DEFAULT 'OK,NG',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_item_condition` (`condition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_checksheet_section`;
CREATE TABLE `m_checksheet_section` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `route` varchar(100) NOT NULL,
  `section_type` varchar(30) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_section_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_check_guide`;
CREATE TABLE `m_check_guide` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `title` varchar(200) NULL DEFAULT NULL,
  `part_name` varchar(200) NULL DEFAULT NULL,
  `part_image` varchar(255) NULL DEFAULT NULL,
  `pic_text` varchar(255) NULL DEFAULT NULL,
  `legend` varchar(100) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_check_guide_item`;
CREATE TABLE `m_check_guide_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guide_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `method` varchar(100) NULL DEFAULT NULL,
  `checking_item` varchar(200) NULL DEFAULT NULL,
  `frequency` varchar(100) NULL DEFAULT NULL,
  `photo` varchar(255) NULL DEFAULT NULL,
  `caption` varchar(200) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_guide_item_guide` (`guide_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_condition`;
CREATE TABLE `m_condition` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_condition_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_department`;
CREATE TABLE `m_department` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `form_type` enum('checklist','assembly') NOT NULL DEFAULT 'checklist',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_fpcs_point`;
CREATE TABLE `m_fpcs_point` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `no` varchar(10) NULL DEFAULT NULL,
  `check_point` varchar(150) NOT NULL,
  `row_type` enum('group','item') NOT NULL DEFAULT 'item',
  `input_type` enum('text','number','okng','truefalse') NOT NULL DEFAULT 'text',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_fpcsp_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_fpcs_standard`;
CREATE TABLE `m_fpcs_standard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variant_id` int(11) NOT NULL,
  `point_id` int(11) NOT NULL,
  `std_1` varchar(100) NULL DEFAULT NULL,
  `std_2` varchar(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fpcss_point` (`point_id`),
  UNIQUE KEY `uq_var_point` (`variant_id`, `point_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_fpcs_variant`;
CREATE TABLE `m_fpcs_variant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `std_label_1` varchar(80) NULL DEFAULT NULL,
  `std_label_2` varchar(80) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_fpcsv_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_role`;
CREATE TABLE `m_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `is_full_access` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_role_permission`;
CREATE TABLE `m_role_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `perm_key` varchar(50) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_perm` (`role`, `perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_shift`;
CREATE TABLE `m_shift` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_user`;
CREATE TABLE `m_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `checker_role` varchar(50) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_user_section`;
CREATE TABLE `m_user_section` (
  `user_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`, `section_id`),
  KEY `fk_us_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_assy_detail`;
CREATE TABLE `t_assy_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `actual_result` varchar(100) NULL DEFAULT NULL,
  `consumable_item` varchar(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_assydetail_header` (`header_id`),
  KEY `fk_assydetail_item` (`checklist_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_assy_header`;
CREATE TABLE `t_assy_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `department_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `mark_crank_shaft` varchar(100) NULL DEFAULT NULL,
  `mark_conrod` varchar(100) NULL DEFAULT NULL,
  `mark_fo_pump` varchar(100) NULL DEFAULT NULL,
  `no_cyl_block` varchar(100) NULL DEFAULT NULL,
  `no_engine` varchar(100) NULL DEFAULT NULL,
  `detail_model` varchar(150) NULL DEFAULT NULL,
  `checker_id` int(11) NOT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_assyheader_checker` (`checker_id`),
  KEY `fk_assyheader_department` (`department_id`),
  KEY `fk_assyheader_model` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_bakeoven_entry`;
CREATE TABLE `t_bakeoven_entry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `t_0700` decimal(5,1) NULL DEFAULT NULL,
  `t_0900` decimal(5,1) NULL DEFAULT NULL,
  `t_1100` decimal(5,1) NULL DEFAULT NULL,
  `t_1300` decimal(5,1) NULL DEFAULT NULL,
  `t_1400` decimal(5,1) NULL DEFAULT NULL,
  `t_1630` decimal(5,1) NULL DEFAULT NULL,
  `checker_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bakeoven_entry_checker` (`checker_id`),
  UNIQUE KEY `uq_bakeoven_dept_date` (`department_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_bakeoven_month`;
CREATE TABLE `t_bakeoven_month` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `year` smallint(6) NOT NULL,
  `asst_foreman_id` int(11) NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `keterangan` varchar(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bakeoven_month_asst` (`asst_foreman_id`),
  KEY `fk_bakeoven_month_foreman` (`foreman_id`),
  KEY `fk_bakeoven_month_supervisor` (`supervisor_id`),
  UNIQUE KEY `uq_bakeoven_month` (`department_id`, `month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_checksheet_detail`;
CREATE TABLE `t_checksheet_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `actual_result` varchar(100) NULL DEFAULT NULL,
  `category` varchar(50) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detail_header` (`header_id`),
  KEY `fk_detail_item` (`checklist_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_checksheet_header`;
CREATE TABLE `t_checksheet_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `condition_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `checker_id` int(11) NOT NULL,
  `jam` time NOT NULL,
  `shift_id` int(11) NOT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_header_checker` (`checker_id`),
  KEY `fk_header_condition` (`condition_id`),
  KEY `fk_header_department` (`department_id`),
  KEY `fk_header_shift` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_detail`;
CREATE TABLE `t_fopump_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `row_no` int(11) NOT NULL,
  `prod_model` varchar(100) NULL DEFAULT NULL,
  `prod_qty` varchar(50) NULL DEFAULT NULL,
  `assy_model` varchar(100) NULL DEFAULT NULL,
  `assy_qty` varchar(50) NULL DEFAULT NULL,
  `export_model` varchar(100) NULL DEFAULT NULL,
  `export_qty` varchar(50) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fopump_detail_header` (`header_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_header`;
CREATE TABLE `t_fopump_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `employee` varchar(150) NULL DEFAULT NULL,
  `working_time` varchar(100) NULL DEFAULT NULL,
  `shift` varchar(50) NULL DEFAULT NULL,
  `operator_name` varchar(150) NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `convert_prod` varchar(50) NULL DEFAULT NULL,
  `convert_assy` varchar(50) NULL DEFAULT NULL,
  `convert_export` varchar(50) NULL DEFAULT NULL,
  `accum_prod` varchar(50) NULL DEFAULT NULL,
  `accum_assy` varchar(50) NULL DEFAULT NULL,
  `accum_export` varchar(50) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_fopump_foreman` (`foreman_id`),
  KEY `fk_fopump_supervisor` (`supervisor_id`),
  UNIQUE KEY `uniq_dept_date` (`department_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fpcs_cell`;
CREATE TABLE `t_fpcs_cell` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `point_id` int(11) NOT NULL,
  `col_index` int(11) NOT NULL,
  `value` varchar(50) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fpcscell_header` (`header_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fpcs_column`;
CREATE TABLE `t_fpcs_column` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `col_index` int(11) NOT NULL,
  `label` varchar(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fpcsc_header` (`header_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fpcs_header`;
CREATE TABLE `t_fpcs_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `variant_id` int(11) NULL DEFAULT NULL,
  `tanggal` date NOT NULL,
  `model` varchar(50) NULL DEFAULT NULL,
  `p_code` varchar(50) NULL DEFAULT NULL,
  `part_no` varchar(50) NULL DEFAULT NULL,
  `prod_date` varchar(30) NULL DEFAULT NULL,
  `check_method` varchar(50) NULL DEFAULT NULL,
  `checker` varchar(100) NULL DEFAULT NULL,
  `foreman` varchar(100) NULL DEFAULT NULL,
  `supervisor` varchar(100) NULL DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_subassy_entry`;
CREATE TABLE `t_subassy_entry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `surface_outside` varchar(10) NULL DEFAULT NULL,
  `parting_line` varchar(10) NULL DEFAULT NULL,
  `surface_upper` varchar(10) NULL DEFAULT NULL,
  `cleanliness` varchar(10) NULL DEFAULT NULL,
  `checker_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_subassy_checker` (`checker_id`),
  KEY `fk_subassy_supervisor` (`supervisor_id`),
  UNIQUE KEY `uniq_dept_date` (`department_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_washing_entry`;
CREATE TABLE `t_washing_entry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `ganti_air` varchar(50) NULL DEFAULT NULL,
  `temperatur_air` varchar(50) NULL DEFAULT NULL,
  `penambahan_gildaon` varchar(50) NULL DEFAULT NULL,
  `total_acid` varchar(50) NULL DEFAULT NULL,
  `checker_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_washing_checker` (`checker_id`),
  KEY `fk_washing_supervisor` (`supervisor_id`),
  UNIQUE KEY `uniq_dept_date` (`department_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
