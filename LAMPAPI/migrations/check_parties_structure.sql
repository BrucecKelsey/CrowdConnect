-- Found: Parties table has DJId column (not UserID)
-- Now populate existing requests with correct DJ user IDs

UPDATE Requests r 
JOIN Parties p ON r.PartyId = p.PartyId 
SET r.DJUserID = p.DJId 
WHERE r.DJUserID IS NULL;