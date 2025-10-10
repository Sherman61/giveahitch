import { logError } from '../utils/logger.js';

// /assets/js/components/driver.js
const esc = s => s ? String(s).replace(/[&<>"']/g, m => (
  {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]
)) : '';

const badge = status => {
  const map = {pending:'warning',accepted:'primary',matched:'primary',confirmed:'primary',in_progress:'info',completed:'success',rejected:'dark',cancelled:'dark'};
  const cls = map[status] || 'light';
  return `<span class="badge text-bg-${cls}">${esc(status || 'pending')}</span>`;
};

function friendlyStatusError(err){
  const code = err?.message || '';
  if (code === 'illegal_transition') {
    return 'This ride was updated somewhere else. Refresh to get the latest status before trying again.';
  }
  if (code === 'no_active_match') {
    return 'This match is no longer active, so the action could not be completed.';
  }
  return '';
}

function handleActionError(tag, err, fallback, context){
  const message = friendlyStatusError(err) || fallback;
  logError(tag, err, context);
  alert(message);
}

const contactHtml = (phone, whatsapp) => {
  const parts = [];
  if (phone) parts.push(`<a class="me-2" href="tel:${encodeURIComponent(phone)}">${esc(phone)}</a>`);
  if (whatsapp){
    const wa = String(whatsapp).replace(/\D+/g,'');
    if (wa) parts.push(`<a target="_blank" rel="noopener" href="https://wa.me/${wa}">WhatsApp</a>`);
  }
  return parts.join(' ');
};

function respondedScenario(m, amDriver, otherName){
  const status = m.match_status;
  let heading = amDriver ? 'You offered to drive' : 'You asked for a ride';
  let detail = '';
  let cta = '';

  if (status === 'pending') {
    detail = amDriver
      ? `${otherName} still needs to accept your offer.`
      : `${otherName} is reviewing your request.`;
    cta = 'You can withdraw if your plans changed.';
  } else if (status === 'accepted') {
    detail = amDriver
      ? `${otherName} accepted your offer. The ride owner will confirm once everything is set.`
      : `You're accepted by ${otherName}. Watch for confirmation from the ride owner.`;
  } else if (status === 'matched' || status === 'confirmed') {
    detail = amDriver
      ? `You're confirmed to drive ${otherName}. Coordinate timing and start the trip together.`
      : `${otherName} is confirmed to drive you. Coordinate meetup details before departure.`;
    cta = 'Mark the ride complete once the trip wraps up.';
  } else if (status === 'in_progress') {
    detail = amDriver
      ? `You're currently driving ${otherName}.`
      : `${otherName} is currently driving you.`;
    cta = 'Complete the ride when the trip ends.';
  } else if (status === 'completed') {
    detail = amDriver
      ? `Trip finished with ${otherName}.`
      : `Trip finished with ${otherName}.`;
    cta = m.already_rated ? 'Thanks for sharing your rating.' : 'Remember to leave a rating.';
  } else if (status === 'rejected' || status === 'cancelled') {
    detail = 'This match is no longer active.';
  } else {
    detail = `Current status: ${esc(status)}.`;
  }

  return { heading, detail, cta };
}

async function postJSON(url, body){
  const res = await fetch(url, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials:'same-origin',
    body: JSON.stringify({...body, csrf: window.CSRF})
  });
  const j = await res.json().catch(()=>({ok:false}));
  if(!res.ok || !j.ok) {
    const err = new Error(j.error||'request_failed');
    logError('driver:post_json_failed', err, { url, body });
    throw err;
  }
  return j;
}

