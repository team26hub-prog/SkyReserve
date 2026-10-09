-- Supports bounded booking trend aggregation without changing business data.
ALTER TABLE bookings ADD INDEX idx_bookings_created (created_at);
