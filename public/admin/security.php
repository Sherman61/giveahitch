<?php declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../config/db.php';

$admin = \App\Auth\require_admin();
$pdo = db();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$summary = [
    'open_reports' => 0,
    'failed_logins_24h' => 0,
    'security_events_24h' => 0,
    'app_errors_24h' => 0,
];

try {
    $summary['open_reports'] = (int)$pdo->query("SELECT COUNT(*) FROM ride_reports WHERE status IN ('open','reviewing')")->fetchColumn();
} catch (\Throwable $e) {
    $summary['open_reports'] = 0;
}
try {
    $summary['failed_logins_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM security_events WHERE event_key = 'login_failed' AND created_at >= (NOW() - INTERVAL 1 DAY)")->fetchColumn();
    $summary['security_events_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM security_events WHERE created_at >= (NOW() - INTERVAL 1 DAY)")->fetchColumn();
} catch (\Throwable $e) {
    $summary['failed_logins_24h'] = 0;
    $summary['security_events_24h'] = 0;
}
try {
    $summary['app_errors_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM app_errors WHERE created_at >= (NOW() - INTERVAL 1 DAY)")->fetchColumn();
} catch (\Throwable $e) {
    $summary['app_errors_24h'] = 0;
}

$securityEvents = [];
try {
    $stmt = $pdo->query("
        SELECT se.*, actor.display_name AS actor_display_name, target.display_name AS target_display_name
        FROM security_events AS se
        LEFT JOIN users AS actor ON actor.id = se.actor_user_id
        LEFT JOIN users AS target ON target.id = se.target_user_id
        ORDER BY se.created_at DESC
        LIMIT 80
    ");
    $securityEvents = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (\Throwable $e) {
    $securityEvents = [];
}

$appErrors = [];
try {
    $stmt = $pdo->query("
        SELECT id, page, message, severity, user_id, created_at
        FROM app_errors
        ORDER BY created_at DESC
        LIMIT 40
    ");
    $appErrors = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (\Throwable $e) {
    $appErrors = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Security Dashboard · Glitch a Hitch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8f9fb; }
    .card { border: 0; border-radius: 1rem; }
    .metric-value { font-size: 2rem; font-weight: 700; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  </style>
</head>
<body class="py-4">
  <div class="container">
    <header class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Security Dashboard</h1>
        <p class="text-secondary mb-0">Recent security-sensitive activity, moderation pressure, and application errors for admins.</p>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center flex-wrap justify-content-end">
        <span class="badge text-bg-light text-secondary">Signed in as <?= h($admin['display_name'] ?? $admin['email'] ?? 'Admin') ?></span>
        <a class="btn btn-outline-primary btn-sm" href="/admin/index.php"><i class="bi bi-grid me-1"></i>Admin home</a>
        <a class="btn btn-outline-danger btn-sm" href="/admin/reports.php"><i class="bi bi-flag me-1"></i>Reports</a>
      </div>
    </header>

    <section class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-secondary small text-uppercase">Open reports</div>
          <div class="metric-value text-danger"><?= (int)$summary['open_reports'] ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-secondary small text-uppercase">Failed logins, 24h</div>
          <div class="metric-value text-warning"><?= (int)$summary['failed_logins_24h'] ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-secondary small text-uppercase">Security events, 24h</div>
          <div class="metric-value text-primary"><?= (int)$summary['security_events_24h'] ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-secondary small text-uppercase">App errors, 24h</div>
          <div class="metric-value text-dark"><?= (int)$summary['app_errors_24h'] ?></div>
        </div></div>
      </div>
    </section>

    <section class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h2 class="h5 mb-1">Recent Security Events</h2>
            <p class="text-secondary mb-0">Failed logins, admin actions, and other security-sensitive events.</p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>When</th>
                <th>Event</th>
                <th>Severity</th>
                <th>Actor</th>
                <th>Target</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$securityEvents): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">No security events logged yet.</td></tr>
              <?php else: ?>
                <?php foreach ($securityEvents as $event): ?>
                  <tr>
                    <td class="small text-secondary"><?= h($event['created_at']) ?></td>
                    <td class="mono"><?= h($event['event_key']) ?></td>
                    <td><span class="badge text-bg-light"><?= h($event['severity']) ?></span></td>
                    <td>
                      <?php if (!empty($event['actor_user_id'])): ?>
                        <a href="/user.php?id=<?= (int)$event['actor_user_id'] ?>"><?= h($event['actor_display_name'] ?: ('User #' . (int)$event['actor_user_id'])) ?></a>
                      <?php else: ?>
                        <span class="text-secondary">System</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($event['target_user_id'])): ?>
                        <a href="/user.php?id=<?= (int)$event['target_user_id'] ?>"><?= h($event['target_display_name'] ?: ('User #' . (int)$event['target_user_id'])) ?></a>
                      <?php else: ?>
                        <span class="text-secondary">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?= h($event['details'] ?? '') ?></div>
                      <?php if (!empty($event['ip_address'])): ?><div class="small text-secondary">IP: <?= h($event['ip_address']) ?></div><?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h2 class="h5 mb-1">Recent Application Errors</h2>
            <p class="text-secondary mb-0">Latest entries from the app error logger for quick triage.</p>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="/error_tests.php"><i class="bi bi-bug me-1"></i>Open full error dashboard</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>When</th>
                <th>Page</th>
                <th>Severity</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$appErrors): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4">No application errors logged yet.</td></tr>
              <?php else: ?>
                <?php foreach ($appErrors as $error): ?>
                  <tr>
                    <td class="small text-secondary"><?= h($error['created_at']) ?></td>
                    <td class="mono"><?= h($error['page']) ?></td>
                    <td><span class="badge text-bg-light"><?= h($error['severity']) ?></span></td>
                    <td><?= h($error['message']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
