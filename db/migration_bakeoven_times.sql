-- ============================================================
-- Migration: Bake Oven check times end at 16:30 instead of running
-- through the full 24h cycle. Renames t_1600 -> t_1630 and drops the
-- now-unused later time slots.
-- Run this ONCE on your existing database, AFTER migration_bakeoven.sql.
-- ============================================================

USE cs_painting;

ALTER TABLE t_bakeoven_entry
    CHANGE COLUMN t_1600 t_1630 DECIMAL(5,1) DEFAULT NULL,
    DROP COLUMN t_1800,
    DROP COLUMN t_2000,
    DROP COLUMN t_2200,
    DROP COLUMN t_2400,
    DROP COLUMN t_0200,
    DROP COLUMN t_0400,
    DROP COLUMN t_0600;
