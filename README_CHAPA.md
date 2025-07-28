# Chapa Payment Gateway Integration

A complete PHP implementation for Chapa's payment system supporting deposits, withdrawals, and webhook handling.

## 🚀 Features

- **Deposit Processing**: Initialize payments with Chapa's API
- **Withdrawal Processing**: Handle bank/mobile money payouts
- **Webhook Handling**: Process payment confirmations securely
- **Input Validation**: Comprehensive sanitization and validation
- **Error Handling**: Robust error management and logging
- **Security**: Webhook signature verification and secure API key storage
- **Documentation**: Built-in API documentation endpoint

## 📋 Requirements

### PHP Extensions
- `curl` - For HTTP requests to Chapa API
- `json` - For JSON data processing
- `openssl` - For secure operations

### Environment
- PHP 7.4 or higher
- Web server (Apache/Nginx)
- SSL certificate (recommended for production)

## 🛠️ Installation

### 1. Clone/Download Files
```bash
# Ensure you have these files in your project directory:
# - chapa_index.php (main router)
# - deposit.php (deposit endpoint)
# - withdraw.php (withdrawal endpoint)
# - webhook.php (webhook handler)
# - .env.example (environment template)
```

### 2. Environment Setup
```bash
# Copy environment template
cp .env.example .env

# Edit .env with your actual Chapa credentials
nano .env
```

### 3. Configure Environment Variables
Edit your `.env` file:
```bash
# Chapa API Configuration
CHAPA_API_KEY=CHASECK_TEST-your-actual-sandbox-key-here
CHAPA_WEBHOOK_SECRET=your-webhook-secret-here

# Environment (sandbox or live)
CHAPA_ENVIRONMENT=sandbox

# Application URLs
APP_URL=http://localhost
CALLBACK_URL=http://localhost/webhook.php
RETURN_URL=http://localhost/success.php
```

### 4. Load Environment Variables
Add this to your PHP configuration or use a library like `vlucas/phpdotenv`:
```php
// Simple environment loader (add to your bootstrap)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            putenv($line);
        }
    }
}
```

## 🔧 Configuration

