<?php
declare(strict_types=1);
$email = isset($_GET['email']) ? (string) $_GET['email'] : '';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Enter reset code - GlitchaHitch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5" style="max-width:520px">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Enter your 6-digit code</h1>
                <p class="text-secondary">We sent a code to your email.</p>

                <div id="msg" class="d-none alert" role="alert"></div>

                <form id="verify-form" class="vstack gap-3">
                    <div>
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" id="email" required
                            value="<?= htmlspecialchars($email, ENT_QUOTES) ?>">
                    </div>
                    <div>
                        <label class="form-label">Code</label>
                        <input class="form-control" type="text" id="code" inputmode="numeric" pattern="\d{6}"
                            maxlength="6" required>
                    </div>
                    <button class="btn btn-primary w-100">Verify code</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        const f = document.getElementById('verify-form');
        const msg = document.getElementById('msg');
        const show = (t, type = 'danger') => { msg.textContent = t; msg.className = 'alert alert-' + type; msg.classList.remove('d-none'); };
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            const code = document.getElementById('code').value.trim();
            try {
                const r = await fetch('/api/forgot_password_verify.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email, code })
                });
                const j = await r.json();
                if (!r.ok || !j.ok) { show(j.error || 'Invalid code'); return; }
                // carry the short-lived token forward
                sessionStorage.setItem('reset_token', j.reset_token);
                window.location.href = '/set-new-password.php?email=' + encodeURIComponent(email);
            } catch (_) { show('Network error'); }
        });
    </script>
</body>

</html>