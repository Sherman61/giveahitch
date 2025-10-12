<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ws.php';

\start_secure_session();
$me = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
$wsToken = \App\WS\generate_token((int)($me['id'] ?? 0));
$initialTarget = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Messages — Glitch A Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body {
      background: linear-gradient(180deg, #f8faff 0%, #eef2ff 100%);
    }
    .chat-layout {
      min-height: calc(100vh - 140px);
    }
    .conversation-list {
      max-height: calc(100vh - 220px);
      overflow-y: auto;
    }
    .conversation-item {
      cursor: pointer;
      transition: background-color 0.2s ease;
    }
    .conversation-item.active {
      background-color: rgba(13, 110, 253, 0.15);
    }
    .conversation-item:hover {
      background-color: rgba(13, 110, 253, 0.08);
    }
    .chat-card {
      min-height: calc(100vh - 220px);
    }
    .messages-area {
      background: #f7f9fc;
      border-radius: 0.75rem;
      padding: 1rem;
      overflow-y: auto;
      flex: 1;
    }
    .message-row {
      display: flex;
      flex-direction: column;
    }
    .message-bubble {
      display: inline-block;
      padding: 0.75rem 1rem;
      border-radius: 1rem;
      max-width: 80%;
      box-shadow: 0 4px 12px rgba(15, 64, 128, 0.08);
      background: #fff;
    }
    .message-bubble.me {
      margin-left: auto;
      background: #0d6efd;
      color: #fff;
    }
    .message-meta {
      font-size: 0.75rem;
      color: #6c757d;
      margin-top: 0.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .message-meta .message-meta-sep {
      color: rgba(0, 0, 0, 0.25);
      font-size: 0.6rem;
    }
    .message-meta .message-status {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }
    .message-meta .message-status i {
      font-size: 1rem;
    }
    .message-meta .message-status.pending i {
      color: #6c757d;
      opacity: 0.75;
    }
    .message-meta .message-status.delivered i {
      color: #6c757d;
    }
    .message-meta .message-status.seen i {
      color: #0d6efd;
    }
    .typing-indicator {
      margin-top: 0.5rem;
      color: #0d6efd;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .typing-indicator::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background-color: currentColor;
      animation: typing-pulse 1s ease-in-out infinite;
    }
    @keyframes typing-pulse {
      0% { opacity: 0.25; transform: scale(0.85); }
      50% { opacity: 1; transform: scale(1); }
      100% { opacity: 0.25; transform: scale(0.85); }
    }
    .messages-empty {
      color: #6c757d;
      text-align: center;
      margin-top: 3rem;
    }
  </style>
  <script>
    window.ME_USER_ID = <?= (int)$me['id'] ?>;
    window.CSRF_TOKEN = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    window.API_BASE = '/api';
    window.WS_URL = <?= json_encode($_ENV['WS_URL'] ?? '') ?>;
    window.WS_AUTH = <?= json_encode($wsToken ? ['userId' => (int)$me['id'], 'token' => $wsToken] : null, JSON_UNESCAPED_SLASHES) ?>;
    window.INITIAL_TARGET_ID = <?= $initialTarget ? (int)$initialTarget : 'null' ?>;
  </script>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<div class="container py-4 chat-layout">
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h5 mb-0">Conversations</h1>
            <button class="btn btn-link btn-sm" id="refreshThreads" type="button"><i class="bi bi-arrow-repeat"></i><span class="ms-1">Refresh</span></button>
          </div>
          <div id="threadsEmpty" class="text-secondary text-center my-4 d-none">No conversations yet. Start one from a rider’s profile.</div>
          <div id="threadsList" class="list-group conversation-list"></div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card shadow-sm chat-card h-100">
        <div class="card-body d-flex flex-column">
          <div id="chatHeader" class="mb-3">
            <h2 class="h5 mb-0">Select a conversation</h2>
            <div class="text-secondary small" id="chatStatus">Pick someone from the left to start messaging.</div>
          </div>
          <div id="messagesArea" class="messages-area d-flex flex-column">
            <div id="messagesEmpty" class="messages-empty">No messages yet.</div>
            <div id="messagesList" class="vstack gap-3"></div>
            <div id="typingIndicator" class="typing-indicator d-none" aria-live="polite"></div>
          </div>
          <form id="messageForm" class="mt-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <div class="input-group">
              <textarea id="messageInput" class="form-control" rows="2" placeholder="Type a message" maxlength="2000" required></textarea>
              <button class="btn btn-primary" type="submit"><i class="bi bi-send-fill"></i><span class="d-none d-md-inline ms-1">Send</span></button>
            </div>
            <div id="messageFormAlert" class="small text-danger mt-2 d-none"></div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
<script type="module" src="/assets/js/messages.js"></script>
</body>
</html>
