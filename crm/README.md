# Kikku Partnerships CRM

## cPanel setup

1. In **MySQL Databases**, create a database and a database user. Assign the user **ALL PRIVILEGES** on that database.
2. Open **phpMyAdmin**, select the new database, choose **Import**, and import `crm/schema.sql`. If the CRM was already installed before the sign-in update, import `crm/migrations/001_add_users.sql` instead.
3. Copy `crm/config.example.php` to the cPanel account home directory (one level above `public_html`) as `kikku-crm-config.php`, then enter the database credentials and a second strong, random `setup_key`.
4. Visit `/crm/setup.php` and create the first CRM user with that setup key. The page switches itself off once the first account exists.
5. Remove cPanel **Directory Privacy** for the `crm` folder. The CRM now uses its own password-based sign-in at `/crm/login.php`.
6. Use **Import CSV** to upload the one-time export from the Google Sheet. Check the preview, choose the source columns, and import.

The CRM does not send outreach. “Sent Manually” records activity only.

## Backups

Use **Export backup** in the top navigation to download all prospects as a CSV. Keep regular copies somewhere private.
