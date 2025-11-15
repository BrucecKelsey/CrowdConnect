-- Add DJAmount column to EarningsHistory table
-- This will track how much the DJ earned from each transaction
-- NetAmount will now represent platform earnings instead of DJ earnings

ALTER TABLE EarningsHistory ADD COLUMN DJAmount DECIMAL(10,2) AFTER StripeFeeAmount;

-- Update the column comments for clarity
ALTER TABLE EarningsHistory 
MODIFY COLUMN GrossAmount DECIMAL(10,2) COMMENT 'Total amount customer paid',
MODIFY COLUMN StripeFeeAmount DECIMAL(10,2) COMMENT 'Amount paid to Stripe for processing',  
MODIFY COLUMN DJAmount DECIMAL(10,2) COMMENT 'Amount DJ receives after all fees',
MODIFY COLUMN NetAmount DECIMAL(10,2) COMMENT 'Platform revenue (5% of total transaction)';

-- Create index for performance on DJ earnings queries
ALTER TABLE EarningsHistory ADD INDEX idx_dj_earnings (UserId, DJAmount);
ALTER TABLE EarningsHistory ADD INDEX idx_platform_earnings (NetAmount, TransactionDate);