# Chapa Webhook Setup Guide

## 1. Chapa Dashboard Configuration

### Access Dashboard
1. Go to https://dashboard.chapa.co/
2. Login with your credentials
3. Navigate to **Settings** → **Webhooks** or **API Settings**

### Configure Webhook
1. **Webhook URL**: `https://yourdomain.com/chapa_webhook.php`
2. **Events to Subscribe**:
   - `payment.success`
   - `payment.failed` 
   - `payment.cancelled`
3. **HTTP Method**: POST
4. **Content Type**: application/json

### Get Webhook Secret
1. Copy the **Webhook Secret** from dashboard
2. This is used to verify webhook authenticity

## 2. Server Configuration

### Update .env File
```bash
# Your actual Chapa secret key
CHAPA_SECRET_KEY=CHASECK_TEST-your_actual_test_key_here

# Webhook secret from Chapa dashboard
CHAPA_WEBHOOK_SECRET=your_webhook_secret_from_dashboard

# API Base URL
CHAPA_BASE_URL=https://api.chapa.co/v1
```

### Webhook URL Requirements
- ✅ Must be publicly accessible (not localhost)
- ✅ Must use HTTPS (SSL certificate required)
- ✅ Must respond with HTTP 200 for successful processing
- ✅ Must process webhooks quickly (< 10 seconds)

## 3. Testing Webhooks

### For Local Development
Use tunneling tools to expose localhost:

#### Option 1: ngrok
```bash
# Install ngrok
npm install -g ngrok

# Expose local server
ngrok http 80

# Use the HTTPS URL provided by ngrok
# Example: https://abc123.ngrok.io/chapa_webhook.php
```

#### Option 2: Serveo
```bash
# Create tunnel to localhost:8080
ssh -R 80:localhost:8080 serveo.net

# Use the provided URL
# Example: https://subdomain.serveo.net/chapa_webhook.php
```

#### Option 3: Cloudflare Tunnel
```bash
# Install cloudflared
# Create tunnel
cloudflared tunnel --url http://localhost:8080
```

### Test Webhook Endpoint
```bash
# Test if your webhook endpoint is accessible
curl -X POST https://yourdomain.com/chapa_webhook.php \
  -H "Content-Type: application/json" \
  -d '{"tx_ref":"TEST_123","status":"success","amount":"100"}'
```

## 4. Webhook Payload Example

Chapa sends webhooks with this structure:
```json
{
  "tx_ref": "DEP_1234567890_abcdef12",
  "status": "success",
  "amount": "100.00",
  "currency": "ETB",
  "email": "customer@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "phone_number": "0911234567",
  "created_at": "2025-01-26T12:00:00Z",
  "updated_at": "2025-01-26T12:05:00Z"
}
```

## 5. Webhook Security

### Signature Verification
Your webhook handler automatically verifies signatures:
```php
// In chapa_webhook.php
$signature = $_SERVER['HTTP_X_CHAPA_SIGNATURE'] ?? '';
if ($signature && !ChapaConfig::verifyWebhookSignature($input, $signature)) {
    throw new Exception("Invalid webhook signature");
}
```

### IP Whitelisting (Optional)
You can restrict webhook access to Chapa's IP addresses:
```php
// Add to chapa_webhook.php
$allowedIPs = ['52.31.174.107', '52.49.173.169']; // Chapa IPs
$clientIP = $_SERVER['REMOTE_ADDR'];
if (!in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    exit('Forbidden');
}
```

## 6. Monitoring and Debugging

### Check Webhook Logs
```bash
# View webhook processing logs
tail -f /var/log/apache2/error.log | grep "Chapa Transaction"

# Or check your application logs
tail -f /path/to/your/app/logs/error.log
```

### Webhook Status in Dashboard
- Check webhook delivery status in Chapa dashboard
- View failed webhook attempts
- Retry failed webhooks manually

## 7. Production Checklist

- [ ] HTTPS certificate installed and valid
- [ ] Webhook URL publicly accessible
- [ ] Webhook secret configured in .env
- [ ] Database connection working
- [ ] Error logging enabled
- [ ] Webhook endpoint responds quickly (< 10 seconds)
- [ ] Signature verification enabled
- [ ] Test payments processed successfully

## 8. Common Issues

### Webhook Not Received
- Check if URL is publicly accessible
- Verify HTTPS certificate is valid
- Check server logs for errors
- Ensure webhook URL in dashboard is correct

### Signature Verification Failed
- Verify webhook secret in .env matches dashboard
- Check if signature header is being sent
- Ensure raw POST body is used for verification

### Database Errors
- Check database connection
- Verify chapa_transactions table exists
- Check database user permissions

## 9. Support

If you encounter issues:
1. Check Chapa documentation: https://developer.chapa.co/
2. Contact Chapa support through dashboard
3. Review webhook delivery logs in dashboard
