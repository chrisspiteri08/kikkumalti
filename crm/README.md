# Kikku Partnerships CRM

## cPanel setup

1. In **MySQL Databases**, create a database and a database user. Assign the user **ALL PRIVILEGES** on that database.
2. Open **phpMyAdmin**, select the new database, choose **Import**, and import `crm/schema.sql`. If the CRM was already installed, import the migrations in order: `crm/migrations/001_add_users.sql`, then `crm/migrations/002_decimal_scores.sql`.
3. Copy `crm/config.example.php` to the cPanel account home directory (one level above `public_html`) as `kikku-crm-config.php`, then enter the database credentials and a second strong, random `setup_key`.
4. Visit `/crm/setup.php` and create the first CRM user with that setup key. The page switches itself off once the first account exists.
5. Remove cPanel **Directory Privacy** for the `crm` folder. The CRM now uses its own password-based sign-in at `/crm/login.php`.
6. Use **Import CSV** to upload the one-time export from the Google Sheet. Check the preview, choose the source columns, and import.

The CRM does not send outreach. “Sent Manually” records activity only.

## CSV re-import

Use **Update prospects from CSV** to correct or enrich imported records. It matches a record by the trimmed, case-insensitive **Prospect** value and shows updates, creates, possible duplicates and validation errors before applying anything.

Select these CSV headings for the matching CRM fields:

| CRM field | CSV heading |
| --- | --- |
| Prospect | Prospect |
| Category | Category |
| Opportunity Type | Opportunity Type |
| Website / Social | Website / Social |
| Why Kikku Fits | Why Kikku Fits |
| Partnership Idea | Partnership Idea |
| Commercial Potential | Commercial Potential |
| Strategic Value | Strategic Value |
| Score /10 | Score /10 |
| Contact Person | Contact Person |
| Role | Role |
| Email | Email |
| Social Contact | Social Contact |
| Best Channel | Best Channel |
| Status | Status |
| Outreach Draft | Outreach Draft |
| Last Contact | Last Contact |
| Follow-up Date | Follow-up Date |
| Reply Summary | Reply Summary |
| Recommended Next Step | Recommended Next Step |
| Notes | Notes |

Blank CSV values never overwrite existing CRM fields. Existing activities and drafts remain intact.

## Backups

Use **Export backup** in the top navigation to download all prospects as a CSV. Keep regular copies somewhere private.
