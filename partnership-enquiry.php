<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reply(405, 'Method not allowed.');
}

$language = ($_POST['language'] ?? 'en') === 'mt' ? 'mt' : 'en';
$messages = [
    'en' => [
        'invalid' => 'Please complete the required fields and try again.',
        'slow' => 'Please wait a moment before submitting the form.',
        'unavailable' => 'We could not send your enquiry right now. Please try again later.',
        'success' => 'Thank you — Chris will be in touch soon.',
        'sponsor_success' => 'Thanks for your interest! Chris will get back to you to discuss the opportunity.',
    ],
    'mt' => [
        'invalid' => 'Jekk jogħġbok imla l-oqsma meħtieġa u erġa’ pprova.',
        'slow' => 'Jekk jogħġbok stenna ftit qabel tibgħat il-formola.',
        'unavailable' => 'Ma rnexxilniex nibagħtu l-mistoqsija bħalissa. Erġa’ pprova aktar tard.',
        'success' => 'Grazzi — Chris se jikkuntattjak dalwaqt.',
        'sponsor_success' => 'Grazzi tal-interess tiegħek! Chris se jikkuntattjak biex jiddiskuti l-opportunità.',
    ],
];

// Honeypot submissions receive a success response without sending an email.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    reply(200, $messages[$language]['success']);
}

$started = filter_var($_POST['form_started'] ?? null, FILTER_VALIDATE_INT);
if ($started === false || $started === null || time() - $started < 3 || time() - $started > 7200) {
    reply(422, $messages[$language]['slow']);
}

$name = trim((string) ($_POST['name'] ?? ''));
$business = trim((string) ($_POST['business'] ?? ''));
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$interest = trim((string) ($_POST['interest'] ?? 'General collaboration enquiry'));
$idea = trim((string) ($_POST['idea'] ?? ''));
$source = trim((string) ($_POST['enquiry_source'] ?? ''));
$isSponsorship = $source === 'Bongu Ghalik sponsorship';
$allowedInterests = ['Series sponsorship', 'Original song and animation', 'Seasonal campaign', 'Educational collaboration', 'Other / not sure yet'];

if ($name === '' || mb_strlen($name) > 120 || $business === '' || mb_strlen($business) > 160 || $email === false || ($interest !== 'General collaboration enquiry' && !in_array($interest, $allowedInterests, true)) || (!$isSponsorship && $idea === '') || mb_strlen($idea) > 5000 || preg_match('/[\r\n]/', $name.$business)) {
    reply(422, $messages[$language]['invalid']);
}

$configPath = dirname(__DIR__) . '/partnership-enquiry-config.php';
if (!is_readable($configPath)) {
    error_log('Kikku partnership enquiry: recipient configuration is missing.');
    reply(503, $messages[$language]['unavailable']);
}
$config = require $configPath;
$recipient = is_array($config) ? filter_var($config['recipient'] ?? '', FILTER_VALIDATE_EMAIL) : false;
$fromEmail = is_array($config) ? filter_var($config['from_email'] ?? '', FILTER_VALIDATE_EMAIL) : false;
$fromName = is_array($config) ? trim((string) ($config['from_name'] ?? 'Kikku website')) : 'Kikku website';
if ($recipient === false || $fromEmail === false || preg_match('/[\r\n]/', $fromName)) {
    error_log('Kikku partnership enquiry: recipient configuration is invalid.');
    reply(503, $messages[$language]['unavailable']);
}

$subject = 'New Kikku partnership enquiry — ' . $business;
$body = "A new partnership enquiry was submitted on kikkumalti.com.\n\n"
    . "Name: {$name}\n"
    . "Business / organisation: {$business}\n"
    . "Email: {$email}\n"
    . "Interested in: {$interest}\n"
    . "Enquiry source: " . ($source !== '' ? $source : 'Website') . "\n"
    . "Language: {$language}\n\n"
    . "Idea:\n{$idea}\n";
$headers = [
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];

if (!mail($recipient, $subject, $body, implode("\r\n", $headers))) {
    error_log('Kikku partnership enquiry: mail delivery failed.');
    reply(502, $messages[$language]['unavailable']);
}

reply(200, $messages[$language][$isSponsorship ? 'sponsor_success' : 'success']);
