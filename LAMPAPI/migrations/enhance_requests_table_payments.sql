-- Add payment tracking columns to Requests table
-- This will consolidate request fees and tips into one table

ALTER TABLE Requests ADD COLUMN PriceOfRequest DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE Requests ADD COLUMN TipAmount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE Requests ADD COLUMN TotalCharged DECIMAL(10,2) DEFAULT 0.00; -- PriceOfRequest + TipAmount
ALTER TABLE Requests ADD COLUMN ProcessingFee DECIMAL(10,2) DEFAULT 0.00; -- 2.9% + $0.30 Stripe fee on tip only
ALTER TABLE Requests ADD COLUMN TotalCollected DECIMAL(10,2) DEFAULT 0.00; -- Amount DJ receives (tip - processing fee)
ALTER TABLE Requests ADD COLUMN PlatformRevenue DECIMAL(10,2) DEFAULT 0.00; -- Request fee + processing fee profit
ALTER TABLE Requests ADD COLUMN PaymentStatus ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending';
ALTER TABLE Requests ADD COLUMN StripePaymentIntentId VARCHAR(255) NULL;
ALTER TABLE Requests ADD COLUMN ProcessedAt DATETIME NULL;

-- Add indexes for performance
ALTER TABLE Requests ADD INDEX idx_payment_status (PaymentStatus);
ALTER TABLE Requests ADD INDEX idx_dj_payment (DJUserID, PaymentStatus);
ALTER TABLE Requests ADD INDEX idx_processed_at (ProcessedAt);