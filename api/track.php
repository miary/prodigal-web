<?php
/*
 * Records anonymous, cookie-free visit statistics sent by js/main.js.
 *
 * No cookies are set and no IP address is stored. Each visitor is counted
 * with a hash of their connection, browser and a random salt that is replaced
 * every day and then deleted, so the same person can't be recognised from one
 * day to the next, and the hash can't be turned back into an address.
 */

declare(strict_types=1);

const STATS_TIMEZONE = 'America/Los_Angeles';
const MAX_EVENTS_PER_VISITOR_PER_DAY = 300;
const KEEP_DAYS = 400;

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function done(int $status = 204): void
{
    http_response_code($status);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    done(405);
}

/* Accept events only from this site's own pages. */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($origin !== '' && strtolower((string) parse_url($origin, PHP_URL_HOST)) !== $host) {
    done(403);
}

/* Skip crawlers, previews and scripts. Most never run JavaScript anyway. */
$agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($agent === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|monitor|curl|wget|python|java\/|okhttp|facebookexternalhit/i', $agent)) {
    done();
}

$labels = require __DIR__ . '/stats-labels.php';
$name = (string) ($_POST['e'] ?? '');
$label = (string) ($_POST['l'] ?? '');

if ($name === 'pageview') {
    $label = null;
} elseif (!isset($labels[$name][$label])) {
    done();
}

/* The page that sent us here, as a bare host name. Visits from this site count as direct. */
function referrerHost(string $url, string $ownHost): ?string
{
    $refHost = strtolower((string) parse_url($url, PHP_URL_HOST));
    $refHost = preg_replace('/^(www|m)\./', '', $refHost);
    $ownHost = preg_replace('/^www\./', '', $ownHost);
    if ($refHost === '' || $refHost === $ownHost || !preg_match('/^[a-z0-9.-]{1,120}$/', $refHost)) {
        return null;
    }
    return $refHost;
}

function tag(string $value): ?string
{
    $value = strtolower(trim($value));
    return preg_match('/^[a-z0-9._-]{1,60}$/', $value) ? $value : null;
}

$referrer = $source = $campaign = $device = $timezone = null;
if ($name === 'pageview') {
    $referrer = referrerHost((string) ($_POST['r'] ?? ''), $host);
    $source = tag((string) ($_POST['s'] ?? ''));
    $campaign = tag((string) ($_POST['c'] ?? ''));

    $width = filter_var($_POST['w'] ?? null, FILTER_VALIDATE_INT);
    if ($width !== false && $width > 0) {
        $device = $width < 768 ? 'phone' : ($width < 1100 ? 'tablet' : 'desktop');
    }

    $zone = (string) ($_POST['tz'] ?? '');
    if ($zone !== '' && in_array($zone, DateTimeZone::listIdentifiers(), true)) {
        $timezone = $zone;
    }
}

try {
    $config = require __DIR__ . '/db-config.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $day = (new DateTimeImmutable('now', new DateTimeZone(STATS_TIMEZONE)))->format('Y-m-d');

    /* Today's salt. Older salts are deleted, which makes older hashes unlinkable. */
    $pdo->prepare('DELETE FROM stats_salts WHERE day < ?')->execute([$day]);
    $pdo->prepare('INSERT IGNORE INTO stats_salts (day, salt) VALUES (?, ?)')
        ->execute([$day, bin2hex(random_bytes(32))]);
    $saltQuery = $pdo->prepare('SELECT salt FROM stats_salts WHERE day = ?');
    $saltQuery->execute([$day]);
    $salt = (string) $saltQuery->fetchColumn();

    $visitor = hash('sha256', $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $agent . '|' . $host);

    $count = $pdo->prepare('SELECT COUNT(*) FROM stats_events WHERE day = ? AND visitor = ?');
    $count->execute([$day, $visitor]);
    if ((int) $count->fetchColumn() >= MAX_EVENTS_PER_VISITOR_PER_DAY) {
        done();
    }

    $pdo->prepare(
        'INSERT INTO stats_events (day, visitor, name, label, referrer, source, campaign, device, timezone)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$day, $visitor, $name, $label, $referrer, $source, $campaign, $device, $timezone]);

    /* Now and then, drop statistics older than about 13 months. */
    if (random_int(1, 200) === 1) {
        $pdo->prepare('DELETE FROM stats_events WHERE day < ? - INTERVAL ' . KEEP_DAYS . ' DAY')->execute([$day]);
    }
} catch (Throwable $error) {
    error_log('Stats event failed: ' . $error->getMessage());
    done(500);
}

done();
