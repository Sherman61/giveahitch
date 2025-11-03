const form = document.getElementById('form');
const msg  = document.getElementById('msg');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

const STORAGE_KEY_ACCEPT_INTENT = 'ga_accept_ride_intent_v1';
const ACCEPT_INTENT_TTL = 24 * 60 * 60 * 1000;

const getStorage = () => {
  try {
    return window.localStorage || null;
  } catch (err) {
    console.warn('login:storage_unavailable', err);
    return null;
  }
};

const storage = getStorage();

const rememberAcceptIntent = (rideId) => {
  if (!storage || !rideId) return;
  try {
    storage.setItem(STORAGE_KEY_ACCEPT_INTENT, JSON.stringify({ rideId, ts: Date.now() }));
  } catch (err) {
    console.warn('login:remember_intent_failed', err);
  }
};

const readAcceptIntent = () => {
  if (!storage) return null;
  try {
    const raw = storage.getItem(STORAGE_KEY_ACCEPT_INTENT);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    const rideId = Number(parsed?.rideId || 0);
    const ts = Number(parsed?.ts || 0);
    if (!rideId) {
      storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
      return null;
    }
    if (ts && Date.now() - ts > ACCEPT_INTENT_TTL) {
      storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
      return null;
    }
    return { rideId, ts };
  } catch (err) {
    console.warn('login:read_intent_failed', err);
    storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
    return null;
  }
};

function show(type, text){
  msg.className = 'alert alert-'+type;
  msg.textContent = text;
  msg.classList.remove('d-none');
}

const params = new URLSearchParams(location.search);
const acceptRideId = Number(params.get('acceptRide') || params.get('accept') || 0) || 0;
if (acceptRideId) {
  rememberAcceptIntent(acceptRideId);
  show('info', 'Log in to accept your selected ride.');
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

    if (!res.ok || !data.ok) {
      if (data?.error === 'csrf')      return show('danger','Session expired. Refresh and try again.');
      if (data?.error === 'invalid')   return show('danger','Wrong email or password.');
      if (data?.error === 'validation')return show('warning','Please check your inputs.');
      return show('danger','Login failed.');
    }

    show('success','Logged in! Redirecting…');
    const intent = readAcceptIntent();
    const target = intent?.rideId
      ? (() => {
          const url = new URL('/rides.php', location.origin);
          url.searchParams.set('acceptRide', String(intent.rideId));
          return url.toString();
        })()
      : '/rides.php';

    setTimeout(()=>{ location.href = target; }, 600);
  } catch (err) {
    console.error('fetch failed', err);
    show('danger','Network error. Please try again.');
  }
});
