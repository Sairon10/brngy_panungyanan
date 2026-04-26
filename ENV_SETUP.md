# Environment Setup for Email Service

This document explains how to configure the Resend email service for the Barangay System.

## Prerequisites

1. A Resend account (sign up at https://resend.com)
2. A Resend API key
3. A verified domain in Resend (for production) or use Resend's test domain

## Setup Instructions

### 1. Create .env File

Create a `.env` file in the root directory of the project (`/home/rlc1990/Desktop/barangay_system/.env`) with the following content:

```env
# Resend Email Configuration
RESEND_API_KEY=re_your_api_key_here
RESEND_FROM_EMAIL=noreply@yourdomain.com
RESEND_FROM_NAME=Barangay Panungyanan

# SMS Configuration (Local Device API)
SMS_USERNAME=your_sms_username
SMS_PASSWORD=your_sms_password
SMS_DEVICE_IP=192.168.1.100

# Optional: Barangay Information
BARANGAY_NAME=Barangay Panungyanan
BARANGAY_ADDRESS=Your Barangay Address
BARANGAY_PHONE=Your Contact Number
```

### 2. Get Your Resend API Key

1. Log in to your Resend account at https://resend.com
2. Go to API Keys section
3. Create a new API key
4. Copy the API key (starts with `re_`)
5. Paste it in your `.env` file as `RESEND_API_KEY`

### 3. Configure Sender Email

**For Development/Testing:**

- You can use Resend's test domain: `onboarding@resend.dev`
- Set `RESEND_FROM_EMAIL=onboarding@resend.dev`

**For Production:**

- You need to verify your domain in Resend
- Once verified, use your domain email (e.g., `noreply@yourdomain.com`)
- Set `RESEND_FROM_EMAIL` to your verified email address

### 4. Optional: Install Composer Dependencies

If you want to use the Resend PHP SDK instead of the direct API calls:

```bash
composer install
```

However, the current implementation uses direct API calls via cURL, so Composer is optional.

## Email and SMS Functionality

The system will automatically send emails and SMS messages when:

1. **Document Request Status Updates** (when admin updates status):
   - **Pending**: Notification that request was received
   - **Approved**: Notification that request was approved
   - **Rejected**: Notification that request was rejected (with notes)
   - **Released**: Notification that document is ready for pickup

2. **ID Verification Status Updates**:
   - **Verified**: Notification that ID verification was approved
   - **Rejected**: Notification that ID verification was rejected (with reason)

3. **Password Reset Requests**:
   - Password reset link sent via email and SMS (if phone number is available)

### SMS Configuration

The SMS service uses a local device API endpoint. Configure the following in your `.env` file:

- `SMS_USERNAME`: Username for basic authentication
- `SMS_PASSWORD`: Password for basic authentication
- `SMS_DEVICE_IP`: Local IP address of the SMS device (e.g., `192.168.1.100`)

The SMS service will send messages to phone numbers stored in the `residents` table. Phone numbers should be in international format (e.g., `+19162255887`).

**Note**: SMS will only be sent if the resident has a phone number in their profile. Email will still be sent if the user has an email address.

## Troubleshooting

### Email Not Sending

1. Check that your `.env` file exists and contains the correct `RESEND_API_KEY`
2. Verify the API key is valid in your Resend dashboard
3. Check that the sender email is verified in Resend
4. Check PHP error logs for any cURL errors
5. Ensure the recipient email addresses are valid

### Testing

You can test the email functionality by:

1. Creating a document request as a resident
2. Logging in as admin
3. Updating the request status
4. Checking the resident's email inbox

## Security Notes

- **Never commit your `.env` file to version control**
- Add `.env` to your `.gitignore` file
- Keep your API keys secure and rotate them periodically
