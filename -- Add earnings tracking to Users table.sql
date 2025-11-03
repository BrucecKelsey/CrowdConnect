-- Add earnings tracking to Users table
ALTER TABLE Users ADD COLUMN TotalEarnings DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE Users ADD COLUMN LastPayoutAmount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE Users ADD COLUMN LastPayoutDate TIMESTAMP NULL;
ALTER TABLE Users ADD COLUMN StripeAccountId VARCHAR(100);

-- Add tip column to Requests table (if not exists)
ALTER TABLE Requests ADD COLUMN TipAmount DECIMAL(10,2) DEFAULT 0.00;

-- Add tip settings to Parties table (if not exists)  
ALTER TABLE Parties ADD COLUMN AllowTips TINYINT(1) DEFAULT 1;

-- Create Tips table for payment tracking
CREATE TABLE Tips (
    TipId INT AUTO_INCREMENT PRIMARY KEY,
    RequestId INT,
    DJUserID INT,
    CustomerUserID INT,
    TipAmount DECIMAL(10,2),
    StripePaymentIntentId VARCHAR(100),
    StripeChargeId VARCHAR(100),
    StripeFeeAmount DECIMAL(10,2) DEFAULT 0.00,
    NetAmount DECIMAL(10,2) DEFAULT 0.00,
    Status VARCHAR(20) DEFAULT 'pending',
    Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ProcessedAt TIMESTAMP NULL,
    FOREIGN KEY (RequestId) REFERENCES Requests(RequestId),
    FOREIGN KEY (DJUserID) REFERENCES Users(ID),
    FOREIGN KEY (CustomerUserID) REFERENCES Users(ID)
);

-- Create earnings history table for detailed tracking
CREATE TABLE EarningsHistory (
    EarningId INT AUTO_INCREMENT PRIMARY KEY,
    UserId INT,
    TipId INT,
    GrossAmount DECIMAL(10,2),
    StripeFeeAmount DECIMAL(10,2),
    NetAmount DECIMAL(10,2),
    TransactionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    StripeChargeId VARCHAR(100),
    Status VARCHAR(20) DEFAULT 'completed',
    FOREIGN KEY (UserId) REFERENCES Users(ID),
    FOREIGN KEY (TipId) REFERENCES Tips(TipId)
);
