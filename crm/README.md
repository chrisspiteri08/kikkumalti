# Kikku Partnerships CRM

## cPanel setup

1. In **MySQL Databases**, create a database and a database user. Assign the user **ALL PRIVILEGES** on that database.
2. Open **phpMyAdmin**, select the new database, choose **Import**, and import `crm/schema.sql`.
3. Copy `crm/config.example.php` to the cPanel account home directory (one level above `public_html`) as `kikku-crm-config.php`, then enter the database credentials.
4. In **Directory Privacy**, protect the `crm` directory with a strong password. This must be enabled before using the CRM publicly.
5. Visit `/crm/`. Use **Import CSV** to upload the one-time export from the Google Sheet. Check the preview, choose the source columns, and import.

The CRM does not send outreach. “Sent Manually” records activity only.

## Backups

Use **Export backup** in the top navigation to download all prospects as a CSV. Keep regular copies somewhere private.
