<?php
// Copy this file to your cPanel account home directory as kikku-crm-config.php.
// Do not put the real credentials inside public_html or commit them to Git.
return [
    'db_host' => 'localhost',
    'db_name' => 'YOUR_CPANEL_DATABASE_NAME',
    'db_user' => 'YOUR_CPANEL_DATABASE_USER',
    'db_password' => 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD',
    // Make this a separate long, random secret. It is required only once to create the first CRM user.
    'setup_key' => 'REPLACE_WITH_A_SECOND_LONG_RANDOM_SECRET',
    'app_name' => 'Kikku Partnerships CRM',
    'timezone' => 'Europe/Malta',
];
