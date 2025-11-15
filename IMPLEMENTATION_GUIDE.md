# CrowdConnect Fee Structure Implementation Guide

## Quick Start

### Official Fee Structure
- **Platform**: 5% of total transaction
- **Stripe**: 2.9% + $0.30 per transaction  
- **DJ**: Remainder after fees

### Apply Fee Structure Updates

#### Option 1: PowerShell (Windows)
```powershell
.\update_fee_structure.ps1
```

#### Option 2: MySQL Direct
```bash
mysql -u TheBeast -p COP4331 < LAMPAPI/migrations/official_fee_structure_migration.sql
```

### Key Files Updated

#### Payment Processing
- ✅ `CreateConsolidatedPayment.php` - Official fee calculations
- ✅ `ConfirmPayment.php` - Updated for both Tips and Requests

#### Earnings APIs  
- ✅ `GetEarningsV2.php` - Uses official fee structure
- ⚠️ `GetEarnings.php` - Deprecated (legacy calculations)

#### Database
- ✅ `EarningsHistory` table updated with DJAmount column
- ✅ All existing records recalculated with correct fees

### Verification

Check that fees are calculated correctly:
```sql
SELECT 
    GrossAmount,
    NetAmount as platform_5pct,
    StripeFeeAmount as stripe_fee,
    DJAmount as dj_earnings,
    ROUND((NetAmount/GrossAmount)*100,1) as platform_pct
FROM EarningsHistory 
WHERE GrossAmount > 0 
ORDER BY TransactionDate DESC 
LIMIT 5;
```

Expected: Platform percentage should be 5.0% for all records.

### Integration Notes

#### Frontend Integration
Update earnings displays to use `GetEarningsV2.php`:
- `total_dj_earnings`: Amount DJ can withdraw
- `total_platform_revenue`: CrowdConnect's 5% cut
- `fee_structure`: Human-readable fee breakdown

#### New Payment Flow
`CreateConsolidatedPayment.php` handles both song requests + tips in one transaction with official fee structure applied automatically.

---
**Need Help?** See `FEE_STRUCTURE_DOCUMENTATION.md` for detailed examples and calculations.