<?php
declare(strict_types=1);

const CRM_STATUSES = ['Researching', 'Draft Saved', 'Ready to Send', 'Sent Manually', 'Replied', 'In Discussion', 'Closed – Won', 'Closed – Not Pursuing'];
const CRM_OPPORTUNITY_TYPES = ['Paid', 'Value Exchange', 'Strategic Collab', 'Not Worth Pursuing'];
const CRM_DRAFT_STATUSES = ['Draft', 'Ready to Send', 'Sent Manually', 'Replied', 'Closed'];
const CRM_ACTIVITY_TYPES = ['Research added', 'Draft prepared', 'Email sent manually', 'Reply received', 'Follow-up due', 'Follow-up completed', 'Note added'];

$configPath = dirname(__DIR__, 2) . '/kikku-crm-config.php';
if (!is_readable($configPath)) {
    http_response_code(503);
    exit('CRM configuration is not installed yet. Follow crm/README.md.');
}

$config = require $configPath;
date_default_timezone_set((string) ($config['timezone'] ?? 'Europe/Malta'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, private, max-age=0');

function db(): PDO
{
    global $config;
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, (string) $config['db_user'], (string) $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['crm_csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void {
    if (!hash_equals($_SESSION['crm_csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Your session expired. Please go back and try again.');
    }
}
function flash(string $message, string $type = 'success'): void { $_SESSION['crm_flash'] = [$message, $type]; }
function take_flash(): ?array { $flash = $_SESSION['crm_flash'] ?? null; unset($_SESSION['crm_flash']); return $flash; }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function valid_choice(string $value, array $choices, string $fallback): string { return in_array($value, $choices, true) ? $value : $fallback; }
function date_or_null(string $value): ?string { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
function score_or_null(string $value): ?string {
    $value = str_replace(',', '.', trim($value));
    if ($value === '') return null;
    if (!is_numeric($value) || (float) $value < 0 || (float) $value > 10) return null;
    return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
}
function score_label(mixed $value): string { return $value === null || $value === '' ? '—' : rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') . '/10'; }
function status_class(string $status): string { return 'status-' . strtolower(preg_replace('/[^a-z]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $status) ?: $status)); }
function prospect_url(int $id): string { return 'prospect.php?id=' . $id; }

function current_user(): ?array
{
    if (empty($_SESSION['crm_user_id'])) return null;
    static $user;
    if (is_array($user)) return $user;
    $stmt = db()->prepare('SELECT id, email, full_name FROM crm_users WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $_SESSION['crm_user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) unset($_SESSION['crm_user_id']);
    return $user;
}
function require_login(): void
{
    if (!current_user()) redirect('login.php');
}
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['crm_user_id'] = (int) $user['id'];
}

function add_activity(int $prospectId, string $type, string $note = '', ?string $date = null): void
{
    $stmt = db()->prepare('INSERT INTO activities (prospect_id, activity_type, note, activity_date) VALUES (?, ?, ?, ?)');
    $stmt->execute([$prospectId, $type, trim($note), $date ?: date('Y-m-d')]);
}

function prospect_payload(): array
{
    $text = static fn (string $key, int $limit = 20000): string => mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $limit);
    $score = score_or_null((string) ($_POST['score'] ?? ''));
    return [
        'prospect' => $text('prospect', 190), 'category' => $text('category', 120),
        'opportunity_type' => valid_choice($text('opportunity_type', 40), CRM_OPPORTUNITY_TYPES, 'Strategic Collab'),
        'website' => $text('website', 500), 'social_links' => $text('social_links'), 'why_kikku_fits' => $text('why_kikku_fits'),
        'partnership_idea' => $text('partnership_idea'), 'commercial_potential' => $text('commercial_potential'),
        'strategic_value' => $text('strategic_value'), 'score' => $score,
        'contact_person' => $text('contact_person', 190), 'contact_role' => $text('contact_role', 190), 'email' => $text('email', 190),
        'social_contact' => $text('social_contact', 500), 'best_channel' => $text('best_channel', 80),
        'status' => valid_choice($text('status', 40), CRM_STATUSES, 'Researching'), 'last_contact' => date_or_null($text('last_contact', 10)),
        'follow_up_date' => date_or_null($text('follow_up_date', 10)), 'reply_summary' => $text('reply_summary'),
        'recommended_next_step' => $text('recommended_next_step'), 'notes' => $text('notes'),
    ];
}
