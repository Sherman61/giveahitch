const form = document.getElementById('reset-form');
const msg  = document.getElementById('msg');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

function show(type, text) {
  msg.className = `alert alert-${type}`;
  msg.textContent = text;
  msg.classList.remove('d-none');
}

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  msg.classList.add('d-none');

  const fd = new FormData(form);
  const email = (fd.get('email') || '').toString().trim();
  const code = (fd.get('code') || '').toString().trim();
  const password = (fd.get('password') || '').toString();
  const confirm = (fd.get('confirm') || '').toString();

  if (!email || !code || !password || !confirm) {
    show('warning', 'Please complete all fields.');
    return;
  }

  if (code.length !== 6 || !/^\d{6}$/.test(code)) {
    show('warning', 'Reset codes are six digits.');
    return;
  }

  if (password.length < 8) {
    show('warning', 'Passwords must be at least 8 characters long.');
    return;
  }

  if (password !== confirm) {
    show('warning', 'Passwords do not match.');
    return;
  }

  try {
    const res = await fetch('/api/password_reset_confirm.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ email, code, password }),
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.ok) {
      if (data?.error === 'validation') {
        show('warning', 'Please check your inputs and try again.');
        return;
      }
      if (data?.error === 'invalid_code') {
        show('danger', 'That code is not correct.');
        return;
      }
      if (data?.error === 'expired') {
        show('warning', 'Your reset code expired. Request a new one.');
        return;
      }
      show('danger', 'Unable to reset your password. Please try again.');
      return;
    }

    show('success', 'Password updated! Redirecting to login…');
    setTimeout(() => {
      window.location.href = '/login.php';
    }, 1500);
  } catch (err) {
    console.error('password_reset_confirm_failed', err);
    show('danger', 'Network error. Please try again.');
  }
});
