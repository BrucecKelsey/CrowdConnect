-- Database fixes for CrowdConnect email authentication
-- Run these commands to fix the password column size and add email columns

-- First, fix the Password column to support hashed passwords (60+ characters)
ALTER TABLE Users MODIFY COLUMN Password VARCHAR(255);

-- Add email columns if they don't exist (these will fail silently if columns already exist)
-- Note: We'll add them one by one to avoid errors if some exist

-- Add Email column
ALTER TABLE Users ADD COLUMN Email VARCHAR(255) NULL AFTER LastName;

-- Add email verification columns
ALTER TABLE Users ADD COLUMN IsEmailVerified BOOLEAN DEFAULT FALSE AFTER Email;
ALTER TABLE Users ADD COLUMN EmailVerificationToken VARCHAR(64) NULL AFTER IsEmailVerified;
ALTER TABLE Users ADD COLUMN EmailVerificationExpires DATETIME NULL AFTER EmailVerificationToken;

-- Add password reset columns
ALTER TABLE Users ADD COLUMN PasswordResetToken VARCHAR(64) NULL AFTER EmailVerificationExpires;
ALTER TABLE Users ADD COLUMN PasswordResetExpires DATETIME NULL AFTER PasswordResetToken;

-- Add timestamp columns
ALTER TABLE Users ADD COLUMN CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER PasswordResetExpires;
ALTER TABLE Users ADD COLUMN UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER CreatedAt;

-- Add indexes (these will fail if they already exist, which is fine)
ALTER TABLE Users ADD UNIQUE INDEX idx_users_email (Email);
ALTER TABLE Users ADD INDEX idx_users_email_verification (EmailVerificationToken);
ALTER TABLE Users ADD INDEX idx_users_password_reset (PasswordResetToken);

-- Show final structure
DESCRIBE Users;