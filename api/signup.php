<?php
/*
 * Saves a book-publication sign-up to MySQL.
 *
 * Database credentials are read from db-config.php, which is kept out of git.
 * See README.md for the format.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Method not allowed.']);
}

/* Accept submissions only from this site's own pages. */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) {
    respond(403, ['error' => 'Forbidden.']);
}

/* The hidden "website" field is a spam trap: report success, save nothing. */
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$name = trim((string) ($_POST['name'] ?? ''));

if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respond(422, ['error' => 'Please enter a valid email address.']);
}
if (empty($_POST['consent'])) {
    respond(422, ['error' => 'Please confirm that you’d like to receive emails.']);
}
if (mb_strlen($name) > 80) {
    respond(422, ['error' => 'Please shorten your full name to 80 characters or fewer.']);
}

try {
    $config = require __DIR__ . '/db-config.php';

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    /* A repeat sign-up keeps the original entry and gets the same response. */
    $statement = $pdo->prepare(
        'INSERT INTO signups (email, full_name, consent) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE email = email'
    );
    $statement->execute([$email, $name === '' ? null : $name]);
} catch (Throwable $error) {
    error_log('Sign-up failed: ' . $error->getMessage());
    respond(500, ['error' => 'We couldn’t save your request. Please try again.']);
}

respond(200, ['ok' => true]);
