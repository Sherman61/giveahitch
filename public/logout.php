<?php declare(strict_types=1);
require_once __DIR__.'/../lib/auth.php';
\App\Auth\logout();
header("Location: /glitchahitch/login.php");
exit;

