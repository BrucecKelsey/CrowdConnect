-- COMPLETE SQL COMMANDS FOR SONG REQUEST PAYMENT PROCESSING
-- RequestID: 70, PartyID: 1, DJUserID: 1
-- Example: $5.00 song request + $2.00 tip = $7.00 total transaction

-- ASSUMPTIONS FOR THIS EXAMPLE:
-- - Song request fee: $5.00
-- - Tip amount: $2.00  
-- - Total charged to customer: $7.00
-- - Stripe Payment Intent ID: 'pi_example123' (would be real Stripe ID)
-- - Stripe Charge ID: 'ch_example123' (would be real Stripe charge ID)

USE COP4331;

-- =====================================================================
-- STEP 1: PAYMENT CREATION (CreateConsolidatedPayment.php)
-- =====================================================================

-- Fee Structure Calculations for $7.00 transaction:
-- Platform Fee (5%): $7.00 × 0.05 = $0.35
-- Stripe Fee (2.9% + $0.30): ($7.00 × 0.029) + $0.30 = $0.20 + $0.30 = $0.50
-- Total Processing Fee: $0.35 + $0.50 = $0.85
-- DJ Net Earnings: $7.00 - $0.85 = $6.15

-- Update Requests table with payment information
UPDATE Requests 
SET 
    PriceOfRequest = 5.00,           -- Song request fee
    TipAmount = 2.00,                -- Additional tip
    TotalCharged = 7.00,             -- Total customer payment
    ProcessingFee = 0.85,            -- Total fees (Platform + Stripe)
    TotalCollected = 6.15,           -- DJ's net earnings
    PlatformRevenue = 0.35,          -- Platform's 5% revenue
    PaymentStatus = 'pending',       -- Initially pending
    StripePaymentIntentId = 'pi_example123'  -- Stripe Payment Intent ID
WHERE RequestId = 70;

-- =====================================================================
-- STEP 2: PAYMENT CONFIRMATION (ConfirmPayment.php - when payment succeeds)
-- =====================================================================

-- Update Requests table payment status to completed
UPDATE Requests 
SET 
    PaymentStatus = 'completed',
    ProcessedAt = NOW()
WHERE StripePaymentIntentId = 'pi_example123';

-- Insert record into EarningsHistory table
INSERT INTO EarningsHistory (
    UserId, 
    RequestId, 
    GrossAmount, 
    StripeFeeAmount, 
    DJAmount, 
    NetAmount, 
    StripeChargeId, 
    Status,
    TransactionDate
) VALUES (
    1,              -- DJUserID
    70,             -- RequestId
    7.00,           -- Total customer payment
    0.50,           -- Stripe processing fee (2.9% + $0.30)
    6.15,           -- DJ's net earnings
    0.35,           -- Platform revenue (5%)
    'ch_example123', -- Stripe Charge ID
    'completed',    -- Status
    NOW()           -- Transaction timestamp
);

-- Update DJ's total earnings and available funds
UPDATE Users 
SET 
    TotalEarnings = TotalEarnings + 6.15,      -- Add DJ's net earnings to total
    AvailableFunds = AvailableFunds + 6.15     -- Add to available payout balance
WHERE ID = 1;

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================

-- Check the updated Requests record
SELECT 
    RequestId,
    SongName,
    RequestedBy,
    PriceOfRequest,
    TipAmount,
    TotalCharged,
    ProcessingFee,
    TotalCollected,
    PlatformRevenue,
    PaymentStatus,
    StripePaymentIntentId,
    ProcessedAt
FROM Requests 
WHERE RequestId = 70;

-- Check the EarningsHistory record
SELECT 
    EarningId,
    UserId,
    RequestId,
    GrossAmount,
    StripeFeeAmount,
    DJAmount,
    NetAmount,
    StripeChargeId,
    Status,
    TransactionDate,
    -- Verify calculations
    ROUND((NetAmount / GrossAmount) * 100, 2) as platform_percent,
    ROUND((DJAmount / GrossAmount) * 100, 2) as dj_percent
FROM EarningsHistory 
WHERE RequestId = 70;

-- Check updated DJ earnings
SELECT 
    ID,
    FirstName,
    LastName,
    TotalEarnings,
    AvailableFunds
FROM Users 
WHERE ID = 1;

-- =====================================================================
-- SUMMARY OF DATABASE CHANGES
-- =====================================================================

/*
TABLES AFFECTED:
1. Requests - Updated with payment details and status
2. EarningsHistory - New record tracking the transaction breakdown
3. Users - Updated TotalEarnings and AvailableFunds for the DJ

FEE BREAKDOWN FOR $7.00 TRANSACTION:
- Customer Pays: $7.00
- Platform Revenue (5%): $0.35 
- Stripe Processing (2.9% + $0.30): $0.50
- DJ Receives: $6.15 (87.9%)

VERIFICATION:
- Platform: $0.35 / $7.00 = 5.0% ✓
- Stripe: $0.50 (includes 2.9% + $0.30) ✓  
- DJ: $6.15 / $7.00 = 87.9% ✓
- Total: $0.35 + $0.50 + $6.15 = $7.00 ✓
*/