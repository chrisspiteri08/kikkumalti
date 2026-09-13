<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Method not allowed.');
}

$language = ($_POST['language'] ?? 'mt') === 'en' ? 'en' : 'mt';
$messages = [
    'mt' => [
        'invalid' => 'Jekk jogħġbok iċċekkja d-dettalji u erġa’ pprova.',
        'unavailable' => 'Is-servizz tal-email għadu mhux disponibbli. Erġa’ pprova aktar tard.',
        'success' => 'Grazzi! Iċċekkja l-email tiegħek biex tikkonferma l-abbonament.',
    ],
    'en' => [
        'invalid' => 'Please check your details and try again.',
        'unavailable' => 'The email service is not available yet. Please try again later.',
        'success' => 'Thank you! Check your email to confirm your subscription.',
    ],
];

// Quietly accept bot submissions made through the hidden honeypot field.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, $messages[$language]['success']);
}

$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$audience = (string) ($_POST['audience'] ?? '');
$consent = isset($_POST['consent']) && $_POST['consent'] === 'yes';

if ($email === false || !in_array($audience, ['parent', 'educator'], true) || !$consent) {
    respond(422, $messages[$language]['invalid']);
}

$childAges = [];
$classAgeRange = '';

if ($audience === 'parent') {
    $submittedAges = $_POST['child_age'] ?? [];
    if (!is_array($submittedAges) || count($submittedAges) < 1 || count($submittedAges) > 12) {
        respond(422, $messages[$language]['invalid']);
    }

    foreach ($submittedAges as $age) {
        $validatedAge = filter_var($age, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 17],
        ]);
        if ($validatedAge === false) {
            respond(422, $messages[$language]['invalid']);
        }
        $childAges[] = (string) $validatedAge;
    }
} else {
    $classAgeRange = trim((string) ($_POST['class_age_range'] ?? ''));
    if ($classAgeRange === '' || strlen($classAgeRange) > 40) {
        respond(422, $messages[$language]['invalid']);
    }
}

$apiToken = trim((string) getenv('MAILERLITE_API_TOKEN'));
$privateConfigPath = dirname(__DIR__) . '/mailerlite-config.php';

if ($apiToken === '' && is_readable($privateConfigPath)) {
    $privateConfig = require $privateConfigPath;
    if (is_array($privateConfig)) {
        $apiToken = trim((string) ($privateConfig['api_token'] ?? ''));
    }
}

if ($apiToken === '') {
    error_log('Kikku signup: MailerLite API token is not configured.');
    respond(503, $messages[$language]['unavailable']);
}

$payload = [
    'email' => $email,
    'status' => 'unconfirmed',
    'groups' => ['198528654772274665'],
    'fields' => [
        'audience' => $audience,
        'child_ages' => implode(', ', $childAges),
        'class_age_range' => $classAgeRange,
        'language' => $language,
    ],
    'ip_address' => filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null,
];

$request = curl_init('https://connect.mailerlite.com/api/subscribers');
curl_setopt_array($request, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
]);

$responseBody = curl_exec($request);
$responseStatus = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($request);
curl_close($request);

if ($responseBody === false || $curlError !== '' || !in_array($responseStatus, [200, 201], true)) {
    error_log('Kikku signup: MailerLite request failed with HTTP ' . $responseStatus . '.');
    respond(502, $messages[$language]['unavailable']);
}

respond(200, $messages[$language]['success']);
