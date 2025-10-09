import { logError } from '../utils/logger.js';

// /assets/js/components/driver.js
const esc = s => s ? String(s).replace(/[&<>"']/g, m => (
  {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]
)) : '';

const badge = status => {
  const map = {pending:'warning',accepted:'primary',matched:'primary',in_progress:'info',completed:'success',rejected:'dark',cancelled:'dark'};
  const cls = map[status] || 'light';
  return `<span class="badge text-bg-${cls}">${esc(status || 'pending')}</span>`;
};

const contactHtml = (phone, whatsapp) => {
  const parts = [];
  if (phone) parts.push(`<a class="me-2" href="tel:${encodeURIComponent(phone)}">${esc(phone)}</a>`);
  if (whatsapp){
    const wa = String(whatsapp).replace(/\D+/g,'');
    if (wa) parts.push(`<a target="_blank" rel="noopener" href="https://wa.me/${wa}">WhatsApp</a>`);
  }
  return parts.join(' ');
};

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

function ratingStarRow(onPick){
  const wrap = document.createElement('div');
  wrap.className = 'mt-2';
  wrap.innerHTML = `
    <div class="btn-group" role="group" aria-label="Rate">
      ${[1,2,3,4,5].map(n=>`<button type="button" class="btn btn-sm btn-outline-warning" data-star="${n}">${'★'.repeat(n)}</button>`).join('')}
    </div>`;
  wrap.querySelectorAll('[data-star]').forEach(btn=>{
    btn.addEventListener('click', ()=> onPick(+btn.dataset.star));
  });
  return wrap;
}

function cardForResponded(m){
  const dt = m.ride_datetime ? new Date(m.ride_datetime.replace(' ','T')+'Z').toLocaleString() : 'Any time';
  const meId   = Number(window.MY_ID || 0);
  const meName = (window.ME_NAME && String(window.ME_NAME).trim()) || 'You';

  const amDriver = Number(m.driver_user_id) === meId;
  const driverLabel    = amDriver ? `${meName} (You)` : esc(m.other_display_driver || 'Driver');
  const passengerLabel = amDriver ? esc(m.other_display_passenger || 'Passenger') : `${meName} (You)`;

  // Only show the other party’s contact
  const otherPhone    = amDriver ? m.passenger_phone    : m.driver_phone;
  const otherWhatsApp = amDriver ? m.passenger_whatsapp : m.driver_whatsapp;

  const card = document.createElement('div');
  card.className = 'card shadow-sm mb-2';
  card.innerHTML = `
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div class="me-3">
          <div class="fw-semibold">${m.type==='offer' ? '🚗 Offer' : '🙋 Request'} — ${esc(m.from_text)} → ${esc(m.to_text)}</div>
          <div class="mt-2">
            <div class="d-flex align-items-center gap-2"><span class="badge text-bg-primary">Driver</span><span>${driverLabel}</span></div>
            <div class="d-flex align-items-center gap-2 mt-1"><span class="badge text-bg-success">Passenger</span><span>${passengerLabel}</span></div>
            ${ (otherPhone||otherWhatsApp) ? `<div class="mt-1"><strong>Contact:</strong> ${contactHtml(otherPhone, otherWhatsApp)}</div>` : '' }
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
  if (['accepted','matched','in_progress'].includes(m.match_status)){
    const c = document.createElement('button');
    c.className='btn btn-sm btn-primary';
    c.textContent='Complete';
    c.addEventListener('click', async ()=>{
      try{ await postJSON(`${window.API_BASE}/ride_set_status.php`, { ride_id: m.ride_id, status:'completed' }); location.reload(); }
      catch(e){ logError('driver:complete_failed', e, { rideId: m.ride_id }); alert('Complete failed'); }
    });
    actions.appendChild(c);
  }

  // Completed → rate the other party (driver rates passenger; passenger rates driver)
  if (m.match_status === 'completed' && !m.already_rated){
    const r = document.createElement('button');
    r.className='btn btn-sm btn-warning';
    r.textContent='Rate';
    r.addEventListener('click', ()=>{
      r.replaceWith(ratingStarRow(async (stars)=>{
        try{
          const target = amDriver ? m.passenger_user_id : m.driver_user_id;
          const role   = amDriver ? 'passenger' : 'driver';
          await postJSON(`${window.API_BASE}/rate_submit.php`, { ride_id: m.ride_id, target_user_id: target, stars, role });
          location.reload();
        }catch(e){ logError('driver:rating_failed', e, { rideId: m.ride_id, matchId: m.match_id }); alert('Rating failed'); }
      }));
    });
    actions.appendChild(r);
  }

  return card;
}

async function render(el){
  el.innerHTML = `
    <h1 class="h4 mb-3">I responded</h1>
    <h2 class="h6 text-success d-flex align-items-center gap-2">Accepted / In progress</h2>
    <div id="acceptedList" class="vstack gap-3 mb-4"></div>
    <h2 class="h6 text-warning d-flex align-items-center gap-2">Pending</h2>
    <div id="pendingList" class="vstack gap-3"></div>
    <div id="msg" class="d-none alert mt-3"></div>
  `;

  const accWrap = el.querySelector('#acceptedList');
  const penWrap = el.querySelector('#pendingList');
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
    msg.className='alert alert-danger'; msg.textContent='Failed to load your responded rides.'; msg.classList.remove('d-none'); return;
  }
  if (!data?.ok || !Array.isArray(data.items)){ msg.className='alert alert-danger'; msg.textContent='Bad data.'; msg.classList.remove('d-none'); return; }

  // split into pending vs active/accepted/etc.
  const pending  = data.items.filter(m => m.match_status === 'pending');
  const accepted = data.items.filter(m => m.match_status !== 'pending');

  if (!accepted.length) accWrap.innerHTML = `<div class="alert alert-info">No accepted rides yet.</div>`;
  else { accWrap.innerHTML=''; accepted.forEach(m => accWrap.appendChild(cardForResponded(m))); }

  if (!pending.length)  penWrap.innerHTML = `<div class="alert alert-info">No pending requests yet.</div>`;
  else { penWrap.innerHTML='';  pending.forEach(m  => penWrap.appendChild(cardForResponded(m))); }
}

export function mountDriver(el){
  const target = (el instanceof HTMLElement) ? el : document.querySelector(el);
  if (!target) throw new Error('mountDriver: target element not found');
  render(target);
}
export function unmountDriver(){/* no-op */}
