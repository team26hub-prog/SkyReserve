-- Passenger details only. Keep document_number for existing ticket/report mapping.
ALTER TABLE passengers ADD COLUMN cnic CHAR(13) NULL,
    ADD COLUMN passport_number VARCHAR(20) NULL;
