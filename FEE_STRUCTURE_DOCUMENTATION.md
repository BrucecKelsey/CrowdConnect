# CrowdConnect Official Fee Structure Documentation

## Overview
CrowdConnect operates on a transparent three-tier fee structure that fairly distributes transaction costs between the platform, payment processor, and DJ performers.

## Fee Structure Breakdown

### 1. Platform Revenue: 5%
- **What it is**: CrowdConnect's service fee for providing the platform
- **Applied to**: Total transaction amount (song requests + tips)
- **Purpose**: Platform maintenance, development, and operations

### 2. Stripe Processing Fee: 2.9% + $0.30
- **What it is**: Payment processing fee charged by Stripe
- **Applied to**: Total transaction amount
- **Purpose**: Credit card processing, fraud protection, payment infrastructure

### 3. DJ Earnings: Remainder
- **What it is**: The amount the DJ receives after all fees are deducted
- **Calculation**: Total Transaction - Platform Fee (5%) - Stripe Fee (2.9% + $0.30)

## Fee Calculation Examples

### Example 1: $10.00 Song Request + $5.00 Tip = $15.00 Total
- **Total Transaction**: $15.00
- **Platform Fee (5%)**: $15.00 × 0.05 = $0.75
- **Stripe Fee**: ($15.00 × 0.029) + $0.30 = $0.44 + $0.30 = $0.74
- **DJ Earnings**: $15.00 - $0.75 - $0.74 = $13.51
- **Fee Breakdown**: Platform: $0.75 (5%) | Stripe: $0.74 (4.9%) | DJ: $13.51 (90.1%)

### Example 2: $2.00 Tip Only
- **Total Transaction**: $2.00
- **Platform Fee (5%)**: $2.00 × 0.05 = $0.10
- **Stripe Fee**: ($2.00 × 0.029) + $0.30 = $0.06 + $0.30 = $0.36
- **DJ Earnings**: $2.00 - $0.10 - $0.36 = $1.54
- **Fee Breakdown**: Platform: $0.10 (5%) | Stripe: $0.36 (18%) | DJ: $1.54 (77%)

### Example 3: $50.00 Song Request + $10.00 Tip = $60.00 Total
- **Total Transaction**: $60.00
- **Platform Fee (5%)**: $60.00 × 0.05 = $3.00
- **Stripe Fee**: ($60.00 × 0.029) + $0.30 = $1.74 + $0.30 = $2.04
- **DJ Earnings**: $60.00 - $3.00 - $2.04 = $54.96
- **Fee Breakdown**: Platform: $3.00 (5%) | Stripe: $2.04 (3.4%) | DJ: $54.96 (91.6%)

## Database Implementation

### EarningsHistory Table Structure
```sql
- GrossAmount: Total customer payment
- StripeFeeAmount: Stripe processing fee (2.9% + $0.30)
- DJAmount: DJ earnings after all fees
- NetAmount: Platform revenue (5% of total)
```

### Key APIs Using Official Fee Structure
- `CreateConsolidatedPayment.php`: Creates payments with official fee structure
- `ConfirmPayment.php`: Confirms payments and records earnings
- `GetEarningsV2.php`: Retrieves earnings using official structure

## Migration Information

### Historical Data Correction
All existing records have been updated to reflect the official fee structure through:
- `official_fee_structure_migration.sql`: Database migration script
- `update_fee_structure.ps1`: PowerShell correction script
- `update_fee_structure.sh`: Bash correction script

### Deprecated APIs
- `GetEarnings.php`: Legacy API with old fee calculations (marked deprecated)

## Important Notes

### For Developers
1. **Always use the official fee structure** in new implementations
2. **Use GetEarningsV2.php** for earnings calculations
3. **Test fee calculations** with the provided examples above
4. **Platform fee is always 5%** regardless of transaction size
5. **Stripe fee increases percentage on smaller transactions** due to the $0.30 fixed fee

### For Business Operations
1. **Minimum transaction**: $0.50 (due to Stripe requirements)
2. **Fee transparency**: All fees are clearly documented and consistent
3. **DJ protection**: DJs always receive the majority of each transaction
4. **Platform sustainability**: 5% fee ensures platform can continue operations

## Implementation Checklist

- [x] Update `CreateConsolidatedPayment.php` with official fee structure
- [x] Update `ConfirmPayment.php` for both Tips and Requests
- [x] Update `GetEarningsV2.php` to use correct column interpretations
- [x] Create database migration script for existing records
- [x] Mark legacy APIs as deprecated
- [x] Create comprehensive documentation
- [x] Provide correction scripts for historical data

## Verification Commands

### Check Fee Structure in Database
```sql
USE COP4331;
SELECT 
    COUNT(*) as total_records,
    ROUND(AVG((NetAmount / GrossAmount) * 100), 2) as avg_platform_percent,
    ROUND(AVG((DJAmount / GrossAmount) * 100), 2) as avg_dj_percent
FROM EarningsHistory 
WHERE GrossAmount > 0;
```

### Verify No Calculation Errors
```sql
SELECT COUNT(*) as records_with_errors
FROM EarningsHistory 
WHERE ABS((NetAmount + StripeFeeAmount + DJAmount) - GrossAmount) > 0.01;
```

Expected result: 0 records with errors

---
*Last updated: November 14, 2024*  
*Fee structure version: 1.0 (Official)*