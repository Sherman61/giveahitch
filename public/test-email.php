<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = isset($_POST['to']) ? trim($_POST['to']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : 'Test message';
    $message = isset($_POST['message']) ? trim($_POST['message']) : "This is a test message from Glitch a Hitch.";
    $from = isset($_POST['from']) ? trim($_POST['from']) : '';

    if ($to === '') {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Recipient address (to) is required.']);
        exit;
    }

    $headers = [];
    if ($from !== '') {
        $headers[] = 'From: ' . $from;
    }
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    $success = mail($to, $subject, $message, implode("\r\n", $headers));

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => $success,
        'to' => $to,
        'subject' => $subject,
        'from' => $from,
    ]);
    exit;
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
          <form method="post" class="vstack gap-3">
            <div>
              <label class="form-label">To</label>
              <input type="email" class="form-control" name="to" placeholder="you@example.com" required>
            </div>
            <div>
              <label class="form-label">From (optional)</label>
              <input type="email" class="form-control" name="from" placeholder="no-reply@example.com">
            </div>
            <div>
              <label class="form-label">Subject</label>
              <input type="text" class="form-control" name="subject" value="Test message" required>
            </div>
            <div>
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="4">This is a test message from Glitch a Hitch.</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send test email</button>
          </form>
          <p class="text-muted small mt-3">POSTing to this endpoint returns JSON with the delivery status.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
