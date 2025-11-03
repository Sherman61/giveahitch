<?php
declare(strict_types=1);
$email = isset($_GET['email']) ? (string) $_GET['email'] : '';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Set new password - GlitchaHitch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5" style="max-width:520px">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Set a new password</h1>
                <div id="msg" class="d-none alert" role="alert"></div>
                <form id="pw-form" class="vstack gap-3">
                    <div>
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" id="email" required
                            value="<?= htmlspecialchars($email, ENT_QUOTES) ?>">
                    </div>
                    <div>
                        <label class="form-label">New password</label>
                        <input class="form-control" type="password" id="password" minlength="8" required>
                    </div>
                    <div>
                        <label class="form-label">Confirm password</label>
                        <input class="form-control" type="password" id="password2" minlength="8" required>
                    </div>
                    <button class="btn btn-primary w-100">Update password</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        const f = document.getElementById('pw-form');
        const msg = document.getElementById('msg');
        const show = (t, type = 'danger') => { msg.textContent = t; msg.className = 'alert alert-' + type; msg.classList.remove('d-none'); };
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = sessionStorage.getItem('reset_token') || '';
            const email = document.getElementById('email').value.trim();
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('password2').value;
            if (p1 !== p2) { show('Passwords do not match'); return; }
            try {
                const r = await fetch('/api/forgot_password_reset.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ reset_token: token, email, password: p1 })
                });
                const j = await r.json();
                if (!r.ok || !j.ok) { show(j.error || 'Reset failed'); return; }
                show('Password updated. You can log in now.', 'success');
                setTimeout(() => { window.location.href = '/login.php?email=' + encodeURIComponent(email); }, 1500);
            } catch (_) { show('Network error'); }
        });
    </script>
</body>

</html>