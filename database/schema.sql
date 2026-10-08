-- Current schema through Module 8. Import into an empty MySQL 8.0.16+ database.
-- All times are UTC. Monetary values use the booking's currency.
-- This is an initial schema, not a migration: existing tables cause an error.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE airports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    iata_code CHAR(3) NOT NULL,
    name VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_airports_iata (iata_code),
    KEY idx_airports_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aircraft (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_number VARCHAR(20) NOT NULL,
    model VARCHAR(100) NOT NULL,
    total_capacity INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_aircraft_registration (registration_number),
    KEY idx_aircraft_status (status),
    CONSTRAINT chk_aircraft_capacity CHECK (total_capacity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aircraft_id BIGINT UNSIGNED NOT NULL,
    seat_number VARCHAR(8) NOT NULL,
    cabin_class ENUM('economy', 'business') NOT NULL DEFAULT 'economy',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_seats_aircraft_number (aircraft_id, seat_number),
    UNIQUE KEY uq_seats_id_aircraft (id, aircraft_id),
    CONSTRAINT fk_seats_aircraft FOREIGN KEY (aircraft_id) REFERENCES aircraft (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flight_number VARCHAR(12) NOT NULL,
    aircraft_id BIGINT UNSIGNED NOT NULL,
    origin_airport_id BIGINT UNSIGNED NOT NULL,
    destination_airport_id BIGINT UNSIGNED NOT NULL,
    departure_at DATETIME NOT NULL,
    arrival_at DATETIME NOT NULL,
    base_fare DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PKR',
    status ENUM('scheduled', 'delayed', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_flights_number_departure (flight_number, departure_at),
    UNIQUE KEY uq_flights_id_aircraft (id, aircraft_id),
    KEY idx_flights_search (origin_airport_id, destination_airport_id, departure_at, status),
    KEY idx_flights_aircraft_departure (aircraft_id, departure_at),
    KEY idx_flights_destination (destination_airport_id),
    CONSTRAINT fk_flights_aircraft FOREIGN KEY (aircraft_id) REFERENCES aircraft (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_flights_origin FOREIGN KEY (origin_airport_id) REFERENCES airports (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_flights_destination FOREIGN KEY (destination_airport_id) REFERENCES airports (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_flights_airports CHECK (origin_airport_id <> destination_airport_id),
    CONSTRAINT chk_flights_times CHECK (arrival_at > departure_at),
    CONSTRAINT chk_flights_fare CHECK (base_fare > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(20) NOT NULL,
    submission_key CHAR(64) NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    flight_id BIGINT UNSIGNED NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PKR',
    status ENUM('pending', 'confirmed', 'cancelled', 'expired', 'payment_submitted') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookings_reference (booking_reference),
    UNIQUE KEY uq_bookings_submission (submission_key),
    UNIQUE KEY uq_bookings_id_flight (id, flight_id),
    KEY idx_bookings_user_created (user_id, created_at),
    KEY idx_bookings_flight_status (flight_id, status),
    KEY idx_bookings_status_expiry (status, expires_at),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_bookings_flight FOREIGN KEY (flight_id) REFERENCES flights (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_bookings_amount CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE passengers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    full_name VARCHAR(160) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    phone VARCHAR(30) NULL,
    date_of_birth DATE NULL,
    document_number VARCHAR(50) NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_passengers_id_booking (id, booking_id),
    KEY idx_passengers_booking (booking_id),
    CONSTRAINT fk_passengers_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_seats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    passenger_id BIGINT UNSIGNED NOT NULL,
    flight_id BIGINT UNSIGNED NOT NULL,
    aircraft_id BIGINT UNSIGNED NOT NULL,
    seat_id BIGINT UNSIGNED NOT NULL,
    status ENUM('reserved', 'confirmed', 'released') NOT NULL DEFAULT 'reserved',
    -- NULL for released rows allows reuse while retaining allocation history.
    occupied_seat_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN status IN ('reserved', 'confirmed') THEN seat_id ELSE NULL END
    ) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_booking_seats_passenger (booking_id, passenger_id),
    UNIQUE KEY uq_booking_seats_active_flight_seat (flight_id, occupied_seat_id),
    KEY idx_booking_seats_booking_flight (booking_id, flight_id),
    KEY idx_booking_seats_passenger_booking (passenger_id, booking_id),
    KEY idx_booking_seats_flight_aircraft (flight_id, aircraft_id),
    KEY idx_booking_seats_seat_aircraft (seat_id, aircraft_id),
    CONSTRAINT fk_booking_seats_booking FOREIGN KEY (booking_id, flight_id) REFERENCES bookings (id, flight_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_booking_seats_passenger FOREIGN KEY (passenger_id, booking_id) REFERENCES passengers (id, booking_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_booking_seats_flight FOREIGN KEY (flight_id, aircraft_id) REFERENCES flights (id, aircraft_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_booking_seats_seat FOREIGN KEY (seat_id, aircraft_id) REFERENCES seats (id, aircraft_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PKR',
    method ENUM('bank_transfer', 'cash', 'other') NOT NULL DEFAULT 'bank_transfer',
    transaction_reference VARCHAR(120) NULL,
    proof_path VARCHAR(255) NULL,
    payment_date DATE NULL,
    status ENUM('pending', 'verified', 'rejected', 'refunded') NOT NULL DEFAULT 'pending',
    active_booking_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending', 'verified') THEN booking_id ELSE NULL END
    ) STORED,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_payments_booking (booking_id),
    KEY idx_payments_status_created (status, created_at),
    KEY idx_payments_reviewer (reviewed_by),
    KEY idx_payments_reference (transaction_reference),
    UNIQUE KEY uq_payments_active_booking (active_booking_id),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_payments_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_seat_id BIGINT UNSIGNED NOT NULL,
    ticket_number VARCHAR(40) NOT NULL,
    status ENUM('valid', 'used', 'void') NOT NULL DEFAULT 'valid',
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tickets_number (ticket_number),
    UNIQUE KEY uq_tickets_booking_seat (booking_seat_id),
    KEY idx_tickets_status (status),
    CONSTRAINT fk_tickets_booking_seat FOREIGN KEY (booking_seat_id) REFERENCES booking_seats (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cancellations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cancellations_booking (booking_id),
    KEY idx_cancellations_requester (requested_by),
    KEY idx_cancellations_reviewer (reviewed_by),
    KEY idx_cancellations_status_created (status, created_at),
    CONSTRAINT fk_cancellations_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_cancellations_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_cancellations_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_cancellations_refund CHECK (refund_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
