const form = document.getElementById('form');
const msg  = document.getElementById('msg');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

function show(type, text){
  msg.className = 'alert alert-'+type;
  msg.textContent = text;
  msg.classList.remove('d-none');
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.classList.add('d-none');

  const fd = new FormData(form);
  const payload = Object.fromEntries(fd.entries());

  if (!payload.email || !payload.password) {
    show('warning','Please enter email and password.');
    return;
  }

  try {
    const res = await fetch('/api/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });

    const data = await res.json().catch(() => ({}));
    console.error('login response', { status: res.status, data });

    if (!res.ok || !data.ok) {
      if (data?.error === 'csrf')      return show('danger','Session expired. Refresh and try again.');
      if (data?.error === 'invalid')   return show('danger','Wrong email or password.');
      if (data?.error === 'validation')return show('warning','Please check your inputs.');
      return show('danger','Login failed.');
    }

    show('success','Logged in! Redirecting…');
    setTimeout(()=>location.href='/my_rides.php', 600);
  } catch (err) {
    console.error('fetch failed', err);
    show('danger','Network error. Please try again.');
  }
});