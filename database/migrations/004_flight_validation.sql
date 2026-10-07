-- Upgrade existing flights without adding tables or changing their relationships.
-- scripts/migrate_module4.php preflights existing fares and departed statuses.
ALTER TABLE flights
    DROP CHECK chk_flights_fare,
    ADD CONSTRAINT chk_flights_fare CHECK (base_fare > 0),
    MODIFY COLUMN status ENUM('scheduled', 'delayed', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled';
