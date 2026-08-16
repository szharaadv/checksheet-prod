-- ============================================================
-- Migration: PT Yadin working calendar
-- Powers the custom date-picker widget (replaces the native browser
-- calendar so holidays can be marked/disabled) used on Torque and
-- FO Pump Assy's Date fields. Seeded from the "YADIN WORKING CALENDAR
-- 2026" image the user provided — double-check via admin/calendar_holidays.php
-- since it was transcribed from a photo.
-- Run this ONCE on your existing database.
-- ============================================================

USE cs_painting;

CREATE TABLE IF NOT EXISTS m_calendar_holiday (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    is_workday TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT IGNORE INTO m_calendar_holiday (tanggal, label, is_workday) VALUES
    ('2026-01-01', 'New Year 2026', 0),
    ('2026-01-02', 'YADIN Special Holiday', 0),
    ('2026-01-16', 'Isra Mi''raj', 0),
    ('2026-02-07', 'Working Day (working day)', 1),
    ('2026-02-16', 'Replaced Holiday', 0),
    ('2026-02-17', 'Chinese New Year', 0),
    ('2026-03-19', 'Nyepi', 0),
    ('2026-03-20', 'YADIN Collective Leave', 0),
    ('2026-03-21', 'Iedul Fitri', 0),
    ('2026-03-22', 'Iedul Fitri', 0),
    ('2026-03-23', 'YADIN Collective Leave', 0),
    ('2026-03-24', 'YADIN Collective Leave', 0),
    ('2026-03-25', 'YADIN Collective Leave', 0),
    ('2026-04-03', 'Good Friday', 0),
    ('2026-04-05', 'Easter Day', 0),
    ('2026-05-01', 'International Labor Day', 0),
    ('2026-05-09', 'Working Day (working day)', 1),
    ('2026-05-14', 'Ascension Day of Christ', 0),
    ('2026-05-15', 'Replaced Holiday', 0),
    ('2026-05-23', 'Working Day (working day)', 1),
    ('2026-05-27', 'Idul Adha', 0),
    ('2026-05-31', 'Waisak', 0),
    ('2026-06-01', 'Pancasila Day', 0),
    ('2026-06-15', 'Replaced Holiday', 0),
    ('2026-06-16', 'Islamic New Year', 0),
    ('2026-06-20', 'Working Day (working day)', 1),
    ('2026-07-03', 'YADIN Special Holiday', 0),
    ('2026-08-17', 'Independence Day of Indonesia', 0),
    ('2026-08-24', 'Replaced Holiday', 0),
    ('2026-08-25', 'Prophet Birthday Muhammad SAW', 0),
    ('2026-08-29', 'Working Day (working day)', 1),
    ('2026-09-04', 'YADIN Special Holiday', 0),
    ('2026-12-05', 'Working Day (working day)', 1),
    ('2026-12-25', 'Christmas', 0),
    ('2026-12-28', 'YADIN Collective Leave', 0),
    ('2026-12-29', 'YADIN Collective Leave', 0),
    ('2026-12-30', 'Replaced Holiday', 0),
    ('2026-12-31', 'Replaced Holiday', 0);
