-- Add AvailableFunds column to Users table
-- This tracks the DJ's actual available funds (net earnings after fees)
ALTER TABLE Users ADD COLUMN AvailableFunds DECIMAL(10,2) DEFAULT 0.00;