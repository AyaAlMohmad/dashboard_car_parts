# Car Parts Store Management System | Car Parts Dashboard

A web application for managing the daily operations of a car parts store, featuring an Arabic right-to-left (RTL) interface. It combines inventory, customers, suppliers, invoices, payments, and reports into a single dashboard, with support for the Syrian Pound (SYP) and the US Dollar (USD).

## Features

- **Dashboard:** Activity summaries and inventory alerts.
- **Inventory & Categories:** Manage car parts, categories, prices, quantities, and availability status.
- **Customers:** Manage customer data, balances, and debt status.
- **Suppliers:** Track supplier data and amounts owed to or from them.
- **Sales & Invoices:** Create multi-item invoices, track paid and remaining amounts, and record invoice returns.
- **Purchases:** Record purchases from suppliers and return purchased items.
- **Payments:** Record customer payments, supplier payments, and refunds.
- **Withdrawals:** Record and track financial withdrawals.
- **Currencies:** Work in Syrian Pound (SYP) and US Dollar (USD), with an exchange rate setting for invoices.
- **Reports:** Financial summaries, debtor/creditor reports, inventory, sales, and monthly profit reports.
- **Export & Backups:** Export tables to Excel, create and download backups, schedule them, and send them by email after configuring SMTP.

## Tech Stack

| Component | Technology |
| --- | --- |
| Backend | PHP 8.3+ and Laravel 13 |
| Frontend | Blade, JavaScript, and CSS, with RTL support |
| Database | MySQL / MariaDB |
| Asset bundling | Vite 8 and Tailwind CSS 4 |
| Excel files | Laravel Excel (`maatwebsite/excel`) |
| Tests | PHPUnit 12 |

## Requirements

- PHP 8.3 or later, with the extensions required by Composer, including the MySQL driver.
- Composer.
- MySQL or MariaDB, with a dedicated database for the project.
- Node.js compatible with Vite 8, such as Node.js 22.12+, and npm.
- Git for cloning the repository.

Laragon can be used for local development on Windows.

> **Important:** The default `.env.example` uses SQLite, but some existing migration files contain MySQL-specific statements such as `ALTER TABLE ... MODIFY`. Configure the connection to use MySQL before running the migrations.

## Installation & Local Setup

### 1. Clone the repository and install PHP dependencies

```bash
git clone https://github.com/AyaAlMohmad/dashboard_car_parts.git
cd dashboard_car_parts
composer install
```

### 2. Configure the environment

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

On Windows Command Prompt (CMD), use `copy .env.example .env` instead of `cp`.

Create an empty database named `dashboard_car_parts`, then update the `.env` settings to match your environment:

```dotenv
APP_NAME="Car Parts Dashboard"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dashboard_car_parts
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Replace the connection values with your actual local credentials, and never commit the `.env` file to the repository.

### 3. Initialize the application and database

Run the following commands against the project's database:

```bash
php artisan key:generate
php artisan migrate
```

### 4. Install frontend dependencies and build assets

```bash
npm install --ignore-scripts
npm run build
```

### 5. Run the application

```bash
php artisan serve
```

Open the URL shown in the terminal, typically `http://localhost:8000`.

For frontend development with automatic rebuilds, run the following in a separate terminal:

```bash
npm run dev
```

When using Laragon or another web server, point the site root to the `public/` directory, and make sure `storage/` and `bootstrap/cache/` are writable.

## Backups & Scheduling

Backup management, schedules, and email settings are available from the **Backups** page in the application.

To enable scheduled tasks locally, keep the following command running in a separate terminal:

```bash
php artisan schedule:work
```

The schedules defined in the project include:

- Creating a backup daily at **02:00** according to the application's timezone.
- Checking user-defined backup schedules every minute.

You can create a manual backup with:

```bash
php artisan backup:database
```

On a production server, configure the system scheduler to run `php artisan schedule:run` every minute. The included `start-scheduler.bat` and `start-scheduler.vbs` files contain Windows-specific local paths; review and adjust them for your machine before use.

## Project Structure

```text
app/Http/Controllers/   Inventory, invoices, payments, and reports logic
app/Models/             Data models and relationships
app/Console/Commands/   Backup and scheduling commands
database/migrations/    Table creation and schema updates
database/seeders/       Demo data generators
resources/views/        Pages and Blade templates
public/assets/          CSS and JavaScript files used by the interface
routes/web.php          Page routes
routes/api.php          API endpoints for operations and reports
routes/console.php      Console commands and scheduled tasks
tests/                  Application tests
```

## Testing

After setting up a separate testing environment, run the test suite with:

```bash
composer test
```

Do not use your production/operational database for tests or demo data.

## Notes Before Deployment

- The current routes do not include authentication or user-permission protection; add authentication and authorization before exposing the application online.
- Set `APP_ENV=production` and `APP_DEBUG=false`, and use HTTPS when deploying.
- Store database and mail credentials in secure configuration, and never add secrets or backup files to Git.
- Keep backups in a secure location outside the server, and verify periodically that they can be restored.
