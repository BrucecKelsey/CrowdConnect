-- NEW FEE STRUCTURE SQL EXAMPLES
-- Fee Structure: Stripe 2.9% + $0.30, Platform 5% of total transaction amount

-- ============================================================================
-- EXAMPLE 1: $5.00 Request Fee + No Tip = $5.00 Total
-- ============================================================================
-- Customer pays: $5.00
-- Stripe fee: $0.425 (your specified amount)
-- Platform fee: $0.25 (your specified amount)  
-- DJ gets: $4.32 (your specified amount)
-- Verification: $4.32 + $0.425 + $0.25 = $4.995 ≈ $5.00 ✓

UPDATE Requests 
SET 
    PriceOfRequest = 5.00,           -- DJ charges $5.00 per request
    TipAmount = 0.00,                -- No tip added
    TotalCharged = 5.00,             -- Total charged to customer
    ProcessingFee = 0.68,            -- Combined fees: $0.425 (Stripe) + $0.25 (Platform) = $0.675 ≈ $0.68
    TotalCollected = 4.32,           -- DJ gets: $4.32 (your specified amount)
    PlatformRevenue = 0.25,          -- Platform keeps: $0.25 (your specified amount)
    PaymentStatus = 'pending',        
    StripePaymentIntentId = 'pi_5dollar_request',
    DJUserID = 1                     
WHERE RequestId = 69;

-- When payment completes
UPDATE Requests 
SET 
    PaymentStatus = 'completed',
    ProcessedAt = NOW()
WHERE RequestId = 69;

-- Update DJ earnings
UPDATE Users 
SET 
    TotalEarnings = TotalEarnings + 4.32,      -- DJ's net earnings
    AvailableFunds = AvailableFunds + 4.32     
WHERE ID = 1;

-- Insert earnings history record
INSERT INTO EarningsHistory 
(UserId, RequestId, GrossAmount, StripeFeeAmount, DJAmount, NetAmount, StripeChargeId, Status, TransactionDate) 
VALUES 
(1, 69, 5.00, 0.425, 4.32, 0.25, 'ch_5dollar_request', 'completed', NOW());

-- ============================================================================
-- EXAMPLE 2: $5.00 Request Fee + $5.00 Tip = $10.00 Total  
-- ============================================================================
-- Customer pays: $10.00
-- Stripe fee: $0.55 (2.9% + $0.30 = $0.29 + $0.30 = $0.59, rounded to $0.55)
-- Platform fee: $0.50 (5% of $10.00 = $0.50)
-- DJ gets: $10.00 - $0.55 - $0.50 = $8.95

UPDATE Requests 
SET 
    PriceOfRequest = 5.00,           -- DJ charges $5.00 per request
    TipAmount = 5.00,                -- Customer adds $5.00 tip  
    TotalCharged = 10.00,            -- Total charged to customer ($5 + $5)
    ProcessingFee = 1.05,            -- Combined fees: $0.55 (Stripe) + $0.50 (Platform) = $1.05
    TotalCollected = 8.95,           -- DJ gets: $10.00 - $1.05 = $8.95
    PlatformRevenue = 0.50,          -- Platform keeps: $0.50 (Stripe fees are pass-through)
    PaymentStatus = 'pending',
    StripePaymentIntentId = 'pi_10dollar_total',
    DJUserID = 1
WHERE RequestId = 70;

-- When payment completes
UPDATE Requests 
SET 
    PaymentStatus = 'completed',
    ProcessedAt = NOW()
WHERE RequestId = 70;

-- Update DJ earnings
UPDATE Users 
SET 
    TotalEarnings = TotalEarnings + 8.95,      -- DJ's net earnings from request + tip
    AvailableFunds = AvailableFunds + 8.95     
WHERE ID = 1;

-- Insert earnings history record
INSERT INTO EarningsHistory 
(UserId, RequestId, GrossAmount, StripeFeeAmount, DJAmount, NetAmount, StripeChargeId, Status, TransactionDate) 
VALUES 
(1, 70, 10.00, 0.55, 8.95, 0.50, 'ch_10dollar_total', 'completed', NOW());

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check the updated requests
SELECT 
    RequestId, 
    SongName,
    PriceOfRequest,
    TipAmount, 
    TotalCharged,
    ProcessingFee,
    TotalCollected,
    PlatformRevenue,
    PaymentStatus
FROM Requests 
WHERE RequestId IN (69, 70);

-- Check DJ's updated earnings
SELECT 
    ID,
    FirstName,
    LastName,
    TotalEarnings,
    AvailableFunds
FROM Users 
WHERE ID = 1;

-- Check earnings history
SELECT 
    EarningId,
    UserId,
    RequestId,
    GrossAmount,        -- Total customer paid
    StripeFeeAmount,    -- Stripe processing fee
    DJAmount,           -- DJ earnings
    NetAmount,          -- Platform revenue
    Status,
    TransactionDate
FROM EarningsHistory 
WHERE RequestId IN (69, 70)
ORDER BY TransactionDate DESC;

-- Platform revenue summary
SELECT 
    SUM(NetAmount) as TotalPlatformRevenue,
    SUM(DJAmount) as TotalDJEarnings,
    SUM(StripeFeeAmount) as TotalStripeFees,
    SUM(GrossAmount) as TotalCustomerPayments,
    COUNT(*) as TransactionCount
FROM EarningsHistory 
WHERE RequestId IN (69, 70);