<?php
declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
}
use Symfony\Component\HttpClient\HttpClient;

function j($p, $c = 200)
{
    http_response_code($c);
    header('Content-Type: application/json');
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$status = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim((string) ($_POST['to'] ?? ''));
    $from = trim((string) ($_POST['from'] ?? '')) ?: ($_ENV['TEST_EMAIL_FROM'] ?? 'hello@glitchahitch.com');
    $subject = trim((string) ($_POST['subject'] ?? 'Test message'));
    $message = trim((string) ($_POST['message'] ?? 'This is a test message from GlitchaHitch.'));
    if ($to === '') {
        $status = ['ok' => false, 'error' => 'Recipient address (to) is required.'];
    } else {
        $token = trim((string) ($_ENV['MAILTRAP_TOKEN'] ?? ''));
        // HARD-CODE HERE (temp) if needed to prove it: $token = 'YOUR_SENDING_API_TOKEN';
        if ($token === '') {
            $status = ['ok' => false, 'error' => '.env MAILTRAP_TOKEN missing'];
        } else {
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
                $status = ['ok' => $ok, 'http' => $http, 'to' => $to, 'from' => $from];
                if (!$ok) {
                    $status['provider'] = $body;
                }
                // DEBUG (remove later): show token suffix to ensure match with curl
                $status['token_suffix'] = substr($token, -4);
            } catch (Throwable $e) {
                $status = ['ok' => false, 'error' => 'HTTP API send failed: ' . $e->getMessage()];
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
    <meta name="viewport" content="width=device-width,initial-scale=1">
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
                                <div><strong><?= !empty($status['ok']) ? 'Success' : 'Failed' ?></strong></div>
                                <div><?= htmlspecialchars($status['error'] ?? ($status['provider'] ?? ''), ENT_QUOTES) ?>
                                </div>
                                <div class="small text-muted mt-1">
                                    http: <?= htmlspecialchars((string) ($status['http'] ?? ''), ENT_QUOTES) ?>
                                    &nbsp; token_suffix:
                                    <?= htmlspecialchars((string) ($status['token_suffix'] ?? ''), ENT_QUOTES) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="vstack gap-3">
                            <div><label class="form-label">To</label>
                                <input type="email" class="form-control" name="to" placeholder="shermanshiya@gmail.com"
                                    required value="<?= htmlspecialchars($_POST['to'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div><label class="form-label">From (optional)</label>
                                <input type="email" class="form-control" name="from"
                                    placeholder="hello@glitchahitch.com"
                                    value="<?= htmlspecialchars($_POST['from'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div><label class="form-label">Subject</label>
                                <input type="text" class="form-control" name="subject"
                                    value="<?= htmlspecialchars($_POST['subject'] ?? 'Test message', ENT_QUOTES) ?>"
                                    required>
                            </div>
                            <div><label class="form-label">Message</label>
                                <textarea class="form-control" name="message"
                                    rows="4"><?= htmlspecialchars($_POST['message'] ?? 'This is a test message from GlitchaHitch.', ENT_QUOTES) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send test email</button>
                        </form>
                        <p class="text-muted small mt-3">Uses Mailtrap HTTP API (port 443). If it fails with
                            “Unauthorized”, the token is wrong/not a Sending API token.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>