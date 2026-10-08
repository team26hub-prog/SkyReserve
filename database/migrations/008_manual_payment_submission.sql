-- Existing installations: stop application writes and back up before applying.
-- Use scripts/migrate_module8.php for repeat-safe checks and duplicate detection.
ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','cancelled','expired','payment_submitted') NOT NULL DEFAULT 'pending';
ALTER TABLE payments ADD COLUMN payment_date DATE NULL;
ALTER TABLE payments ADD COLUMN active_booking_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status IN ('pending','verified') THEN booking_id ELSE NULL END
) STORED;
ALTER TABLE payments ADD UNIQUE KEY uq_payments_active_booking (active_booking_id);
