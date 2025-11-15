#!/bin/bash

# CROWDCONNECT DATABASE FEE STRUCTURE CORRECTION SCRIPT
# This script applies the official fee structure to all existing records
# Platform: 5% | Stripe: 2.9% + $0.30 | DJ: Remainder

echo "========================================="
echo "CrowdConnect Official Fee Structure Update"
echo "Platform: 5% | Stripe: 2.9% + \$0.30 | DJ: Remainder"
echo "========================================="

# Database connection details
DB_USER="TheBeast"
DB_NAME="COP4331"

echo "Please enter your MySQL password when prompted..."

# Step 1: Run the official fee structure migration
echo ""
echo "Step 1: Applying official fee structure migration..."
mysql -u $DB_USER -p $DB_NAME < LAMPAPI/migrations/official_fee_structure_migration.sql

if [ $? -eq 0 ]; then
    echo "✓ Official fee structure migration completed successfully"
else
    echo "✗ Migration failed. Please check the error messages above."
    exit 1
fi

# Step 2: Verify the corrections
echo ""
echo "Step 2: Verifying fee structure corrections..."
mysql -u $DB_USER -p -e "
USE $DB_NAME;
SELECT 'Fee Structure Verification' as status;
SELECT 
    COUNT(*) as total_records,
    ROUND(AVG((NetAmount / GrossAmount) * 100), 2) as avg_platform_percent,
    ROUND(AVG(((StripeFeeAmount - 0.30) / GrossAmount) * 100), 2) as avg_stripe_percent,
    ROUND(AVG((DJAmount / GrossAmount) * 100), 2) as avg_dj_percent
FROM EarningsHistory 
WHERE GrossAmount > 0;

SELECT 'Sample Updated Records' as info;
SELECT 
    GrossAmount as total_paid,
    NetAmount as platform_revenue,
    StripeFeeAmount as stripe_fees,
    DJAmount as dj_earnings,
    ROUND((NetAmount / GrossAmount) * 100, 1) as platform_pct,
    ROUND((DJAmount / GrossAmount) * 100, 1) as dj_pct
FROM EarningsHistory 
WHERE GrossAmount > 0
ORDER BY TransactionDate DESC 
LIMIT 5;
"

# Step 3: Check for any calculation errors
echo ""
echo "Step 3: Checking for calculation errors..."
mysql -u $DB_USER -p -e "
USE $DB_NAME;
SELECT 'Calculation Error Check' as status;
SELECT COUNT(*) as records_with_errors
FROM EarningsHistory 
WHERE ABS((NetAmount + StripeFeeAmount + DJAmount) - GrossAmount) > 0.01;
"

echo ""
echo "========================================="
echo "Fee Structure Update Complete!"
echo ""
echo "Summary of Official CrowdConnect Fee Structure:"
echo "• Platform Revenue: 5% of total transaction"
echo "• Stripe Processing: 2.9% + \$0.30 per transaction"
echo "• DJ Earnings: Remainder after platform and Stripe fees"
echo ""
echo "All existing records have been updated to reflect this structure."
echo "Use GetEarningsV2.php API for accurate earnings calculations."
echo "========================================="