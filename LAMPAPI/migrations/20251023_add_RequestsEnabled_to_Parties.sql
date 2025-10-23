-- Add RequestsEnabled column to Parties table
ALTER TABLE Parties ADD COLUMN RequestsEnabled TINYINT(1) NOT NULL DEFAULT 1;