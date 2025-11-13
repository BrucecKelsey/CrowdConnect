-- Create PayoutRequests table to track DJ payout requests
-- This table stores payout requests with payment method details and status tracking

CREATE TABLE PayoutRequests (
    RequestId INT AUTO_INCREMENT PRIMARY KEY,
    UserId INT NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    PaymentMethod ENUM('paypal', 'bank_transfer', 'cashapp', 'venmo', 'zelle', 'apple_pay', 'google_pay', 'crypto') NOT NULL,
    PaymentDetails JSON NOT NULL, -- Stores method-specific details (email, account info, etc.)
    Status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    RequestedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    ProcessedAt DATETIME NULL,
    CompletedAt DATETIME NULL,
    ProcessingFee DECIMAL(10,2) DEFAULT 0.00, -- Fee charged for payout processing
    NetAmount DECIMAL(10,2) NOT NULL, -- Amount after processing fees
    AdminNotes TEXT NULL,
    FailureReason TEXT NULL,
    
    -- Foreign key constraints
    FOREIGN KEY (UserId) REFERENCES Users(ID) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_user_status (UserId, Status),
    INDEX idx_requested_at (RequestedAt),
    INDEX idx_status (Status)
);