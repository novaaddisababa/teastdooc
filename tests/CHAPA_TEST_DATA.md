# 🏦 Chapa Payment System - Complete Test Data Reference

## 📋 Overview
This document contains all test data for the Chapa payment system including bank codes, account numbers, mobile money providers, and phone numbers. **Use only this test data for testing - never use real account information.**

---

## 🏛️ Ethiopian Banks Test Data

### Commercial Bank of Ethiopia (CBE)
- **Bank Code:** `CBE`
- **API Support:** ✅ Yes (Automated processing)
- **Test Account Numbers:**
  - `1000123456789` (Primary test account)
  - `1000987654321` (Secondary test account)
  - `1000555666777` (Tertiary test account)
- **Account Format:** 13 digits starting with `1000`
- **Processing:** Automated for amounts under ETB 10,000

### Awash International Bank (AIB)
- **Bank Code:** `AIB`
- **API Support:** ✅ Yes (Automated processing)
- **Test Account Numbers:**
  - `0123456789012` (Primary test account)
  - `0987654321098` (Secondary test account)
  - `0555666777888` (Tertiary test account)
- **Account Format:** 13 digits starting with `0`
- **Processing:** Automated for amounts under ETB 10,000

### Bank of Abyssinia (BOA)
- **Bank Code:** `BOA`
- **API Support:** ❌ No (Manual processing only)
- **Test Account Numbers:**
  - `2000123456789` (Primary test account)
  - `2000987654321` (Secondary test account)
  - `2000555666777` (Tertiary test account)
- **Account Format:** 13 digits starting with `2000`
- **Processing:** Manual processing required

### United Bank (UB)
- **Bank Code:** `UB`
- **API Support:** ✅ Yes (Automated processing)
- **Test Account Numbers:**
  - `3000123456789` (Primary test account)
  - `3000987654321` (Secondary test account)
  - `3000555666777` (Tertiary test account)
- **Account Format:** 13 digits starting with `3000`
- **Processing:** Automated for amounts under ETB 10,000

### Dashen Bank (DB)
- **Bank Code:** `DB`
- **API Support:** ❌ No (Manual processing only)
- **Test Account Numbers:**
  - `4000123456789` (Primary test account)
  - `4000987654321` (Secondary test account)
  - `4000555666777` (Tertiary test account)
- **Account Format:** 13 digits starting with `4000`
- **Processing:** Manual processing required

---

## 📱 Mobile Money Providers Test Data

### M-Birr
- **Provider Code:** `MBIRR`
- **API Support:** ✅ Yes (Automated processing)
- **Test Phone Numbers:**
  - `251911234567` (Primary test number)
  - `251922345678` (Secondary test number)
  - `251933456789` (Tertiary test number)
  - `251944567890` (Quaternary test number)
- **Format:** `251` + 9 digits (Ethiopian mobile format)
- **Limits:** Min: ETB 10, Max: ETB 50,000
- **Processing:** Automated for supported amounts

### HelloCash
- **Provider Code:** `HELLO`
- **API Support:** ✅ Yes (Automated processing)
- **Test Phone Numbers:**
  - `251912345678` (Primary test number)
  - `251923456789` (Secondary test number)
  - `251934567890` (Tertiary test number)
  - `251945678901` (Quaternary test number)
- **Format:** `251` + 9 digits (Ethiopian mobile format)
- **Limits:** Min: ETB 5, Max: ETB 30,000
- **Processing:** Automated for supported amounts

### TeleBirr
- **Provider Code:** `TELEBIRR`
- **API Support:** ❌ No (Manual processing only)
- **Test Phone Numbers:**
  - `251913456789` (Primary test number)
  - `251924567890` (Secondary test number)
  - `251935678901` (Tertiary test number)
  - `251946789012` (Quaternary test number)
- **Format:** `251` + 9 digits (Ethiopian mobile format)
- **Limits:** Min: ETB 1, Max: ETB 100,000
- **Processing:** Manual processing required

---

## 👥 Test User Accounts

### Test User 1 (Small Balance)
- **Username:** `test_user_1`
- **Email:** `test1@example.com`
- **Password:** `test123`
- **Balance:** ETB 5,000
- **Use Case:** Small deposits and withdrawals testing

### Test User 2 (Medium Balance)
- **Username:** `test_user_2`
- **Email:** `test2@example.com`
- **Password:** `test123`
- **Balance:** ETB 15,000
- **Use Case:** Medium transactions and mobile money testing

### Test User 3 (Low Balance)
- **Username:** `test_user_3`
- **Email:** `test3@example.com`
- **Password:** `test123`
- **Balance:** ETB 500
- **Use Case:** Insufficient balance testing

### Test User 4 (High Balance)
- **Username:** `test_user_4`
- **Email:** `test4@example.com`
- **Password:** `test123`
- **Balance:** ETB 25,000
- **Use Case:** Large withdrawals and VIP testing

---

## 🔑 Admin Test Accounts

### Super Admin
- **Username:** `admin`
- **Password:** `password` (⚠️ Change in production!)
- **Role:** `super_admin`
- **Permissions:** Full access to all withdrawal operations

### Test Admin
- **Username:** `test_admin`
- **Password:** `test123`
- **Role:** `admin`
- **Permissions:** View and approve withdrawals only

---

## 💰 Test Transaction Scenarios

### Deposit Scenarios

#### Small Deposit (Success)
```json
{
  "amount": 100.00,
  "user": "test_user_1",
  "expected": "success",
  "description": "Small deposit should process immediately"
}
```

#### Medium Deposit (Success)
```json
{
  "amount": 2500.00,
  "user": "test_user_2",
  "expected": "success",
  "description": "Medium deposit with standard processing"
}
```

