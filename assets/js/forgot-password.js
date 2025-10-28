const form = document.getElementById('forgot-form');
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
  if (!email) {
    show('warning', 'Please enter your email address.');
    return;
  }

  try {
    const res = await fetch('/api/password_reset_request.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ email }),
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.ok) {
      if (data?.error === 'validation') {
        show('warning', 'Please enter a valid email address.');
        return;
      }
      if (data?.error === 'mail') {
        show('danger', 'Unable to send the reset code. Please try again shortly.');
        return;
      }
      show('danger', 'Something went wrong. Please try again.');
      return;
    }

    show('success', 'If we found an account for that email, we sent a reset code. Check your inbox.');
    setTimeout(() => {
      const url = new URL('/reset-password.php', window.location.origin);
      url.searchParams.set('email', email);
      window.location.href = url.toString();
    }, 1500);
  } catch (err) {
    console.error('password_reset_request_failed', err);
    show('danger', 'Network error. Please try again.');
  }
});
