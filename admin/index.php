<?php
/*
 * Private sign-up list and reply page.
 *
 * Deployed under an unlisted folder name, which is never written in this repo,
 * and protected by the password in api/admin-config.php. See README.md.
 */

declare(strict_types=1);

const MAX_RECIPIENTS_PER_SEND = 50;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$apiDir = __DIR__ . '/../api';
$settings = is_file("$apiDir/admin-config.php") ? require "$apiDir/admin-config.php" : [];

/* Stay closed unless the page is fully configured. */
if (empty($settings['password']) || empty($settings['mail_from'])) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This page is not configured.');
}

session_name('prodigal_admin');
session_set_cookie_params([
    'path' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function back(): void
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function database(string $apiDir): PDO
{
    $config = require "$apiDir/db-config.php";

    return new PDO(
        "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function personalize(string $text, ?string $fullName): string
{
    $name = trim((string) $fullName);
    $first = $name === '' ? 'friend' : preg_split('/\s+/u', $name)[0];

    return strtr($text, ['{name}' => $name === '' ? 'friend' : $name, '{first_name}' => $first]);
}

function sendReply(array $settings, string $to, string $subject, string $body): bool
{
    $from = $settings['mail_from'];
    $fromName = trim(str_replace(["\r", "\n", '"'], '', (string) ($settings['mail_from_name'] ?? '')));
    $replyTo = str_replace(["\r", "\n"], '', (string) ($settings['mail_reply_to'] ?? $from));

    $headers = [
        'From' => $fromName === '' ? $from : mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>",
        'Reply-To' => $replyTo,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];

    $subject = mb_encode_mimeheader(str_replace(["\r", "\n"], ' ', $subject), 'UTF-8');
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    return mail($to, $subject, $body, $headers, '-f' . $from);
}

$signedIn = !empty($_SESSION['signed_in']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('This form has expired. Go back, reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        $given = hash('sha256', (string) ($_POST['password'] ?? ''));
        if (hash_equals(hash('sha256', (string) $settings['password']), $given)) {
            session_regenerate_id(true);
            $_SESSION['signed_in'] = true;
        } else {
            sleep(2);
            flash('error', 'That password is not correct.');
        }
        back();
    }

    if (!$signedIn) {
        back();
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        back();
    }

    if ($action === 'send') {
        $ids = array_values(array_unique(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($ids === [] || $subject === '' || $message === '') {
            flash('error', 'Choose at least one person, and enter a subject and a message.');
            $_SESSION['draft'] = compact('ids', 'subject', 'message');
            back();
        }
        if (count($ids) > MAX_RECIPIENTS_PER_SEND) {
            flash('error', 'Please choose ' . MAX_RECIPIENTS_PER_SEND . ' people or fewer at a time.');
            $_SESSION['draft'] = compact('ids', 'subject', 'message');
            back();
        }

        try {
            $pdo = database($apiDir);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $select = $pdo->prepare("SELECT id, email, full_name FROM signups WHERE id IN ($marks)");
            $select->execute($ids);
            $mark = $pdo->prepare('UPDATE signups SET last_replied_at = NOW() WHERE id = ?');

            $sent = 0;
            $failed = [];
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $person) {
                $ok = sendReply(
                    $settings,
                    $person['email'],
                    personalize($subject, $person['full_name']),
                    personalize($message, $person['full_name'])
                );
                if ($ok) {
                    $mark->execute([$person['id']]);
                    $sent++;
                } else {
                    $failed[] = $person['email'];
                }
            }

            if ($failed === []) {
                flash('success', "Handed $sent " . ($sent === 1 ? 'message' : 'messages') . ' to the mail server.');
            } else {
                flash('error', "Handed $sent to the mail server. Could not send to: " . implode(', ', $failed));
                $_SESSION['draft'] = compact('ids', 'subject', 'message');
            }
        } catch (Throwable $error) {
            error_log('Admin send failed: ' . $error->getMessage());
            flash('error', 'Something went wrong, and sending stopped. Check the list before trying again.');
            $_SESSION['draft'] = compact('ids', 'subject', 'message');
        }
        back();
    }

    back();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$draft = $_SESSION['draft'] ?? ['ids' => [], 'subject' => '', 'message' => ''];
unset($_SESSION['draft']);

$people = [];
$loadError = false;
if ($signedIn) {
    try {
        $people = database($apiDir)
            ->query('SELECT id, email, full_name, created_at, last_replied_at FROM signups ORDER BY created_at DESC, id DESC')
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        error_log('Admin list failed: ' . $error->getMessage());
        $loadError = true;
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign-ups</title>
  <style>
    :root { --paper: #f6f3eb; --ink: #293126; --olive: #313b2b; --muted: #565f50; --rule: #cdcebd; --error: #8f2d20; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--paper); color: var(--ink); font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
    main { max-width: 1000px; margin: auto; padding: 32px 16px 64px; }
    h1 { font: 400 2.25rem/1.2 Georgia, serif; margin: 0; }
    h2 { font: 400 1.5rem/1.3 Georgia, serif; margin: 40px 0 8px; }
    .top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
    .count { color: var(--muted); margin: 4px 0 24px; }
    .notice { padding: 12px 16px; margin: 16px 0; border-left: 4px solid var(--olive); background: #e8eadf; overflow-wrap: anywhere; }
    .notice.error { border-color: var(--error); color: var(--error); background: #f4e4df; }
    .table-wrap { overflow-x: auto; border: 1px solid var(--rule); background: #fff; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--rule); white-space: nowrap; }
    tr:last-child td { border-bottom: 0; }
    th { font-size: .8125rem; color: var(--muted); font-weight: 600; }
    td.wrap { white-space: normal; overflow-wrap: anywhere; min-width: 160px; }
    label { display: block; font-weight: 600; margin: 16px 0 6px; }
    input[type=text], input[type=password], textarea { width: 100%; padding: 12px; font: inherit; color: inherit; background: #fff; border: 1px solid #8d9480; border-radius: 0; }
    textarea { min-height: 200px; resize: vertical; }
    input[type=checkbox] { width: 20px; height: 20px; accent-color: var(--olive); vertical-align: middle; }
    button { min-height: 44px; padding: 10px 20px; font: inherit; cursor: pointer; color: #fff; background: var(--olive); border: 0; }
    button:hover { background: #4e5b3d; }
    button.plain { color: var(--ink); background: transparent; border: 1px solid #8d9480; }
    button.plain:hover { background: #e8eadf; }
    .hint { color: var(--muted); font-size: .9375rem; margin: 6px 0 0; }
    .selected { font-weight: 600; margin: 16px 0 0; }
    .login { max-width: 400px; margin: 10vh auto 0; }
    .actions { margin-top: 20px; }
    :focus-visible { outline: 3px solid #a08a47; outline-offset: 2px; }
  </style>
</head>
<body>
<main>
<?php if ($flash): ?>
  <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$signedIn): ?>
  <form class="login" method="post" action="">
    <h1>Sign in</h1>
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="login">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
    <div class="actions"><button type="submit">Sign in</button></div>
  </form>
<?php else: ?>
  <div class="top">
    <h1>Sign-ups</h1>
    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="logout">
      <button class="plain" type="submit">Sign out</button>
    </form>
  </div>
  <p class="count"><strong id="total"><?= count($people) ?></strong> <?= count($people) === 1 ? 'person has' : 'people have' ?> signed up.</p>

  <?php if ($loadError): ?>
    <div class="notice error" role="alert">The list could not be loaded from the database.</div>
  <?php elseif ($people !== []): ?>
    <form method="post" action="" id="reply-form">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="send">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th scope="col"><input type="checkbox" id="select-all" aria-label="Select everyone"></th>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Signed up</th>
              <th scope="col">Last reply</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($people as $person): ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $person['id'] ?>" aria-label="Select <?= e($person['email']) ?>" <?= in_array((int) $person['id'], $draft['ids'], true) ? 'checked' : '' ?>></td>
              <td class="wrap"><?= $person['full_name'] === null ? '<span class="hint">Not given</span>' : e($person['full_name']) ?></td>
              <td class="wrap"><?= e($person['email']) ?></td>
              <td><?= e($person['created_at']) ?></td>
              <td><?= $person['last_replied_at'] === null ? '<span class="hint">None</span>' : e($person['last_replied_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2>Send a reply</h2>
      <p class="hint">Tick the people above. Each person gets their own separate email and can't see the others. Write <strong>{first_name}</strong> or <strong>{name}</strong> to insert each person's name. If they didn't give one, it becomes “friend”.</p>
      <p class="selected" id="selected-count" aria-live="polite"></p>

      <label for="subject">Subject</label>
      <input id="subject" name="subject" type="text" maxlength="200" value="<?= e($draft['subject']) ?>" required>

      <label for="message">Message</label>
      <textarea id="message" name="message" required><?= e($draft['message']) ?></textarea>

      <div class="actions"><button type="submit" id="send-button">Send</button></div>
    </form>
    <script>
      (function () {
        var form = document.getElementById("reply-form");
        var all = document.getElementById("select-all");
        var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="ids[]"]'));
        var label = document.getElementById("selected-count");

        function chosen() {
          return boxes.filter(function (box) { return box.checked; }).length;
        }
        function update() {
          var count = chosen();
          label.textContent = count === 1 ? "1 person selected" : count + " people selected";
          all.checked = count === boxes.length;
        }

        boxes.forEach(function (box) { box.addEventListener("change", update); });
        all.addEventListener("change", function () {
          boxes.forEach(function (box) { box.checked = all.checked; });
          update();
        });
        form.addEventListener("submit", function (event) {
          var count = chosen();
          if (count === 0) {
            event.preventDefault();
            label.textContent = "Choose at least one person first.";
            return;
          }
          document.getElementById("send-button").disabled = true;
        });
        update();
      })();
    </script>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
