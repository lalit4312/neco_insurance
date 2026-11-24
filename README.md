# Neco Insurance Futsal Cup

The application is a server-rendered PHP site with a MySQL database. React, Vite, Express, and MongoDB are no longer used by the app.

## Requirements

- PHP 8.1 or later with PDO, `pdo_mysql`, Fileinfo, SimpleXML, and zlib enabled; set `upload_max_filesize=6M` and `post_max_size=8M` for 5 MB image/workbook uploads
- MySQL 8.0 or later
- Apache with `mod_rewrite` for production, or the PHP development server for local use

## Local setup

1. Import the schema (it creates the `neco_futsal` database):

   ```powershell
   Get-Content .\database\schema.sql | mysql -u root -p
   ```

2. Copy `.env.example` to `.env` and set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

3. Start the PHP development server from the project root:

   ```powershell
   php -S 127.0.0.1:8000 router.php
   ```

4. Create the first administrator. Set `ADMIN_PASSWORD` to a unique password of at least 12 characters before running:

   ```powershell
   $env:ADMIN_PASSWORD = 'your-long-unique-password'
   php .\bin\create-admin.php "Tournament Administrator" admin@example.com
   ```

5. Open `http://127.0.0.1:8000`. Sign in at `/admin`.

## Routes and features

- Public tournament pages: `/`, `/fixtures`, `/results`, `/standings`, `/teams`, `/players`, `/stats`, `/bracket`, and `/sponsors`
- Admin portal: `/admin`, with role-protected tournament, team, player, fixture, result, sponsor, user, and audit-log management
- Health check: `/api/health`
- Result drafts remain private; publishing a result updates the fixture and standings
- Admin image fields use a file picker for JPG, PNG, WebP, or GIF uploads up to 5 MB. Uploads are stored under `uploads/`; existing images are retained when editing a record without selecting a replacement.
- Admins and Super Admins can download the multi-sheet `.xlsx` template from **Admin → Excel bulk import**, then validate and import tournaments, teams, players, fixtures, results, and sponsors. Each valid row is committed separately; duplicate or invalid rows are reported and never overwrite existing records. The form shows the effective PHP upload limit.
- Run the workbook tests with `php .\tests\excel_test.php`.

## Apache deployment

Point the virtual host document root at the project root, enable PHP and `mod_rewrite`, and allow the repository `.htaccess` rules. Configure the database variables in the hosting environment or a root `.env` file that is not web-accessible. Ensure `uploads/` is writable by the PHP process and is served as static files; the included upload-directory rules deny PHP execution there.

The application returns a generic unavailable response when MySQL cannot be reached and writes diagnostic details to the PHP error log. Check `/api/health` and the hosting logs when diagnosing a database connection issue.

## Data notes

The MySQL schema is in `database/schema.sql`. It creates the application tables and foreign keys but does not migrate existing MongoDB documents. Export and map existing records before importing them into MySQL. Keep the old MongoDB credentials in `server/.env` out of source control; the PHP application does not read that file.
