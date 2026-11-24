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

## Temporary Render deployment

The included `Dockerfile` packages the PHP app for a Render Docker Web Service. For a database-backed deployment:

1. Push this project to a private GitHub repository. Do not commit `.env`; `.gitignore` excludes it. Review the files you are pushing first.
2. In Render, create a **private MySQL service** using Render's [MySQL guide](https://render.com/docs/deploy-mysql). Set the database name and a dedicated app username/password, then attach a persistent disk at `/var/lib/mysql`. Keep the credentials private.
3. In Render, create a **Web Service** from the GitHub repository, select **Docker**, and use the repository root as the Docker build context. The included image listens on port `10000`; set the service's port to `10000` if Render asks.
4. Add these environment variables to the web service using the MySQL service's internal connection details:
   - `DB_HOST`: MySQL private hostname (not a URL)
   - `DB_PORT`: `3306`
   - `DB_DATABASE`: `neco_futsal`
   - `DB_USERNAME`: the app's MySQL username
   - `DB_PASSWORD`: the app user's password
5. Deploy the web service. In its **Shell**, import the schema using the MySQL client included in the Docker image:

   ```sh
   mysql --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" --password < database/schema.sql
   ```

   Enter `DB_PASSWORD` when prompted. The schema creates and selects `neco_futsal`.
6. Set the Render health check path to `/api/health`, then deploy again if needed. Create the first Super Admin from the web service Shell: set a temporary, strong `ADMIN_PASSWORD` environment variable in the Render dashboard, then run `php bin/create-admin.php "Tournament Administrator" you@example.com`. Remove `ADMIN_PASSWORD` from the service environment after creating the account.
7. If you need admin image uploads, attach a persistent disk mounted at `/var/www/html/uploads`. Without it, uploaded images can be lost when the service is redeployed or restarted.

Render's MySQL service and persistent disks may incur charges; review current plan pricing before deploying. Avoid using the MySQL root account for the app. A Render deployment has its own database and does not automatically include your local records or uploaded images.

## Data notes

The MySQL schema is in `database/schema.sql`. It creates the application tables and foreign keys but does not migrate existing MongoDB documents. Export and map existing records before importing them into MySQL. Keep the old MongoDB credentials in `server/.env` out of source control; the PHP application does not read that file.
