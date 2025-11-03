<?php
declare(strict_types=1);

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
         // IMPORTANT: omit 'domain' so cookie is host-only (works on glitchahitch.com, ahitch.org, etc.)
        // 'domain' => '',  // <— DO NOT set
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1'); 
    session_name('gh_sess');
    session_start();
}
?> 