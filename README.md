# Document & Bill Verification Portal

A secure web portal for office use that enables customers to verify the authenticity of documents and bills issued by the company. Built with PHP, MySQL, and Tailwind CSS.

## Features

### Public Portal
- **Document Verification** - Verify company documents using document number and date
- **Bill Verification** - Verify bills with OTP-based email authentication
- **Secure PDF Viewer** - View documents with watermark protection (no unauthorized downloads)
- **OTP Authentication** - Email-based one-time password verification for security

### Admin Dashboard
- **Document Management** - Upload, create, and manage documents
- **Bill Management** - Add bills manually or import via Excel
- **Settings Panel** - Configure SMTP, OTP settings, branding, and page content
- **Verification Logs** - Track all verification activities
- **BS/AD Date Support** - Nepali (Bikram Sambat) calendar integration

## Tech Stack

- **Backend:** PHP 8.0+ with PDO
- **Database:** MySQL
- **Frontend:** Tailwind CSS, Font Awesome
- **PDF Generation:** FPDF Library

## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7+
- Apache/Nginx web server
- PHP extensions: PDO, GD, mbstring

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/verification-portal.git
   cd verification-portal
   ```

2. **Configure database connection**

   Edit `verification/config/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

3. **Import database schema**

   Import `verification/database/schema.sql` into your MySQL database.

4. **Set folder permissions**
   ```bash
   chmod 755 verification/uploads/documents
   chmod 755 verification/uploads/bills
   ```

5. **Configure email settings**

   Edit `verification/config/mail.php` with your email configuration.

## Project Structure

```
verification/
├── admin/                  # Admin panel pages
│   ├── dashboard.php       # Admin dashboard
│   ├── login.php           # Admin authentication
│   ├── create-document.php # PDF document generator
│   ├── add-document.php    # Document upload
│   ├── manage-documents.php
│   ├── add-bill.php
│   ├── import-bills.php    # Excel import feature
│   ├── manage-bills.php
│   ├── settings.php        # System settings
│   ├── branding.php        # Logo & favicon
│   └── verification-logs.php
│
├── config/
│   ├── db.php              # Database configuration
│   ├── security.php        # Security functions
│   └── mail.php            # Email configuration
│
├── includes/
│   ├── functions.php       # Helper functions
│   ├── admin_header.php    # Admin layout
│   ├── admin_footer.php
│   └── CompanyPDF.php      # Custom PDF class
│
├── libs/
│   └── fpdf.php            # FPDF library
│
├── public/
│   ├── verify-document.php # Public document verification
│   ├── verify-bill.php     # Public bill verification
│   └── api/                # AJAX endpoints
│
├── uploads/                # File storage
│   ├── documents/
│   └── bills/
│
├── assets/
│   ├── css/
│   └── js/
│
├── database/
│   └── schema.sql          # Database schema
│
└── index.php               # Landing page
```

## Database Tables

| Table | Description |
|-------|-------------|
| `ver_documents` | Document records with metadata |
| `ver_bills` | Bill records (PAN/non-PAN support) |
| `ver_otp_requests` | OTP verification tracking |
| `ver_verification_logs` | Public verification audit trail |
| `ver_admin_logs` | Admin activity logs |
| `ver_settings` | System configuration |
| `ver_nepali_dates` | BS/AD date conversion data |

## Security Features

- CSRF token protection on all forms
- Session-based rate limiting
- Secure file upload validation (PDF, JPG, PNG only, max 5MB)
- Password hashing with `password_hash()`
- Direct access prevention for include files
- Watermarked document viewing

## Configuration

### Email Settings
Configure OTP delivery in `config/mail.php`:
- `MAIL_FROM_EMAIL` - Sender email address
- `OTP_LENGTH` - OTP digit length (default: 6)
- `OTP_EXPIRY_MINUTES` - OTP validity period (default: 10)

### Security Settings
Adjust in `config/security.php`:
- `MAX_FILE_SIZE` - Maximum upload size
- `ALLOWED_FILE_TYPES` - Permitted file extensions

## Nepal-Specific Features

This portal includes support for:
- **Bikram Sambat (BS) Calendar** - Nepali date picker and BS/AD conversion
- **PAN Validation** - 9-digit Nepal PAN number format
- **Nepali Date Range** - Supports BS years 2000-2090

## Usage

### Admin Access
1. Navigate to `/verification/admin/login.php`
2. Log in with admin credentials
3. Add documents/bills through the dashboard

### Public Verification
1. Visit `/verification/`
2. Select document or bill verification
3. Enter document/bill number and date
4. Receive OTP via email
5. Enter OTP to view the verified document

## Deployment

For detailed deployment instructions on cPanel hosting, see [DEPLOYMENT_GUIDE.txt](DEPLOYMENT_GUIDE.txt).

### Quick Checklist
- [ ] Upload files to `public_html/verification/`
- [ ] Configure database credentials
- [ ] Import SQL schema
- [ ] Set folder permissions
- [ ] Configure email settings
- [ ] Test verification flow

## Color Theme

```javascript
colors: {
    'primary-red': '#D72828',
    'primary-red-dark': '#B82020',
    'primary-blue': '#1E73BE',
    'primary-dark': '#1A3647',
    'primary-teal': '#008BB0',
}
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/new-feature`)
3. Commit your changes (`git commit -m 'Add new feature'`)
4. Push to the branch (`git push origin feature/new-feature`)
5. Open a Pull Request

## License

This project is proprietary software developed for Trishakti Group.

## Support

- **Website:** [sarojrijal.com.np](https://sarojrijal.com.np)
- **Email:** saroj.rijal07@gmail.com

---

Developed for Trishakti Group
