<?php
require __DIR__ . '/bootstrap.php';
if ((int) db()->query('SELECT COUNT(*) FROM crm_users')->fetchColumn() > 0) redirect('login.php');

$error = ''; $key = (string) ($config['setup_key'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submittedKey = trim((string) ($_POST['setup_key'] ?? ''));
    $name = mb_substr(trim((string) ($_POST['full_name'] ?? '')), 0, 190);
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($key === '' || !hash_equals($key, $submittedKey)) $error = 'The setup key is not correct.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif (mb_strlen($password) < 12) $error = 'Choose a password with at least 12 characters.';
    else {
        $stmt = db()->prepare('INSERT INTO crm_users (email, full_name, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
        flash('Your first CRM account is ready. Please sign in.'); redirect('login.php');
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Set up · Kikku Partnerships CRM</title><link rel="stylesheet" href="style.css"></head><body><main class="login-shell"><section class="login-card"><div class="login-brand"><strong>KIKKU</strong><span>Partnerships CRM</span></div><h1>Create the first account</h1><p>This one-time setup page works only with the private setup key in <code>kikku-crm-config.php</code>. It closes automatically once an account exists.</p><?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Your name</label><input name="full_name" autocomplete="name" required></div><div class="field"><label>Email address</label><input type="email" name="email" autocomplete="email" required></div><div class="field"><label>Strong password</label><input type="password" name="password" autocomplete="new-password" minlength="12" required></div><div class="field"><label>Private setup key</label><input type="password" name="setup_key" autocomplete="off" required></div><button class="button" type="submit">Create admin account</button></form></section></main></body></html>
