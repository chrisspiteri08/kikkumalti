<?php
require __DIR__ . '/bootstrap.php';
if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== '') {
        $stmt = db()->prepare('SELECT * FROM crm_users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]); $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) db()->prepare('UPDATE crm_users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            db()->prepare('UPDATE crm_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            login_user($user); redirect('index.php');
        }
    }
    $error = 'The email address or password is not correct.';
}
$hasUsers = (int) db()->query('SELECT COUNT(*) FROM crm_users')->fetchColumn() > 0;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Sign in · Kikku Partnerships CRM</title><link rel="stylesheet" href="style.css"></head><body><main class="login-shell"><section class="login-card"><div class="login-brand"><strong>KIKKU</strong><span>Partnerships CRM</span></div><h1>Sign in</h1><p>This is a private internal workspace.</p><?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Email address</label><input type="email" name="email" autocomplete="username" required autofocus></div><div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" required></div><button class="button" type="submit">Sign in</button></form><?php if (!$hasUsers): ?><p class="notice" style="margin-top:18px">No CRM user exists yet. Use the private setup link with the setup key stored in your server configuration.</p><?php endif; ?></section></main></body></html>
