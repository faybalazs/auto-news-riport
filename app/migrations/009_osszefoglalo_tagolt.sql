-- Vezetői összefoglaló tagolva: csoportok (Újdonságok, Tények, számok, Trendek) pontokkal, JSON-ban. A details mező a bevezető.

ALTER TABLE month_reports ADD COLUMN sections LONGTEXT NULL AFTER details;
ALTER TABLE month_reports ADD COLUMN updated_at DATETIME NULL AFTER created_at;
