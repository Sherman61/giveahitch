// /assets/js/forgot-password.js
(() => {
  const form = document.getElementById('forgot-form');
  const msg  = document.getElementById('msg');
  if (!form || !msg) return;

  const show = (text, type = 'info') => {
    msg.className = `alert alert-${type}`;
    msg.textContent = text;
    msg.classList.remove('d-none');
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = new FormData(form);
    const email = (data.get('email') || '').toString().trim();
    const csrf  = (data.get('csrf')  || '').toString();

    if (!email) {
      show('Please enter your email.', 'warning');
      return;
    }

    try {
      show('Sending...', 'secondary');
      const resp = await fetch('/api/forgot_password_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ email, csrf })
      });
      const json = await resp.json().catch(() => ({}));

      if (!resp.ok || json.ok === false) {
        show(json.error || `Error ${resp.status}: Unable to send reset code.`, 'danger');
        return;
      }
      show('If that email exists, a reset code was sent.', 'success');
      // Optional: redirect to code entry page:
      // window.location.href = `/reset-code.php?email=${encodeURIComponent(email)}`;
    } catch (err) {
      show('Network error. Please try again.', 'danger');
    }
  });
})();