#### Large Deposit (Success)
```json
{
  "amount": 10000.00,
  "user": "test_user_4",
  "expected": "success",
  "description": "Large deposit may require verification"
}
```

#### Invalid Amount (Error)
```json
{
  "amount": -100.00,
  "user": "test_user_1",
  "expected": "error",
  "description": "Negative amount should be rejected"
}
```

### Withdrawal Scenarios

#### Small Bank Transfer (Automated)
```json
{
  "amount": 1000.00,
  "method": "bank_transfer",
  "bank_code": "CBE",
  "account": "1000123456789",
  "user": "test_user_1",
  "expected_processing": "automated"
}
```

#### Large Bank Transfer (Manual)
```json
{
  "amount": 15000.00,
  "method": "bank_transfer",
  "bank_code": "AIB",
  "account": "0123456789012",
  "user": "test_user_4",
  "expected_processing": "manual"
}
```

#### Mobile Money Automated
```json
{
  "amount": 2500.00,
  "method": "mobile_money",
  "provider": "M-BIRR",
  "phone": "251911234567",
  "user": "test_user_2",
  "expected_processing": "automated"
}
```

#### Mobile Money Manual (TeleBirr)
```json
{
  "amount": 8500.00,
  "method": "mobile_money",
  "provider": "TeleBirr",
  "phone": "251913456789",
  "user": "test_user_2",
  "expected_processing": "manual"
}
```

---

## 🔐 Chapa API Test Configuration

### Test Environment URLs
- **API Base URL:** `https://api.chapa.co/v1`
- **Checkout URL:** `https://checkout.chapa.co/checkout/payment/`
- **Webhook URL:** `http://localhost/your-app/chapa_webhook.php`
- **Callback URL:** `http://localhost/your-app/chapa_callback.php`

### Test API Keys
```bash
# Add these to your .env file for testing
CHAPA_SECRET_KEY=CHASECK_TEST-your-test-secret-key-here
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-your-test-public-key-here
CHAPA_ENCRYPTION_KEY=your-test-encryption-key-here
```

### Sample API Responses

#### Successful Payment Initialization
```json
{
  "status": "success",
  "message": "Payment initialized successfully",
  "data": {
    "checkout_url": "https://checkout.chapa.co/checkout/payment/test-checkout-url",
    "reference": "TEST_REF_123456",
    "status": "pending"
  }
}
```

#### Successful Payment Verification
```json
{
  "status": "success",
  "message": "Payment verified successfully",
  "data": {
    "reference": "TEST_REF_123456",
    "status": "success",
    "amount": 1000.00,
    "currency": "ETB",
    "charge": {
      "id": "ch_test_123456",
      "status": "success",
      "reference": "TEST_REF_123456"
    }
  }
}
```

#### Webhook Payload (Success)
```json
{
  "event": "charge.success",
  "data": {
    "id": "ch_test_123456",
    "status": "success",
    "reference": "TEST_REF_123456",
    "amount": 1000.00,
    "currency": "ETB",
    "email": "test@example.com",
    "first_name": "Test",
    "last_name": "User"
  }
}
```

---

## 🧪 Running Tests

### Setup Test Environment
```bash
# 1. Install PHPUnit (if not already installed)
composer require --dev phpunit/phpunit

# 2. Set up test database
mysql -u root -p -e "CREATE DATABASE toady_game_test;"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON toady_game_test.* TO 'test_user'@'localhost' IDENTIFIED BY 'test_password';"

# 3. Run all tests
cd tests
../vendor/bin/phpunit

# 4. Run specific test suites
../vendor/bin/phpunit --testsuite="Unit Tests"
../vendor/bin/phpunit --testsuite="Integration Tests"

# 5. Generate coverage report
../vendor/bin/phpunit --coverage-html coverage-html
```

### Test Commands
```bash
# Run unit tests only
./vendor/bin/phpunit tests/Unit/

# Run integration tests only
./vendor/bin/phpunit tests/Integration/

# Run with verbose output
./vendor/bin/phpunit --verbose

# Run specific test class
./vendor/bin/phpunit tests/Unit/DepositTest.php

# Run specific test method
./vendor/bin/phpunit --filter testSuccessfulDepositInitialization
```

---

## ⚠️ Important Testing Notes

### Security Reminders
- **Never use real bank account numbers or phone numbers in tests**
- **Always use the provided test data**
- **Test API keys should never be used in production**
- **Change default admin passwords before going live**

### Test Data Validation
- All bank account numbers follow Ethiopian banking formats
- Phone numbers use Ethiopian mobile number format (+251)
- Test amounts are realistic for Ethiopian context
- All test emails use `@example.com` domain

### Processing Rules
- **Automated Processing:** Amounts under ETB 10,000 with API-supported banks/providers
- **Manual Processing:** Large amounts, unsupported banks/providers, or failed automated attempts
- **Priority Levels:** normal (< ETB 15,000), high (ETB 15,000-50,000), urgent (> ETB 50,000)

---

## 📞 Support & Troubleshooting

### Common Test Issues
1. **Database connection errors:** Check test database credentials
2. **API key errors:** Verify test keys are properly set
3. **Table not found:** Run bootstrap.php to create test tables
4. **Permission denied:** Check database user permissions

### Test Data Reset
```sql
-- Clean all test data
DELETE FROM withdrawal_processing_logs WHERE transaction_ref LIKE '%TEST%';
DELETE FROM manual_withdrawals WHERE transaction_ref LIKE '%TEST%';
DELETE FROM chapa_transactions WHERE transaction_ref LIKE '%TEST%';
DELETE FROM users WHERE email LIKE '%example.com';
```

**Remember: This is test data only - never use in production!** 🚨