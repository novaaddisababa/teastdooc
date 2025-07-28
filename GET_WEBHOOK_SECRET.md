# 🔐 How to Get Chapa Webhook Secret Hash

## Step-by-Step Guide

### 1. Access Chapa Dashboard
- Go to: https://dashboard.chapa.co/
- Sign in with your credentials

### 2. Navigate to Webhooks Section
- Click on **Settings** in the left sidebar
- Click on **Webhooks** from the settings menu

### 3. Add New Webhook
- Click **"Add Webhook"** or **"Create Webhook"** button
- You'll see a form to configure your webhook

### 4. Configure Webhook Settings
Fill in the webhook form:
```
Webhook URL: https://041589671ab5.ngrok-free.app/chapa_webhook.php
Description: Toady Game Payment Webhook (optional)
```

### 5. Select Events to Subscribe
Check these events:
- ✅ `payment.success`
- ✅ `payment.failed` 
- ✅ `payment.cancelled`

### 6. Save and Get Secret
- Click **"Save"** or **"Create Webhook"**
- After saving, Chapa will display your **Webhook Secret**
- This secret will look something like: `wh_sec_xxxxxxxxxxxxxxxxxxxxxxxx`

### 7. Copy the Secret
- Copy the entire webhook secret hash
- It's usually displayed in a box or highlighted area
- Keep this secret safe - you'll only see it once!

## 🔧 Update Your .env File

Once you have the webhook secret, update your `.env` file:

```bash
# Replace this line:
CHAPA_WEBHOOK_SECRET=your_webhook_secret_here

# With your actual secret:
CHAPA_WEBHOOK_SECRET=wh_sec_your_actual_secret_here
```

## 📝 Alternative: If You Already Have a Webhook

If you already created a webhook:
1. Go to Settings → Webhooks
2. Find your existing webhook in the list
3. Click **"View"** or **"Edit"** 
4. The secret should be displayed there
5. If not visible, you may need to regenerate it

## ⚠️ Important Notes

- **Security**: Never share your webhook secret publicly
- **One-time Display**: Chapa usually shows the secret only once after creation
- **Regeneration**: If you lose it, you can regenerate a new secret
- **Testing**: You can test webhooks in the Chapa dashboard

## 🧪 Test Your Webhook

After adding the secret to `.env`, test it:
```bash
cd /home/kali/Desktop/final/final-toady-game
php configure_webhooks.php
```

## 🆘 Troubleshooting

**If you can't find the webhook secret:**
1. Try creating a new webhook
2. Check if there's a "Show Secret" button
3. Look for a "Regenerate Secret" option
4. Contact Chapa support if needed

**Common locations for the secret:**
- Immediately after webhook creation
- In webhook details/edit page
- Under "Webhook Configuration" section
- In a collapsible "Advanced Settings" area
