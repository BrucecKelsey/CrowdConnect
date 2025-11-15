-- OFFICIAL FEE STRUCTURE MIGRATION
-- Establishes CrowdConnect's official fee structure:
-- 1. Platform Revenue: 5% of total transaction amount
-- 2. Stripe Processing: 2.9% + $0.30 per transaction
-- 3. DJ Earnings: Remainder after platform and Stripe fees

-- This migration ensures all existing records comply with the official fee structure

USE COP4331;

-- First, let's see what we're working with
SELECT 'Current EarningsHistory Records:' as info;
SELECT COUNT(*) as total_records, 
       MIN(TransactionDate) as earliest_date, 
       MAX(TransactionDate) as latest_date
FROM EarningsHistory;

-- Show records that might have incorrect calculations
SELECT 'Records with potential incorrect fees:' as info;
SELECT COUNT(*) as suspect_records
FROM EarningsHistory 
WHERE ABS(StripeFeeAmount - ((GrossAmount * 0.029) + 0.30)) > 0.01;

-- BACKUP existing data before corrections
CREATE TABLE IF NOT EXISTS EarningsHistory_Backup_20241114 AS 
SELECT * FROM EarningsHistory;

-- UPDATE EXISTING RECORDS TO OFFICIAL FEE STRUCTURE
UPDATE EarningsHistory 
SET 
    -- Recalculate Stripe fees: 2.9% + $0.30
    StripeFeeAmount = ROUND((GrossAmount * 0.029) + 0.30, 2),
    -- Platform gets 5% of total transaction
    NetAmount = ROUND(GrossAmount * 0.05, 2),
    -- DJ gets the remainder: Total - Platform(5%) - Stripe(2.9% + $0.30)
    DJAmount = ROUND(GrossAmount - (GrossAmount * 0.05) - ((GrossAmount * 0.029) + 0.30), 2)
WHERE GrossAmount > 0;

-- Verify the calculations are correct
SELECT 'Verification of updated records:' as info;
SELECT 
    COUNT(*) as total_updated,
    AVG(GrossAmount) as avg_transaction,
    AVG(NetAmount) as avg_platform_revenue,
    AVG(StripeFeeAmount) as avg_stripe_fees,
    AVG(DJAmount) as avg_dj_earnings,
    -- Verify percentages
    AVG((NetAmount / GrossAmount) * 100) as avg_platform_percent,
    AVG((StripeFeeAmount / GrossAmount) * 100) as avg_stripe_percent,
    AVG((DJAmount / GrossAmount) * 100) as avg_dj_percent
FROM EarningsHistory 
WHERE GrossAmount > 0;

-- Check for any calculation errors (totals should equal GrossAmount)
SELECT 'Records with calculation mismatches:' as info;
SELECT COUNT(*) as mismatch_count
FROM EarningsHistory 
WHERE ABS((NetAmount + StripeFeeAmount + DJAmount) - GrossAmount) > 0.01;

-- Show sample of corrected records
SELECT 'Sample of corrected records:' as info;
SELECT 
    UserId,
    GrossAmount,
    NetAmount as platform_revenue,
    StripeFeeAmount as stripe_fees,
    DJAmount as dj_earnings,
    ROUND((NetAmount / GrossAmount) * 100, 2) as platform_percent,
    ROUND((StripeFeeAmount / GrossAmount) * 100, 2) as stripe_percent,
    ROUND((DJAmount / GrossAmount) * 100, 2) as dj_percent
FROM EarningsHistory 
WHERE GrossAmount > 0
ORDER BY TransactionDate DESC 
LIMIT 10;

-- Add documentation to the table for future reference
ALTER TABLE EarningsHistory 
MODIFY COLUMN GrossAmount DECIMAL(10,2) COMMENT 'Total customer payment',
MODIFY COLUMN StripeFeeAmount DECIMAL(10,2) COMMENT 'Stripe processing fee: 2.9% + $0.30',
MODIFY COLUMN DJAmount DECIMAL(10,2) COMMENT 'DJ earnings: Total - Platform(5%) - Stripe fees',
MODIFY COLUMN NetAmount DECIMAL(10,2) COMMENT 'Platform revenue: 5% of total transaction';

SELECT 'OFFICIAL FEE STRUCTURE MIGRATION COMPLETED' as status;
SELECT 'Platform: 5% | Stripe: 2.9% + $0.30 | DJ: Remainder' as fee_breakdown;