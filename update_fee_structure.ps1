# CROWDCONNECT DATABASE FEE STRUCTURE CORRECTION SCRIPT (PowerShell)
# This script applies the official fee structure to all existing records
# Platform: 5% | Stripe: 2.9% + $0.30 | DJ: Remainder

Write-Host "=========================================" -ForegroundColor Green
Write-Host "CrowdConnect Official Fee Structure Update" -ForegroundColor Green
Write-Host "Platform: 5% | Stripe: 2.9% + `$0.30 | DJ: Remainder" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green

# Database connection details
$dbUser = "TheBeast"
$dbName = "COP4331"

Write-Host ""
Write-Host "Step 1: Applying official fee structure migration..." -ForegroundColor Yellow

# Step 1: Run the official fee structure migration
$migrationPath = "LAMPAPI/migrations/official_fee_structure_migration.sql"
$command = "mysql -u $dbUser -p $dbName"

try {
    $result = & cmd /c "mysql -u $dbUser -p $dbName < $migrationPath" 2>&1
    Write-Host "✓ Official fee structure migration completed successfully" -ForegroundColor Green
} catch {
    Write-Host "✗ Migration failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Step 2: Verifying fee structure corrections..." -ForegroundColor Yellow

# Step 2: Verify the corrections
$verificationQuery = @"
USE $dbName;
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
"@

try {
    & mysql -u $dbUser -p -e $verificationQuery
} catch {
    Write-Host "Verification query failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Step 3: Checking for calculation errors..." -ForegroundColor Yellow

# Step 3: Check for calculation errors
$errorCheckQuery = @"
USE $dbName;
SELECT 'Calculation Error Check' as status;
SELECT COUNT(*) as records_with_errors
FROM EarningsHistory 
WHERE ABS((NetAmount + StripeFeeAmount + DJAmount) - GrossAmount) > 0.01;
"@

try {
    & mysql -u $dbUser -p -e $errorCheckQuery
} catch {
    Write-Host "Error check query failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=========================================" -ForegroundColor Green
Write-Host "Fee Structure Update Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Summary of Official CrowdConnect Fee Structure:" -ForegroundColor Cyan
Write-Host "• Platform Revenue: 5% of total transaction" -ForegroundColor White
Write-Host "• Stripe Processing: 2.9% + `$0.30 per transaction" -ForegroundColor White
Write-Host "• DJ Earnings: Remainder after platform and Stripe fees" -ForegroundColor White
Write-Host ""
Write-Host "All existing records have been updated to reflect this structure." -ForegroundColor Yellow
Write-Host "Use GetEarningsV2.php API for accurate earnings calculations." -ForegroundColor Yellow
Write-Host "=========================================" -ForegroundColor Green