function cardForResponded(m, onRefresh){
  const dt = m.ride_datetime ? new Date(m.ride_datetime.replace(' ','T')+'Z').toLocaleString() : 'Any time';
  const meId   = Number(window.MY_ID || 0);
  const meName = (window.ME_NAME && String(window.ME_NAME).trim()) || 'You';

  const amDriver = Number(m.driver_user_id) === meId;
  const driverLabel    = amDriver ? `${meName} (You)` : esc(m.other_display_driver || 'Driver');
  const passengerLabel = amDriver ? esc(m.other_display_passenger || 'Passenger') : `${meName} (You)`;

  // Only show the other party’s contact
  const otherPhone    = amDriver ? m.passenger_phone    : m.driver_phone;
  const otherWhatsApp = amDriver ? m.passenger_whatsapp : m.driver_whatsapp;

  const otherName = amDriver ? (m.other_display_passenger || 'your passenger') : (m.other_display_driver || 'your driver');
  const scenario = respondedScenario(m, amDriver, esc(otherName));

  const card = document.createElement('div');
  card.className = 'card shadow-sm mb-2';
  card.innerHTML = `
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
        <div class="me-md-3 flex-grow-1">
          <div class="fw-semibold">${m.type==='offer' ? '🚗 Offer' : '🙋 Request'} — ${esc(m.from_text)} → ${esc(m.to_text)}</div>
          <div class="border rounded-3 bg-body-tertiary p-3 mt-3">
            <div class="fw-semibold text-primary">${esc(scenario.heading)}</div>
            <div class="text-secondary small mt-1">${scenario.detail}</div>
            ${scenario.cta ? `<div class="text-secondary small mt-2">${scenario.cta}</div>` : ''}
          </div>
          <div class="mt-3">
            <div class="d-flex align-items-center gap-2"><span class="badge text-bg-primary">Driver</span><span>${driverLabel}</span></div>
            <div class="d-flex align-items-center gap-2 mt-1"><span class="badge text-bg-success">Passenger</span><span>${passengerLabel}</span></div>
            ${ (otherPhone||otherWhatsApp) ? `<div class="mt-2"><strong>Contact:</strong> ${contactHtml(otherPhone, otherWhatsApp)}</div>` : '' }
          </div>
        </div>
        <div class="text-end">
          ${badge(m.match_status)}
          <div><span class="badge text-bg-light">${esc(dt)}</span></div>
          <div id="act-${m.match_id}" class="mt-2"></div>
        </div>
      </div>
    </div>`;

  const actions = card.querySelector(`#act-${m.match_id}`);

  // ACTIONS you can take as the responder:
  // Pending → withdraw
  if (m.match_status === 'pending'){
    const w = document.createElement('button');
    w.className='btn btn-sm btn-outline-secondary';
    w.textContent='Withdraw';
    w.addEventListener('click', async ()=>{
      try{ await postJSON(`${window.API_BASE}/match_withdraw.php`, { match_id: m.match_id }); card.remove(); }
      catch(e){ logError('driver:withdraw_failed', e, { matchId: m.match_id }); alert('Withdraw failed'); }
    });
    actions.appendChild(w);
  }

  // Accepted / matched / in_progress → you can mark complete too (both parties can do it)
  if (['accepted','matched','confirmed','in_progress'].includes(m.match_status)){
    const c = document.createElement('button');
    c.className='btn btn-sm btn-primary';
    c.textContent='Complete';
    c.addEventListener('click', async ()=>{
      try{
        await postJSON(`${window.API_BASE}/ride_set_status.php`, { ride_id: m.ride_id, status:'completed' });
        onRefresh?.();
      }
      catch(e){ handleActionError('driver:complete_failed', e, 'Failed to mark the ride complete.', { rideId: m.ride_id }); }
    });
    actions.appendChild(c);
  }

  // Completed → rate the other party (driver rates passenger; passenger rates driver)
  if (m.match_status === 'completed' && !m.already_rated){
    const r = document.createElement('button');
    r.className='btn btn-sm btn-warning';
    r.textContent = amDriver ? 'Rate passenger' : 'Rate driver';
    r.addEventListener('click', ()=>{
      const form = document.createElement('form');
      form.className = 'rating-form border rounded-3 bg-body-tertiary p-3 text-start mt-2';
      form.innerHTML = `
        <div class="mb-2">
          <label class="form-label small fw-semibold">Rate this ${amDriver ? 'passenger' : 'driver'}</label>
          <div class="btn-group" role="group" aria-label="Rating">
            ${[1,2,3,4,5].map(n => `
              <input type="radio" class="btn-check" name="stars" id="rate-${m.match_id}-${n}" value="${n}" ${n===5?'checked':''}>
              <label class="btn btn-sm btn-outline-warning" for="rate-${m.match_id}-${n}">${'★'.repeat(n)}</label>
            `).join('')}
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Comment (optional)</label>
          <textarea class="form-control form-control-sm" name="comment" rows="2" maxlength="1000" placeholder="Share highlights or things to improve"></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-warning">Submit rating</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-cancel>Cancel</button>
        </div>`;

      actions.innerHTML = '';
      actions.appendChild(form);

      form.addEventListener('submit', async (event)=>{
        event.preventDefault();
        const data = new FormData(form);
        const stars = Number(data.get('stars')) || 0;
        const comment = String(data.get('comment') || '').trim();
        if (stars < 1 || stars > 5) {
          alert('Please select a rating between 1 and 5 stars.');
          return;
        }
        form.querySelectorAll('button,textarea,input').forEach(elm => { elm.disabled = true; });
        try {
          await postJSON(`${window.API_BASE}/rate_submit.php`, {
            ride_id: m.ride_id,
            match_id: m.match_id,
            stars,
            comment
          });
          onRefresh?.();
        } catch (e) {
          logError('driver:rating_failed', e, { rideId: m.ride_id, matchId: m.match_id });
          alert('Rating failed');
          form.querySelectorAll('button,textarea,input').forEach(elm => { elm.disabled = false; });
        }
      });

      form.querySelector('[data-cancel]')?.addEventListener('click', ()=>{
        onRefresh?.();
      });
    });
    actions.appendChild(r);
  }

  return card;
}

