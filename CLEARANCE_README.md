# Barangay Clearance System

This system allows residents to request barangay clearances and administrators to manage and approve them.

## Features

- **Resident Features:**

  - Request barangay clearance with purpose and validity period
  - View clearance request status
  - Download approved clearances as PDF

- **Admin Features:**
  - Review and approve/reject clearance requests
  - Generate PDF clearances
  - Manage clearance status and notes

## Setup Instructions

1. **Database Setup:**
   Run the setup script to create the required table:

   ```bash
   php scripts/setup_clearances.php
   ```

2. **Access the System:**
   - Residents: Navigate to "Clearance" in the main menu
   - Admins: Access "Barangay Clearances" in the admin panel

## How to Use

### For Residents:

1. **Request a Clearance:**

   - Go to the "Clearance" page
   - Fill in the purpose of the clearance
   - Select validity period (30, 60, 90, or 180 days)
   - Submit the request

2. **Check Status:**

   - View your clearance requests in the same page
   - Status will show: pending, approved, rejected, or released

3. **Download PDF:**
   - Once approved, click "Download PDF" to get your clearance
   - The PDF can be printed or saved

### For Administrators:

1. **Review Requests:**

   - Go to Admin Panel > Barangay Clearances
   - View all clearance requests with resident details

2. **Approve/Reject:**

   - Change status from pending to approved/rejected
   - Add notes if needed
   - Save changes

3. **Generate PDF:**
   - Click "PDF" button for approved clearances
   - PDF will be generated with official format

## PDF Features

The generated PDF includes:

- Official barangay letterhead
- Clearance number and date
- Resident information
- Purpose of clearance
- Validity period
- Signature line for barangay captain
- Professional formatting

## File Structure

- `barangay_clearance.php` - Main clearance request page for residents
- `generate_clearance_pdf.php` - PDF generation script
- `admin/barangay_clearances.php` - Admin management page
- `scripts/setup_clearances.php` - Database setup script

## Database Schema

The `barangay_clearances` table stores:

- User ID and clearance number
- Purpose and validity period
- Status and approval information
- Timestamps for tracking

## Notes

- Clearances are automatically numbered with format: BC-YYYY-XXXXXX
- Only approved clearances can be downloaded as PDF
- The system tracks who approved each clearance
- PDF generation uses browser print functionality (can be enhanced with libraries like TCPDF)
