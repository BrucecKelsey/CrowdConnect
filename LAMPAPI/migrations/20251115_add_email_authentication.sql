-- Email Authentication System Migration
-- Date: November 15, 2025
-- Purpose: Add email column and authentication fields to Users table

-- Add email and related authentication columns
ALTER TABLE Users 
ADD COLUMN Email VARCHAR(255) NOT NULL AFTER LastName,
ADD COLUMN IsEmailVerified BOOLEAN DEFAULT FALSE AFTER Email,
ADD COLUMN EmailVerificationToken VARCHAR(64) NULL AFTER IsEmailVerified,
ADD COLUMN EmailVerificationExpires DATETIME NULL AFTER EmailVerificationToken,
ADD COLUMN PasswordResetToken VARCHAR(64) NULL AFTER EmailVerificationExpires,
ADD COLUMN PasswordResetExpires DATETIME NULL AFTER PasswordResetToken,
ADD COLUMN CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER PasswordResetExpires,
ADD COLUMN UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER CreatedAt;

-- Add unique index on email
ALTER TABLE Users ADD UNIQUE INDEX idx_users_email (Email);

-- Add indexes for performance
ALTER TABLE Users ADD INDEX idx_users_email_verification (EmailVerificationToken);
ALTER TABLE Users ADD INDEX idx_users_password_reset (PasswordResetToken);

-- Update existing users with placeholder emails (you'll need to update these manually)
-- UPDATE Users SET Email = CONCAT(Login, '@placeholder.com') WHERE Email IS NULL OR Email = '';