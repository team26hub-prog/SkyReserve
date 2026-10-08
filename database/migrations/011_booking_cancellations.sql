-- Existing installations: use php scripts/migrate_module11.php for preflight and repeat-safe checks.
-- Stop writes and back up first; MySQL DDL is not transactional.
ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','cancelled','expired','payment_submitted','cancellation_requested') NOT NULL DEFAULT 'pending';
ALTER TABLE cancellations
    ADD COLUMN previous_booking_status ENUM('pending','payment_submitted','confirmed') NULL,
    ADD COLUMN active_booking_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN booking_id ELSE NULL END) STORED,
    ADD UNIQUE KEY uq_cancellations_active_booking (active_booking_id),
    ADD KEY idx_cancellations_booking (booking_id);
ALTER TABLE cancellations DROP INDEX uq_cancellations_booking;
