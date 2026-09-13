# MailerLite setup

The website uses MailerLite group `198528654772274665` and these text fields:

- `Audience` (`audience`)
- `Child ages` (`child_ages`)
- `Class age range` (`class_age_range`)
- `Language` (`language`)

## Private API token

Keep the real token outside the GitHub checkout and outside `public_html`.

1. Copy `mailerlite-config.example.php` to the cPanel account home directory,
   one level above `public_html`.
2. Rename the copy to `mailerlite-config.php`.
3. Replace the placeholder with the real MailerLite API token.
4. Set the file permissions to `600` if cPanel supports it.

The endpoint also accepts a server environment variable named
`MAILERLITE_API_TOKEN` instead of the private configuration file.

## Before enabling the form

- Enable double opt-in for API and integrations in MailerLite.
- Create and activate the welcome automation for the `Kikku Website` group.
- Upload the activity pack and add its private download link to the welcome email.
- Test with an email address that is not already an active subscriber.
