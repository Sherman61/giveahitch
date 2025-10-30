<?php
declare(strict_types=1);

// deps
require __DIR__ . '/../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
  Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
}

use Symfony\Component\HttpClient\HttpClient;

// helpers
function wants_json(): bool
{
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  return stripos($accept, 'application/json') !== false || (($_POST['json'] ?? '') === '1');
}
function respond_json(array $payload, int $code = 200): never
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

$status = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $to = trim((string) ($_POST['to'] ?? ''));
  $from = trim((string) ($_POST['from'] ?? ''));
  $subject = trim((string) ($_POST['subject'] ?? 'Test message'));
  $message = trim((string) ($_POST['message'] ?? 'This is a test message from GlitchaHitch.'));

  if ($to === '') {
    $payload = ['ok' => false, 'error' => 'Recipient address (to) is required.'];
    wants_json() ? respond_json($payload, 422) : $status = $payload;
  } else {
    $token = trim((string) ($_ENV['MAILTRAP_TOKEN'] ?? '9fcc8dcdd3dc9b4303cd9a60ce80bb29'));
    if ($token === '') {
      $payload = ['ok' => false, 'error' => 'MAILTRAP_TOKEN missing in .env'];
      wants_json() ? respond_json($payload, 500) : $status = $payload;
    } else {
      if ($from === '')
        $from = $_ENV['TEST_EMAIL_FROM'] ?? 'hello@glitchahitch.com';
      $client = HttpClient::create();
      try {
        $r = $client->request('POST', 'https://send.api.mailtrap.io/api/send', [
          'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'from' => ['email' => $from, 'name' => 'GlitchaHitch'],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'text' => $message,
          ],
          'timeout' => 15,
        ]);

        $http = $r->getStatusCode();
        $body = $r->getContent(false);
        $ok = $http >= 200 && $http < 300;

        $payload = ['ok' => $ok, 'http' => $http, 'to' => $to, 'from' => $from];
        if (!$ok)
          $payload['provider'] = $body;

        wants_json() ? respond_json($payload, $ok ? 200 : 502) : $status = $payload;
      } catch (Throwable $e) {
        $payload = ['ok' => false, 'error' => 'HTTP API send failed: ' . $e->getMessage()];
        wants_json() ? respond_json($payload, 502) : $status = $payload;
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Send Test Email</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="h4 mb-4">Send a test email</h1>

            <?php if ($status !== null): ?>
              <div class="alert <?= !empty($status['ok']) ? 'alert-success' : 'alert-danger' ?>">
                <?= !empty($status['ok'])
                  ? 'Email request sent to provider.'
                  : htmlspecialchars($status['error'] ?? ($status['provider'] ?? 'Unknown error'), ENT_QUOTES) ?>
              </div>
            <?php endif; ?>

            <form method="post" class="vstack gap-3">
              <div>
                <label class="form-label">To</label>
                <input type="email" class="form-control" name="to" placeholder="shermanshiya@gmail.com" required
                  value="<?= htmlspecialchars($_POST['to'] ?? '', ENT_QUOTES) ?>">
              </div>
              <div>
                <label class="form-label">From (optional)</label>
                <input type="email" class="form-control" name="from" placeholder="hello@glitchahitch.com"
                  value="<?= htmlspecialchars($_POST['from'] ?? '', ENT_QUOTES) ?>">
              </div>
              <div>
                <label class="form-label">Subject</label>
                <input type="text" class="form-control" name="subject"
                  value="<?= htmlspecialchars($_POST['subject'] ?? 'Test message', ENT_QUOTES) ?>" required>
              </div>
              <div>
                <label class="form-label">Message</label>
                <textarea class="form-control" name="message"
                  rows="4"><?= htmlspecialchars($_POST['message'] ?? 'This is a test message from GlitchaHitch.', ENT_QUOTES) ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Send test email</button>
            </form>
            <p class="text-muted small mt-3">
              This uses Mailtrap’s <code>/api/send</code> over HTTPS (port 443). Add
              <code>Accept: application/json</code> to get JSON.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>