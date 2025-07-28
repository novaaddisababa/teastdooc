# 🧪 Chapa Payment System Testing Guide

## 📋 Pre-Testing Setup

### 1. Database Setup (Run in Order)
```bash
# 1. Create main tables
mysql -u your_username -p your_database < chapa_database.sql

# 2. Create manual withdrawal tables  
mysql -u your_username -p your_database < manual_withdrawals_schema.sql

# 3. Insert test data
mysql -u your_username -p your_database < test_data_setup.sql
```

### 2. Environment Configuration
```bash
# Ensure .env file has test keys
CHAPA_SECRET_KEY=CHASECK_TEST-your-test-key-here
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-your-public-key-here
CHAPA_ENCRYPTION_KEY=your-encryption-key-here
```

### 3. Test User Accounts Created
| Username | Email | Balance | Purpose |
|----------|-------|---------|---------|
| test_user_1 | test1@example.com | ETB 5,000 | Small deposits/withdrawals |
| test_user_2 | test2@example.com | ETB 15,000 | Medium transactions |
| test_user_3 | test3@example.com | ETB 500 | Low balance testing |
| test_user_4 | test4@example.com | ETB 25,000 | Large withdrawals |

### 4. Admin Account
- **Username:** `admin`
- **Password:** `password` (change in production!)
- **Access:** `admin_withdrawals.php`

---

## 🔄 Testing Scenarios

### Phase 1: Deposit Testing

#### Test 1.1: Small Deposit (Success Path)
```
User: test_user_1
Amount: ETB 100
Expected: Redirect to Chapa, successful payment
Verify: Balance increases, transaction recorded
```

#### Test 1.2: Large Deposit
```
User: test_user_4  
Amount: ETB 5,000
Expected: Redirect to Chapa, successful payment
Verify: Balance increases, transaction recorded
```

#### Test 1.3: Invalid Amount
```
User: test_user_1
Amount: ETB 0 or negative
Expected: Error message, no transaction created
```

#### Test 1.4: Insufficient Session
```
User: Not logged in
Expected: Redirect to login or error
```

### Phase 2: Withdrawal Testing

#### Test 2.1: Small Bank Transfer (Automated)
```
User: test_user_1
Amount: ETB 1,000
Method: Bank Transfer (CBE)
Account: 1234567890
Expected: Automatic processing, immediate completion
```

#### Test 2.2: Large Bank Transfer (Manual Queue)
```
User: test_user_4
Amount: ETB 15,000
Method: Bank Transfer (AIB)
Account: 9876543210
Expected: Queued for manual processing
```

#### Test 2.3: Mobile Money (Automated)
```
User: test_user_2
Amount: ETB 2,500
Method: M-Birr
Phone: 251911234567
Expected: Automatic processing attempt
```

#### Test 2.4: Insufficient Balance
```
User: test_user_3 (ETB 500 balance)
Amount: ETB 1,000
Expected: Error message, no transaction
```

### Phase 3: Admin Dashboard Testing

#### Test 3.1: View Pending Withdrawals
```
1. Login to admin_withdrawals.php
2. Verify pending withdrawals display
3. Check statistics cards
4. Verify user details shown correctly
```

#### Test 3.2: Approve Withdrawal
```
1. Select a pending withdrawal
2. Click "Approve" 
3. Add admin notes
4. Confirm approval
5. Verify user balance updated
6. Check user notification sent
```

#### Test 3.3: Reject Withdrawal
```
1. Select a pending withdrawal
2. Click "Reject"
3. Add rejection reason
4. Confirm rejection  
5. Verify user balance restored
6. Check user notification sent
```

### Phase 4: Webhook Testing

#### Test 4.1: Successful Payment Webhook
```bash
# Simulate Chapa webhook
curl -X POST http://localhost/your-app/chapa_webhook.php \
  -H "Content-Type: application/json" \
  -H "Chapa-Signature: test-signature" \
  -d '{
    "event": "charge.success",
    "data": {
      "reference": "TEST_DEP_001",
      "status": "success",
      "amount": 1000,
      "currency": "ETB"
    }
  }'
```

#### Test 4.2: Failed Payment Webhook
```bash
curl -X POST http://localhost/your-app/chapa_webhook.php \
  -H "Content-Type: application/json" \
  -H "Chapa-Signature: test-signature" \
  -d '{
    "event": "charge.failed",
    "data": {
      "reference": "TEST_DEP_002", 
      "status": "failed",
      "amount": 500,
      "currency": "ETB"
    }
  }'
```

