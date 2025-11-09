-- Add request fee columns to Parties table
-- Use these commands one at a time, ignore errors if columns already exist

ALTER TABLE Parties ADD COLUMN AllowRequestFees TINYINT(1) DEFAULT 0;
ALTER TABLE Parties ADD COLUMN RequestFeeAmount DECIMAL(10,2) DEFAULT 0.00;