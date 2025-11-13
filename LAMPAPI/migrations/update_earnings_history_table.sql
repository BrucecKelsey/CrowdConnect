-- Update EarningsHistory table to support both Tips and Requests
-- Add RequestId column to track earnings from song requests

ALTER TABLE EarningsHistory ADD COLUMN RequestId INT NULL AFTER TipId;
ALTER TABLE EarningsHistory ADD FOREIGN KEY (RequestId) REFERENCES Requests(RequestId);

-- Make TipId nullable since we now have RequestId as well
ALTER TABLE EarningsHistory MODIFY COLUMN TipId INT NULL;

-- Add index for performance
ALTER TABLE EarningsHistory ADD INDEX idx_request_earnings (RequestId);
ALTER TABLE EarningsHistory ADD INDEX idx_user_earnings_date (UserId, TransactionDate);