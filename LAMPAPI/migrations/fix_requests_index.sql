-- Fix the missing DJ user reference in Requests table
-- The Requests table is missing a direct reference to the DJ user
-- We need to add this column and then create the index

-- Add DJ user reference column
ALTER TABLE Requests ADD COLUMN DJUserID INT NULL AFTER PartyId;

-- Add foreign key constraint to link to Users table
ALTER TABLE Requests ADD FOREIGN KEY (DJUserID) REFERENCES Users(ID);

-- Now create the index we originally wanted
ALTER TABLE Requests ADD INDEX idx_dj_payment (DJUserID, PaymentStatus);

-- We should also populate this column for existing requests by joining with Parties table
-- UPDATE Requests r 
-- JOIN Parties p ON r.PartyId = p.PartyId 
-- SET r.DJUserID = p.UserID 
-- WHERE r.DJUserID IS NULL;