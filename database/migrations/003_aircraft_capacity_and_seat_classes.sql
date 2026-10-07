-- Run on an existing Module 1/2 database, with application writes stopped.
-- scripts/migrate_module3.php provides preflight checks and repeat-safe execution.
ALTER TABLE aircraft ADD COLUMN total_capacity INT UNSIGNED NOT NULL DEFAULT 1 AFTER model;

-- Backfill or resume safely, preserving any larger capacity already configured.
UPDATE aircraft a SET total_capacity = GREATEST(total_capacity, 1, (SELECT COUNT(*) FROM seats s WHERE s.aircraft_id = a.id));

ALTER TABLE aircraft ADD CONSTRAINT chk_aircraft_capacity CHECK (total_capacity > 0);

-- The migration script refuses to narrow this enum if first-class rows exist.
ALTER TABLE seats MODIFY COLUMN cabin_class ENUM('economy', 'business') NOT NULL DEFAULT 'economy';