---

## 🔍 Verification Checklist

### Database Verification
```sql
-- Check transaction records
SELECT * FROM chapa_transactions WHERE transaction_ref LIKE 'TEST_%';

-- Check manual withdrawal queue
SELECT * FROM manual_withdrawals;

-- Check processing logs
SELECT * FROM withdrawal_processing_logs;

-- Verify user balances
SELECT username, balance FROM users WHERE email LIKE '%example.com';
```

### Log File Verification
```bash
# Check error logs
tail -f /var/log/apache2/error.log

# Check application logs (if implemented)
tail -f logs/chapa_transactions.log
```

---

## 🚨 Error Testing

### Test Invalid Scenarios

#### Invalid Chapa Response
```
Scenario: Chapa API returns error
Expected: Graceful error handling, user notification
Verify: No balance deduction, transaction marked failed
```

#### Network Timeout
```
Scenario: Chapa API timeout
Expected: Transaction marked pending, retry mechanism
Verify: User can retry, no duplicate charges
```

#### Database Connection Loss
```
Scenario: Database unavailable during transaction
Expected: Error message, no partial transactions
Verify: Data consistency maintained
```

#### Concurrent Withdrawals
```
Scenario: User submits multiple withdrawals simultaneously
Expected: Only one processed, others rejected
Verify: Balance protection works
```

---

## 📊 Performance Testing

### Load Testing Scenarios
```bash
# Test concurrent deposits (use Apache Bench)
ab -n 100 -c 10 http://localhost/your-app/index.php

# Test admin dashboard under load
ab -n 50 -c 5 http://localhost/your-app/admin_withdrawals.php
```

### Database Performance
```sql
-- Check query performance
EXPLAIN SELECT * FROM chapa_transactions WHERE user_id = 1 AND status = 'pending';

-- Verify indexes are used
SHOW INDEX FROM chapa_transactions;
```

---

## 🔒 Security Testing

### Authentication Testing
```
1. Access admin panel without login → Should redirect
2. Access with invalid credentials → Should fail
3. Session timeout testing → Should require re-login
4. CSRF token validation → Should prevent attacks
```

### Input Validation Testing
```
1. SQL injection attempts in forms
2. XSS attempts in user inputs  
3. Invalid amount formats
4. Malformed JSON in API calls
```

### API Security Testing
```bash
# Test webhook without proper signature
curl -X POST http://localhost/your-app/chapa_webhook.php \
  -H "Content-Type: application/json" \
  -d '{"malicious": "data"}'

# Should return 401 Unauthorized
```

---

## 🎯 Go-Live Checklist

### Before Production Deployment

#### ✅ Configuration
- [ ] Replace test API keys with production keys
- [ ] Update webhook URLs to production domain
- [ ] Set proper error reporting levels
- [ ] Configure SSL certificates
- [ ] Set up backup procedures

#### ✅ Security
- [ ] Change default admin password
- [ ] Remove test user accounts
- [ ] Enable HTTPS only
- [ ] Configure firewall rules
- [ ] Set up monitoring alerts

#### ✅ Database
- [ ] Run production database migration
- [ ] Set up automated backups
- [ ] Configure replication if needed
- [ ] Optimize database settings
- [ ] Clear test data

#### ✅ Monitoring
- [ ] Set up error logging
- [ ] Configure transaction monitoring
- [ ] Set up uptime monitoring
- [ ] Create admin notification system
- [ ] Test disaster recovery

#### ✅ Documentation
- [ ] Update API documentation
- [ ] Create admin user manual
- [ ] Document troubleshooting procedures
- [ ] Create backup/restore procedures

---

## 🆘 Troubleshooting Common Issues

### Issue: "Duplicate key name 'transaction_ref'"
**Solution:** Remove redundant index from chapa_database.sql

### Issue: "Class 'Chapa' not found"
**Solution:** Ensure Chapa SDK is properly included and autoloaded

### Issue: "Webhook signature verification failed"
**Solution:** Check webhook secret key configuration

### Issue: "Admin dashboard shows no withdrawals"
**Solution:** Verify manual_withdrawals table exists and has data

### Issue: "Balance not updating after deposit"
**Solution:** Check callback URL configuration and webhook processing

---

## 📞 Support Information

For issues during testing:
1. Check error logs first
2. Verify database connections
3. Confirm API key configuration  
4. Test with minimal data first
5. Use browser developer tools for debugging

**Remember:** Always test in a safe environment before going live!