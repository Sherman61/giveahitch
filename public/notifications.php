<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/ws.php';

\App\Auth\start_secure_session();
$me = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
$pdo = db();

$initial = \App\Notifications\list_recent($pdo, (int)$me['id'], 20);
$unread = \App\Notifications\unread_count($pdo, (int)$me['id']);
$settings = \App\Notifications\get_settings($pdo, (int)$me['id']);
$wsToken = \App\WS\generate_token((int)$me['id']);

$bootstrap = [
    'items' => $initial['items'],
    'has_more' => $initial['has_more'],
    'next_cursor' => $initial['next_cursor'],
    'unread_count' => $unread,
    'settings' => $settings,
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications — Glitch A Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body {
      background: linear-gradient(180deg, #f8faff 0%, #eef2ff 100%);
    }
    .notification-card {
      border-radius: 1rem;
      border: 0;
      background: #fff;
      box-shadow: 0 10px 30px rgba(15, 64, 128, 0.08);
    }
    .notification-item {
      border-radius: 0.75rem;
      border: 1px solid rgba(13, 110, 253, 0.15);
      padding: 1rem;
      background: rgba(255, 255, 255, 0.9);
      transition: box-shadow 0.2s ease;
    }
    .notification-item.unread {
      border-color: rgba(13, 110, 253, 0.35);
      background: rgba(13, 110, 253, 0.08);
    }
    .notification-item:hover {
      box-shadow: 0 12px 24px rgba(15, 64, 128, 0.12);
    }
    .notification-meta {
      font-size: 0.85rem;
      color: #6c757d;
    }
    .settings-card {
      border-radius: 1rem;
      border: 0;
      box-shadow: 0 10px 30px rgba(15, 64, 128, 0.08);
      background: #fff;
    }
  </style>
  <script>
    window.ME_USER_ID = <?= (int)$me['id'] ?>;
    window.CSRF_TOKEN = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    window.API_BASE = '/api';
    window.WS_URL = <?= json_encode($_ENV['WS_URL'] ?? '') ?>;
    window.WS_AUTH = <?= json_encode($wsToken ? ['userId' => (int)$me['id'], 'token' => $wsToken] : null, JSON_UNESCAPED_SLASHES) ?>;
    window.NOTIFICATIONS_BOOTSTRAP = <?= json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
</head>
<body class="pb-5">
  <?php include __DIR__ . '/header.php'; ?>
  <div class="container py-4">
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="notification-card card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h1 class="h4 mb-0">Notifications</h1>
                <div class="text-secondary small" id="notificationSubtitle">Stay up to date with your rides.</div>
              </div>
              <button class="btn btn-outline-primary btn-sm" id="markAllRead" type="button">
                <i class="bi bi-check2-circle me-1"></i>Mark all as read
              </button>
            </div>
            <div id="notificationList" class="vstack gap-3"></div>
            <div id="notificationEmpty" class="text-center text-secondary py-5 d-none">
              <i class="bi bi-bell-fill fs-1 text-primary d-block mb-3"></i>
              <p class="lead mb-1">You're all caught up!</p>
              <p class="small mb-0">We'll let you know when there's something new to review.</p>
            </div>
            <button class="btn btn-outline-secondary w-100 mt-4 d-none" id="loadMore" type="button">
              <i class="bi bi-chevron-down me-1"></i>Load older notifications
            </button>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="settings-card card">
          <div class="card-body">
            <h2 class="h5 mb-3">Notification settings</h2>
            <form id="notificationSettings" class="vstack gap-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="settingRideActivity" name="ride_activity">
                <label class="form-check-label" for="settingRideActivity">
                  Updates about rides you posted
                </label>
                <div class="form-text">Get notified when someone requests or leaves one of your rides.</div>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="settingMatchActivity" name="match_activity">
                <label class="form-check-label" for="settingMatchActivity">
                  Progress on rides you're part of
                </label>
                <div class="form-text">Stay in the loop as confirmed rides move forward.</div>
              </div>
            </form>
            <div id="settingsSaved" class="small text-success mt-3 d-none">
              Preferences updated.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
  <script type="module" src="/assets/js/notifications.js"></script>
</body>
</html>
