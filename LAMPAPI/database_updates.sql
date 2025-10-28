-- Add tipping columns to Requests table (if not already added)
ALTER TABLE Requests ADD COLUMN IF NOT EXISTS TipAmount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE Requests ADD COLUMN IF NOT EXISTS PaymentStatus VARCHAR(20) DEFAULT 'none';
ALTER TABLE Requests ADD COLUMN IF NOT EXISTS PaymentId VARCHAR(100);
ALTER TABLE Requests ADD COLUMN IF NOT EXISTS ProcessingFee DECIMAL(10,2) DEFAULT 0.00;

-- Add DJ payment info to Users table (if not already added)
ALTER TABLE Users ADD COLUMN IF NOT EXISTS StripeAccountId VARCHAR(100);
ALTER TABLE Users ADD COLUMN IF NOT EXISTS PayPalEmail VARCHAR(100);
ALTER TABLE Users ADD COLUMN IF NOT EXISTS VenmoUsername VARCHAR(50);
ALTER TABLE Users ADD COLUMN IF NOT EXISTS StripeAccountStatus VARCHAR(20) DEFAULT 'not_setup';
ALTER TABLE Users ADD COLUMN IF NOT EXISTS CanReceiveTips BOOLEAN DEFAULT FALSE;
ALTER TABLE Users ADD COLUMN IF NOT EXISTS LastStripeSync TIMESTAMP NULL;

-- Create tips tracking table for analytics
CREATE TABLE Tips (
    TipId INT AUTO_INCREMENT PRIMARY KEY,
    RequestId INT,
    PartyId INT,
    DjUserId INT,
    TipAmount DECIMAL(10,2),
    ProcessingFee DECIMAL(10,2),
    NetAmount DECIMAL(10,2),
    PaymentMethod VARCHAR(20),
    PaymentId VARCHAR(100),
    Status VARCHAR(20),
    Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (RequestId) REFERENCES Requests(RequestId),
    FOREIGN KEY (PartyId) REFERENCES Parties(PartyId),
    FOREIGN KEY (DjUserId) REFERENCES Users(UserId)
);