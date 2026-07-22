-- ============================================================
-- Migration: add draft support to checksheet submissions
-- Run this ONCE on your existing cs_painting database.
-- ============================================================

USE cs_painting;

ALTER TABLE t_checksheet_header
    ADD COLUMN status ENUM('draft', 'submitted') NOT NULL DEFAULT 'submitted' AFTER shift_id;