async function render(el){
  el.innerHTML = `
    <h1 class="h4 mb-2">I responded</h1>
    <p class="text-secondary small mb-4">These are rides where you joined as a driver or passenger. Track your active trips, follow up on pending matches, and rate your partner once a ride is completed.</p>
    <section class="mb-4">
      <h2 class="h6 text-success d-flex align-items-center gap-2">Active trips</h2>
      <p class="text-secondary small mb-2">Use these actions to coordinate with your match and wrap up the ride when it ends.</p>
      <div id="acceptedList" class="vstack gap-3"></div>
    </section>
    <section class="mb-4">
      <h2 class="h6 text-warning d-flex align-items-center gap-2">Pending responses</h2>
      <p class="text-secondary small mb-2">Waiting on the ride owner? You can withdraw here if your plans changed.</p>
      <div id="pendingList" class="vstack gap-3"></div>
    </section>
    <section class="mb-4">
      <h2 class="h6 text-primary d-flex align-items-center gap-2">Completed (rate your partner)</h2>
      <p class="text-secondary small mb-2">Rate the other rider to keep the community trustworthy.</p>
      <div id="completedList" class="vstack gap-3"></div>
    </section>
    <div id="msg" class="d-none alert mt-3"></div>
  `;

  const accWrap = el.querySelector('#acceptedList');
  const penWrap = el.querySelector('#pendingList');
  const compWrap = el.querySelector('#completedList');
  const msg     = el.querySelector('#msg');

  let data;
  try {
    // EXPECTED: my_matches.php (no role filter needed) returns:
    // { ok:true, items:[
    //   { match_id, ride_id, type, from_text, to_text, ride_datetime,
    //     driver_user_id, passenger_user_id,
    //     other_display_driver, other_display_passenger,
    //     driver_phone, driver_whatsapp, passenger_phone, passenger_whatsapp,
    //     match_status, already_rated: bool }
    // ] }
    const res = await fetch(`${window.API_BASE}/my_matches.php`, { credentials:'same-origin' });
    data = await res.json();
  } catch (e) {
    logError('driver:fetch_failed', e);
    msg.className = 'alert alert-danger';
    msg.textContent = 'Failed to load your responded rides.';
    msg.classList.remove('d-none');
    return;
  }
  if (!data?.ok || !Array.isArray(data.items)) {
    msg.className = 'alert alert-danger';
    msg.textContent = 'Bad data.';
    msg.classList.remove('d-none');
    return;
  }

  const pending   = data.items.filter(m => m.match_status === 'pending');
  const active    = data.items.filter(m => ['accepted','matched','confirmed','in_progress'].includes(m.match_status));
  const completed = data.items.filter(m => m.match_status === 'completed');

  const refresh = () => render(el);

  if (!active.length) accWrap.innerHTML = `<div class="alert alert-info">No active trips right now.</div>`;
  else { accWrap.innerHTML=''; active.forEach(m => accWrap.appendChild(cardForResponded(m, refresh))); }

  if (!pending.length)  penWrap.innerHTML = `<div class="alert alert-info">No pending matches.</div>`;
  else { penWrap.innerHTML='';  pending.forEach(m  => penWrap.appendChild(cardForResponded(m, refresh))); }

  if (!completed.length) compWrap.innerHTML = `<div class="alert alert-secondary">No completed rides yet.</div>`;
  else { compWrap.innerHTML=''; completed.forEach(m => compWrap.appendChild(cardForResponded(m, refresh))); }
}

export function mountDriver(el){
  const target = (el instanceof HTMLElement) ? el : document.querySelector(el);
  if (!target) throw new Error('mountDriver: target element not found');
  render(target);
}
export function unmountDriver(){/* no-op */}
