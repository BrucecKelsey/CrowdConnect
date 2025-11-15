-- Fix existing EarningsHistory records to reflect correct fee structure
-- Recalculate DJAmount and NetAmount based on new 5% platform fee structure

-- Backup the current data first
CREATE TABLE EarningsHistory_Backup AS SELECT * FROM EarningsHistory;

-- Update existing records with correct calculations
UPDATE EarningsHistory 
SET 
    NetAmount = ROUND(GrossAmount * 0.05, 2),  -- Platform gets 5% of gross
    DJAmount = ROUND(GrossAmount - StripeFeeAmount - (GrossAmount * 0.05), 2)  -- DJ gets remainder
WHERE DJAmount IS NOT NULL OR NetAmount IS NOT NULL;

-- Verify the fix
SELECT 
    EarningId,
    GrossAmount,
    StripeFeeAmount, 
    DJAmount,
    NetAmount,
    (StripeFeeAmount + DJAmount + NetAmount) as Total_Check,
    ROUND(GrossAmount * 0.05, 2) as Expected_Platform_Fee
FROM EarningsHistory;