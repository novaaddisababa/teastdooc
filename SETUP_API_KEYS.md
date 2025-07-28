# 🔑 Chapa API Keys Setup Guide

## Step 1: Get Your Keys from Chapa Dashboard

### A. Get Secret Key
1. Go to https://dashboard.chapa.co/
2. Navigate to Settings → API Keys
3. Copy your **Test Secret Key** (starts with `CHASECK_TEST-`)

### B. Setup Webhook
1. Go to Settings → Webhooks
2. Click "Add Webhook"
3. Enter URL: `https://041589671ab5.ngrok-free.app/chapa_webhook.php`
4. Select events: `payment.success`, `payment.failed`, `payment.cancelled`
5. Save and copy the **Webhook Secret**

## Step 2: Update Your .env File

Replace these lines in your `.env` file:

```bash
# Replace this line:
CHAPA_SECRET_KEY=CHASECK_TEST-your_actual_test_key_here
# With your actual key:
CHAPA_SECRET_KEY=CHASECK_TEST-[paste_your_key_here]

# Replace this line:
CHAPA_WEBHOOK_SECRET=your_webhook_secret_here
# With your actual webhook secret:
CHAPA_WEBHOOK_SECRET=[paste_your_webhook_secret_here]
```

## Step 3: Test the Setup

After updating your keys, run:
```bash
cd /home/kali/Desktop/final/final-toady-game
php configure_webhooks.php
```

## Step 4: Test Your Application

1. Open: https://041589671ab5.ngrok-free.app/index.php
2. Try making a test deposit
3. Check if webhooks are working

## ⚠️ Important Notes

- Keep your API keys secure and never share them
- Use test keys for development
- Switch to live keys only for production
- Make sure ngrok is running: `ngrok http 80`
- Ensure Apache/LAMPP is running: `sudo /opt/lampp/lampp status`

## 🆘 If You Need Help

If you encounter any issues:
1. Check that ngrok is still running
2. Verify Apache is serving files
3. Run the configuration test script
4. Check the webhook logs in Chapa dashboard
