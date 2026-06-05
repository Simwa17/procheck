# ProCheck

A web-based project pricing tool built for Malawian software developers and studios. ProCheck helps you generate professional quotes for software projects — picking modules, complexity tiers, developer rates, and margins — then email them directly to clients.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

- **4-step quote builder wizard** — project type, module selection, complexity/tier, margin & review
- **Live pricing** — real-time MWK/USD totals update as you configure the quote
- **Developer rate tiers** — configurable Junior / Mid / Senior hourly rates in MWK
- **Client management** — store client contact details and attach them to quotes
- **Quote lifecycle** — Draft → Sent → Accepted / Rejected status tracking
- **Print to PDF** — browser-native print layout for every quote
- **Email quotes to clients** — sends a branded HTML email with a full line-item breakdown
- **Email verification** — registration sends a verification link; unverified users see a persistent banner
- **Admin panel** — manage modules, project types, developer rates, users, and system settings
- **SMTP configuration** — plug in any SMTP provider (Gmail, Mailtrap, etc.) or fall back to PHP `mail()`
- **No Composer required** — zero external dependencies; pure PHP with PDO and a custom SMTP client

---

## Screenshots

> _Add screenshots of the dashboard, quote builder, and quote PDF here._

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.1 or later |
| MySQL / MariaDB | 5.7 / 10.4 or later |
| Web server | Apache (WAMP / XAMPP / LAMP) or Nginx |

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-username/procheck.git
```

Place the folder inside your web server's document root (e.g. `C:\wamp64\www\procheck` or `/var/www/html/procheck`).

### 2. Configure the application

Copy or edit `config.php` in the project root:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'procheck');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_URL',  'http://localhost/procheck');   // no trailing slash
define('APP_NAME', 'ProCheck');
```

### 3. Run the installer

Visit the installer in your browser:

```
http://localhost/procheck/setup/install.php
```

This creates the database schema, seeds developer rates, project types, and modules, and creates your admin account.

### 4. (Existing installs) Run the email migration

If you installed ProCheck before the email feature was added, run:

```
http://localhost/procheck/setup/migrate_email.php
```

This adds the `email_verified`, `email_token`, and `email_token_expires` columns and inserts the SMTP settings keys.

### 5. Log in

Navigate to:

```
http://localhost/procheck
```

Use the admin credentials you set during installation.

---

## SMTP / Email Setup

Go to **Admin → Settings → Mail / SMTP** and fill in your provider's details.

| Setting | Description |
|---|---|
| From Name | Sender name shown in the recipient's inbox |
| From Email | Address emails are sent from |
| SMTP Host | e.g. `smtp.gmail.com` or `smtp.mailtrap.io` |
| SMTP Port | `587` (STARTTLS) · `465` (SSL) · `25` (none) |
| Encryption | STARTTLS recommended |
| Username | Your SMTP account username |
| Password | App-specific password (see below) |

Use **Admin → Send Test Email** after saving to confirm delivery.

### Gmail

1. Enable 2-Step Verification on your Google account.
2. Generate an **App Password** (Google Account → Security → App Passwords).
3. Use `smtp.gmail.com`, port `587`, encryption `STARTTLS`, and the app password.

### Mailtrap (recommended for development)

Sign up free at [mailtrap.io](https://mailtrap.io), copy the SMTP credentials from your inbox, and paste them into Settings. All emails are caught in Mailtrap's sandbox — nothing reaches real inboxes.

---

## Project Structure

```
procheck/
├── admin/                  # Admin panel pages
│   ├── index.php           # Admin dashboard
│   ├── modules.php         # Module & category management
│   ├── project_types.php   # Project type management
│   ├── rates.php           # Developer hourly rates
│   ├── settings.php        # System & SMTP settings
│   ├── test_mail.php       # Send a test email
│   └── users.php           # User management
├── assets/
│   ├── css/style.css       # Custom styles
│   └── js/quote-builder.js # Quote wizard JavaScript
├── clients/                # Client CRUD
├── includes/
│   ├── auth.php            # Session, login, CSRF
│   ├── db.php              # PDO singleton
│   ├── footer.php
│   ├── functions.php       # Helpers & DB queries
│   ├── header.php          # Navbar, flash messages, verify banner
│   └── mailer.php          # SMTP client + mail() fallback
├── quotes/
│   ├── create.php          # 4-step quote builder wizard
│   ├── email.php           # Email quote to client
│   ├── index.php           # Quote listing
│   ├── print.php           # Print-ready layout
│   └── view.php            # Quote detail & status
├── setup/
│   ├── install.php         # First-run installer
│   └── migrate_email.php   # Email feature migration
├── config.php
├── dashboard.php
├── index.php               # Redirects to login or dashboard
├── login.php
├── logout.php
├── register.php
├── resend_verify.php
└── verify.php
```

---

## Default Developer Rates

Seeded by the installer (editable in Admin → Rates):

| Tier | Rate (MWK/hr) |
|---|---|
| Junior | 3,500 |
| Mid-level | 8,000 |
| Senior | 18,000 |

---

## Security Notes

- Passwords are hashed with `password_hash()` / `bcrypt`
- All forms use CSRF tokens
- All output is escaped with `htmlspecialchars()`
- SQL queries use PDO prepared statements throughout
- SMTP passwords are never echoed back in the settings form
- Email tokens expire after 48 hours

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "Add my feature"`
4. Push: `git push origin feature/my-feature`
5. Open a Pull Request

---

## License

MIT © 2025 — built for Malawian developers, free to use and adapt.
