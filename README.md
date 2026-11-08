# MKU Complaints and Feedback System

A web application for Mount Kigali University students to submit and track complaints and feedback. Staff manage issues assigned to their departments; administrators manage users, departments, complaints, and reports.

Built with PHP 8, MySQL/MariaDB, vanilla JavaScript, and CSS. It is designed to run locally with XAMPP or on a PHP/MySQL web server.

## What the System Does

- Students register, manage their profile, submit complaints with optional attachments, follow status changes, reply to staff, rate resolved complaints, and submit feedback.
- Staff see complaints for their department, update status, reply to students, add internal notes, and produce reports.
- Administrators route complaints to departments, manage accounts and departments, review all complaints, and run reports and backups.
- The system provides complaint tracking IDs, status history, notifications, password reset, and dashboards for each role.

See [diagram.md](diagram.md) for the architecture, workflows, complaint lifecycle, and database relationships.

## Install on Another Computer

### Requirements

- Windows with XAMPP (Apache, PHP 8.0 or later, and MySQL/MariaDB), or an equivalent PHP/MySQL server.
- Composer, required to install Dompdf for PDF reports and receipts.
- A browser such as Chrome or Firefox.

### XAMPP Setup

1. Install XAMPP and Composer on the new computer.
2. Copy this project to `C:\xampp\htdocs\mku-complaints`, or clone it after it has been published:

   ```powershell
   cd C:\xampp\htdocs
   git clone https://github.com/softboyai/complaints_system.git mku-complaints
   ```

3. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
4. Open `http://localhost/phpmyadmin`, create a database named `mku_complaints` with `utf8mb4_unicode_ci` collation, then import `database/schema.sql`.
5. Optionally import `database/seed.sql` for demo accounts and sample complaints. Demo accounts use `Password@123`; change those passwords before sharing a non-local installation.
6. In a terminal, install the locked Composer dependencies:

   ```powershell
   cd C:\xampp\htdocs\mku-complaints
   composer install --no-dev --optimize-autoloader
   ```

7. Open `config/config.php`. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match this computer's database. Set `APP_URL` to the URL where the project is served, without a trailing slash. The default local value is `http://localhost/mku-complaints`.
8. For local development, leave `DEV_MODE` enabled. Open `http://localhost/mku-complaints` and register a student account or sign in with a demo account.

`database/schema.sql` drops and recreates the application's tables. Import it only into a new/empty database or after backing up any existing data. Do not re-import it to update an installation. The seed contains sample data and is optional; its `INSERT` statements are not an upgrade mechanism.

### Using a Different Folder or Computer Name

If the project folder is named differently, update `APP_URL` in `config/config.php` to match, for example `http://localhost/campus-complaints`. If other computers on a local network must access it, configure Apache to accept network connections, use the host computer's LAN address in `APP_URL`, and allow Apache through the host firewall. For a public deployment, use a properly configured web host and HTTPS rather than exposing the XAMPP development server.

## Demo Accounts

When `database/seed.sql` has been imported, these demo accounts use `Password@123`:

| Role | Email |
|---|---|
| Admin | `admin@mku.ac.rw` |
| Staff, Academics | `academics@mku.ac.rw` |
| Staff, Finance | `finance@mku.ac.rw` |
| Staff, ICT | `ict@mku.ac.rw` |
| Student | `irakoze.ntwari@students.mku.ac.rw` |
| Student | `amina.mukagatare@students.mku.ac.rw` |
| Student | `eric.hakizimana@students.mku.ac.rw` |

These are sample accounts, not production credentials. Reset or replace them before deployment.

## User Workflows

### Students

Students register from **Create account** with their name, registration number, email, and faculty. Registration signs them in and opens their profile. From the student dashboard they can submit a complaint, optionally mark it anonymous, attach supporting files, and track its updates. They can reply to public staff responses, rate resolved complaints, download receipts, and submit course, lecturer, service, or facility feedback.

### Staff

An administrator creates staff accounts and assigns a department. Staff sign in to see complaints routed to that department. They can update the complaint status, add a public reply or an internal note, and view reports. Students receive in-app notifications for status updates and public replies.

### Administrators

Administrators can create staff/admin accounts in **Users**, enable or disable accounts, manage departments, assign complaints to departments, review all complaint activity, and generate reports or database backups. Students should use self-registration so their registration number and faculty are collected.

## Change Names, Logo, and Colors

- **Application name and browser title:** change `APP_NAME` in `config/config.php`. The shared page title uses this setting.
- **University name or other brand wording:** update the relevant text in `includes/header.php`, `includes/footer.php`, `index.php`, `register.php`, `reports/pdf.php`, and `student/receipt.php` as needed.
- **Logo:** replace `assets/images/MKUR-logo.png` with the new PNG, keeping that filename, or update the logo image paths in the shared header/footer. The authenticated sidebar, public header, and footer use this asset.
- **Main colors:** edit the CSS custom properties at the top of `assets/css/style.css` under `:root`. The primary accent is `--blue`; related tokens include `--blue-hover`, `--blue-light`, the surface colors, text colors, and success/warning/error colors. Dark-mode overrides are in `.dark` immediately below. Some status and report colors are intentionally defined locally, so search the stylesheet for a specific color if it does not change with a token.
- **URL and mail sender:** configure `APP_URL`, `MAIL_FROM`, and `MAIL_FROM_NAME` in `config/config.php`. SMTP settings are in the same file.

## Project Map

| Path | Purpose |
|---|---|
| `config/` | Application and database configuration |
| `database/` | Database schema and optional demo seed |
| `student/`, `staff/`, `admin/` | Role-specific pages |
| `includes/` | Shared authentication, validation, layout, mail, and utility code |
| `api/` | Dashboard charts and notification endpoints |
| `assets/` | CSS, JavaScript, charts, and university logo |
| `uploads/` | Private complaint attachments |
| `reports/` | PDF and CSV report generators |
| `cron/`, `scripts/` | Escalation and backup jobs |
| `docs/`, `tests/` | Project documentation and tests |

## Configuration and Production Safety

- Use a dedicated database account with only the permissions the app needs; do not use a blank-password database administrator account in production.
- Set `DEV_MODE` to `false` in production and configure SMTP before relying on email flows.
- Serve the application over HTTPS, use strong unique passwords, and remove or change all demo accounts.
- Keep `uploads/`, `logs/`, and `backups/` private and backed up according to local policy. Uploaded complaint files may contain sensitive information.
- Never commit real database, SMTP, or hosting credentials.

## Tests and Syntax Checks

Run the existing validator test with the project's installed Composer dependencies and a PHPUnit executable, if configured. To check a PHP file's syntax with XAMPP PHP:

```powershell
C:\xampp\php\php.exe -l student\dashboard.php
```
