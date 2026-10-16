# Work with Kikku enquiry form setup

The public form posts to `partnership-enquiry.php`. It sends a plain-text email through the cPanel PHP mail service and does not add the sender to MailerLite.

Before using it on the live site:

1. Copy `partnership-enquiry-config.example.php` to `partnership-enquiry-config.php` one directory above the website's public directory (the same private location used for `mailerlite-config.php`).
2. Set `recipient` to Chris's inbox and use a real mailbox on `kikkumalti.com` for `from_email`, for example `website@kikkumalti.com`.
3. Make sure that mailbox/forwarder exists in cPanel and send a test enquiry.

The real configuration file is listed in `.gitignore` and must never be committed.