### API Keys
1. **Sandbox**: Get your test keys from [Chapa Dashboard](https://dashboard.chapa.co)
2. **Production**: Switch to live keys when ready for production

### Webhook Setup
1. In your Chapa dashboard, set webhook URL to: `https://yourdomain.com/webhook.php`
2. Configure webhook secret in your environment variables
3. Ensure your webhook endpoint is publicly accessible

## 📚 API Documentation

### Base URL
- **Development**: `http://localhost/chapa_index.php`
- **Production**: `https://yourdomain.com/chapa_index.php`

### Endpoints

#### 1. Initialize Deposit
**POST** `/deposit` or `/deposit.php`

**Required Fields:**
- `amount` (number): Payment amount
- `email` (string): Customer email

**Optional Fields:**
- `currency` (string): Currency code (default: ETB)
- `tx_ref` (string): Unique transaction reference
- `first_name` (string): Customer first name
- `last_name` (string): Customer last name
- `phone` (string): Customer phone number
- `return_url` (string): Success redirect URL
- `callback_url` (string): Webhook callback URL

**Example Request:**
```bash
curl -X POST http://localhost/deposit.php \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100,
    "email": "customer@example.com",
    "currency": "ETB",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+251911234567"
  }'
```

**Example Response:**
```json
{
  "status": "success",
  "message": "Payment initialized successfully",
  "data": {
    "checkout_url": "https://checkout.chapa.co/checkout/payment/...",
    "tx_ref": "deposit_1642857600_a1b2c3d4"
  }
}
```

#### 2. Process Withdrawal
**POST** `/withdraw` or `/withdraw.php`

**Required Fields:**
- `amount` (number): Withdrawal amount
- `account_number` (string): Beneficiary account number
- `bank_code` (string): Bank code (e.g., "CBE", "BOA")

**Optional Fields:**
- `currency` (string): Currency code (default: ETB)
- `reference` (string): Unique withdrawal reference
- `account_name` (string): Account holder name
- `beneficiary_name` (string): Beneficiary name

**Example Request:**
```bash
curl -X POST http://localhost/withdraw.php \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50,
    "account_number": "1234567890",
    "bank_code": "CBE",
    "currency": "ETB",
    "account_name": "John Doe"
  }'
```

#### 3. Verify Payment
**GET** `/verify?tx_ref=transaction_reference`

**Example Request:**
```bash
curl -X GET "http://localhost/chapa_index.php/verify?tx_ref=deposit_1642857600_a1b2c3d4"
```

#### 4. Get Supported Banks
**GET** `/banks`

**Example Request:**
```bash
curl -X GET http://localhost/chapa_index.php/banks
```

#### 5. Webhook Handler
**POST** `/webhook` or `/webhook.php`

This endpoint is called automatically by Chapa. Ensure it's publicly accessible.

## 🧪 Testing

### 1. Test Deposit
```bash
# Test payment initialization
curl -X POST http://localhost/deposit.php \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 10,
    "email": "test@example.com",
    "currency": "ETB"
  }'
```

### 2. Test Withdrawal
```bash
# Test withdrawal processing
curl -X POST http://localhost/withdraw.php \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 5,
    "account_number": "1000123456789",
    "bank_code": "CBE"
  }'
```

### 3. Test Webhook (Simulate)
```bash
# Simulate webhook call
curl -X POST http://localhost/webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Chapa-Signature: test-signature" \
  -d '{
    "event": "charge.success",
    "data": {
      "tx_ref": "test_tx_ref",
      "amount": 100,
      "email": "test@example.com",
      "status": "success"
    }
  }'
```

### 4. View API Documentation
```bash
# Access built-in documentation
curl http://localhost/chapa_index.php
```

## 🔒 Security Best Practices

### 1. Environment Variables
- Never commit `.env` files to version control
- Use strong, unique webhook secrets
- Rotate API keys regularly

### 2. Webhook Security
- Always verify webhook signatures
- Use HTTPS in production
- Implement rate limiting

### 3. Input Validation
- All inputs are sanitized and validated
- SQL injection protection (if using database)
- XSS prevention

### 4. Error Handling
- Sensitive information is not exposed in error messages
- All errors are logged for debugging
- Graceful error responses

## 📊 Database Integration

### Example Database Schema
```sql
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tx_ref VARCHAR(255) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ETB',
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    failure_reason TEXT NULL
);

CREATE TABLE withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(255) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ETB',
    account_number VARCHAR(50) NOT NULL,
    bank_code VARCHAR(10) NOT NULL,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    failure_reason TEXT NULL
);
```

### Database Integration Example
```php
// Add this to your webhook.php functions
function updatePaymentStatus($txRef, $status, $amount = 0, $email = '', $reason = '') {
    try {
        $pdo = new PDO(
            "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = ?, amount = ?, updated_at = NOW(), failure_reason = ?
            WHERE tx_ref = ?
        ");
        $stmt->execute([$status, $amount, $reason, $txRef]);
        
        if ($status === 'success') {
            // Credit user account
            $stmt = $pdo->prepare("
                UPDATE users 
                SET balance = balance + ? 
                WHERE email = ?
            ");
            $stmt->execute([$amount, $email]);
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}
```

## 🚀 Deployment

### Production Checklist
- [ ] Set `CHAPA_ENVIRONMENT=live` in `.env`
- [ ] Use production API keys
- [ ] Enable HTTPS
- [ ] Set up proper logging
- [ ] Configure webhook URL in Chapa dashboard
- [ ] Test all endpoints thoroughly
- [ ] Set up monitoring and alerts
- [ ] Disable error display: `ini_set('display_errors', 0)`

### Server Configuration
```apache
# Apache .htaccess example
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ chapa_index.php [QSA,L]
```

```nginx
# Nginx configuration example
location / {
    try_files $uri $uri/ /chapa_index.php?$query_string;
}
```

## 🐛 Troubleshooting

### Common Issues

1. **cURL Error**: Ensure cURL extension is installed
2. **Invalid API Key**: Check environment variables and API key format
3. **Webhook Signature Verification Failed**: Verify webhook secret matches Chapa dashboard
4. **CORS Issues**: Adjust CORS headers in the code if needed

### Debug Mode
Enable detailed logging by adding to your code:
```php
// Add to chapa_index.php for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');
```

## 📞 Support

- **Chapa Documentation**: [https://developer.chapa.co](https://developer.chapa.co)
- **Chapa Support**: [support@chapa.co](mailto:support@chapa.co)
- **API Status**: Check Chapa's status page for service updates

## 📄 License

This implementation is provided as-is for educational and commercial use. Please ensure compliance with Chapa's terms of service.

---

**Created by**: Cascade AI Assistant  
**Date**: 2025-07-23  
**Version**: 1.0.0
