<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

\App\Auth\require_admin();
header('Location: /admin/index.php', true, 302);
exit;
