import { logError } from '../utils/logger.js';

// /assets/js/components/posted.js
const esc = s => s ? String(s).replace(/[&<>"']/g, m => (
  {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]
)) : '';

const badge = status => {
  const map = {open:'secondary',pending:'warning',matched:'primary',confirmed:'primary',in_progress:'info',completed:'success',cancelled:'dark',rejected:'dark'};
  const cls = map[status] || 'light';
  return `<span class="badge badge-status text-bg-${cls}">${esc(status||'open')}</span>`;
};

const profileLink = (id, name) => {
  const label = esc(name || (id ? `User #${id}` : 'User'));
  const userId = Number(id);
  if (!userId) return label;
  return `<a class="text-decoration-none" href="/user.php?id=${userId}">${label}</a>`;
};

function friendlyStatusError(err){
  const code = err?.message || '';
  if (code === 'illegal_transition') {
    return 'This ride was updated in another tab or by the other rider. Refresh to see the latest status.';
  }
  if (code === 'no_active_match') {
    return 'The ride no longer has an active match, so this action cannot be performed.';
  }
  return '';
}

function handleActionError(tag, err, fallback, context){
  const message = friendlyStatusError(err) || fallback;
  logError(tag, err, context);
  alert(message);
}

// small helpers
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
    logError('posted:post_json_failed', err, { url, body });
    throw err;
  }
  return j;
}

function contactHtml(phone, whatsapp){
  const parts = [];
  if (phone) parts.push(`<a class="me-2" href="tel:${encodeURIComponent(phone)}">${esc(phone)}</a>`);
  if (whatsapp){
    const wa = String(whatsapp).replace(/\D+/g,'');
    if (wa) parts.push(`<a target="_blank" rel="noopener" href="https://wa.me/${wa}">WhatsApp</a>`);
  }
  return parts.join(' ');
}

const plural = (count, singular, pluralWord = null) => {
  const n = Number(count) || 0;
  const word = n === 1 ? singular : (pluralWord || `${singular}s`);
  return `${n} ${word}`;
};

function summarizeCounts(item){
  const counts = item.match_counts || {};
  return {
    pending: Number(counts.pending || 0),
    active: Number((counts.accepted || 0) + (counts.confirmed || 0) + (counts.in_progress || 0)),
    completed: Number(counts.completed || 0),
  };
}

function scenarioSummary(item){
  const { pending, active, completed } = summarizeCounts(item);
  const type = item.type;
  const status = item.status;
  const confirmed = item.confirmed;
  const manageUrl = `/manage_ride.php?id=${item.id}`;
  let otherLabel = esc('your match');
  if (confirmed) {
    if (type === 'offer') {
      const otherId = confirmed.passenger_user_id;
      const name = confirmed.passenger_display || (otherId ? `Passenger #${otherId}` : 'Passenger');
      otherLabel = profileLink(otherId, name);
    } else {
      const otherId = confirmed.driver_user_id;
      const name = confirmed.driver_display || (otherId ? `Driver #${otherId}` : 'Driver');
      otherLabel = profileLink(otherId, name);
    }
  }

  let heading = type === 'offer' ? 'You offered a ride' : 'You requested a ride';
  let detail = '';
  let cta = '';

  if (status === 'open') {
    if (type === 'offer') {
      detail = pending > 0
        ? `${plural(pending, 'passenger request')} ${pending === 1 ? 'is' : 'are'} waiting for your response.`
        : 'No passengers have requested this ride yet.';
      if (pending > 0) {
        cta = `<a class="fw-semibold link-primary" href="${manageUrl}">Review passenger requests</a>`;
      }
    } else {
      detail = pending > 0
        ? `${plural(pending, 'driver offer')} ${pending === 1 ? 'is' : 'are'} ready for you to review.`
        : 'No drivers have offered yet.';
      if (pending > 0) {
        cta = `<a class="fw-semibold link-primary" href="${manageUrl}">Review driver offers</a>`;
      }
    }
  } else if (status === 'matched' || status === 'confirmed') {
    if (confirmed) {
      detail = type === 'offer'
        ? `You're matched with ${otherLabel}. Coordinate pickup and start the trip when you're ready.`
        : `Driver ${otherLabel} accepted your request. Confirm plans before you meet up.`;
    } else {
      detail = 'You have an active match awaiting confirmation.';
    }
    if (pending > 0) {
      detail += ` You also have ${plural(pending, type === 'offer' ? 'other passenger request' : 'other driver offer')} waiting.`;
    }
    cta = `<a class="fw-semibold link-primary" href="${manageUrl}">Manage ride</a>`;
  } else if (status === 'in_progress') {
    detail = confirmed
      ? `You're on the road with ${otherLabel}.`
      : 'This ride is marked as in progress.';
    cta = `<a class="fw-semibold link-primary" href="${manageUrl}">Update status</a>`;
  } else if (status === 'completed') {
    detail = confirmed
      ? `You completed this trip with ${otherLabel}.`
      : 'This ride is completed.';
    if (!item.already_rated) {
      detail += ' Please rate the other rider to wrap things up.';
      cta = '<span class="fw-semibold text-warning">Awaiting your rating</span>';
    }
  } else if (status === 'cancelled' || status === 'rejected') {
    detail = 'This ride is archived for your records.';
  } else {
    detail = `Current status: ${esc(status)}.`;
  }

  const countsLine = [];
  if (pending > 0) countsLine.push(`${plural(pending, type === 'offer' ? 'passenger request' : 'driver offer')}`);
  if (active > 0) countsLine.push(`${plural(active, 'active match')}`);
  if (completed > 0) countsLine.push(`${plural(completed, 'completed match')}`);

  return { heading, detail, cta, countsLine };
}

async function load(el){
  el.innerHTML = `
    <h1 class="h4 mb-2">I posted (offers & looking)</h1>
    <p class="text-secondary small mb-4">Manage the rides you created. Active rides stay at the top; once a ride is finished or cancelled it moves into the history lists below.</p>
    <div id="ownMsg" class="d-none alert"></div>
    <section class="mb-4">
      <h2 class="h6 text-success d-flex align-items-center gap-2">Active rides</h2>
      <p class="text-secondary small mb-2">Start trips, mark them complete, or cancel if plans change.</p>
      <div id="activeList" class="vstack gap-3"></div>
    </section>
    <section class="mb-4">
      <h2 class="h6 text-primary d-flex align-items-center gap-2">Completed rides</h2>
      <p class="text-secondary small mb-2">Remember to rate the other rider when a trip wraps up.</p>
      <div id="completedList" class="vstack gap-3"></div>
    </section>
    <section class="mb-4">
      <h2 class="h6 text-secondary d-flex align-items-center gap-2">Cancelled / Rejected</h2>
      <p class="text-secondary small mb-2">These are archived for your records.</p>
      <div id="cancelledList" class="vstack gap-3"></div>
    </section>
  `;

  const msg        = el.querySelector('#ownMsg');
  const activeWrap = el.querySelector('#activeList');
  const doneWrap   = el.querySelector('#completedList');
  const cancelWrap = el.querySelector('#cancelledList');

  let data;
  try {
    // EXPECTED: ride_list.php?mine=1 returns:
    // { ok:true, items:[{ id, type, status, ride_datetime, from_text, to_text, seats, package_only,
    //   confirmed: { match_id, driver_user_id, driver_display, driver_phone, driver_whatsapp,
    //                passenger_user_id, passenger_display, passenger_phone, passenger_whatsapp }|null,
    //   already_rated: boolean
    // }] }
    const res = await fetch(`${window.API_BASE}/ride_list.php?mine=1`, { credentials:'same-origin' });
    data = await res.json();
  } catch (e) {
    logError('posted:fetch_failed', e);
    msg.className='alert alert-danger';
    msg.textContent='Failed to load your rides.';
    msg.classList.remove('d-none');
    return;
  }

  const items = Array.isArray(data.items) ? data.items : [];
  if (!items.length){
    activeWrap.innerHTML = '<div class="alert alert-info">You have not posted any rides yet.</div>';
    doneWrap.innerHTML = '';
    cancelWrap.innerHTML = '';
    return;
  }

  const groups = { active: [], completed: [], cancelled: [] };
  for (const item of items){
    if (item.status === 'completed') groups.completed.push(item);
    else if (item.status === 'cancelled' || item.status === 'rejected') groups.cancelled.push(item);
    else groups.active.push(item);
  }

  const renderList = (wrap, list, emptyHtml) => {
    if (!list.length) {
      wrap.innerHTML = emptyHtml;
      return;
    }
    wrap.innerHTML = '';
    list.forEach(item => wrap.appendChild(renderCard(item, el)));
  };

  renderList(activeWrap, groups.active, '<div class="alert alert-info">No active rides. Create one to get started!</div>');
  renderList(doneWrap, groups.completed, '<div class="alert alert-info">No completed rides yet.</div>');
  renderList(cancelWrap, groups.cancelled, '<div class="alert alert-secondary">No cancelled rides.</div>');
}

function renderCard(item, el){
  const dt = item.ride_datetime ? new Date(item.ride_datetime.replace(' ','T')+'Z').toLocaleString() : 'Any time';
  const seats = (item.package_only || item.seats===0) ? 'Package only' : `${item.seats} seat(s)`;
  const st = item.status || 'open';
  const card = document.createElement('div');
  card.className = 'card ride-card shadow-sm';

  let rolesHtml = '';
  if (item.confirmed){
    const d = item.confirmed;
    const driverName = d.driver_display || (d.driver_user_id ? `User #${d.driver_user_id}` : 'Driver');
    const passengerName = d.passenger_display || (d.passenger_user_id ? `User #${d.passenger_user_id}` : 'Passenger');
    rolesHtml = `
        <div class="mt-2">
          <div class="d-flex align-items-center gap-2"><span class="badge text-bg-primary">Driver</span><span>${profileLink(d.driver_user_id, driverName)}</span></div>
          <div class="d-flex align-items-center gap-2 mt-1"><span class="badge text-bg-success">Passenger</span><span>${profileLink(d.passenger_user_id, passengerName)}</span></div>
          <div class="mt-1"><strong>Contact:</strong> ${contactHtml(d.driver_phone||'', d.driver_whatsapp||'')} ${contactHtml(d.passenger_phone||'', d.passenger_whatsapp||'')}</div>
        </div>`;
  }

  card.innerHTML = `
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-2">
          <div>
            <h5 class="mb-1">${item.type==='offer'?'🚗 Offer':'🙋 Looking (request)'}</h5>
            ${badge(st)}
          </div>
          <span class="badge text-bg-light">${esc(dt)}</span>
        </div>
        <div class="border rounded-3 bg-body-tertiary p-3 mt-3" id="scenario-${item.id}"></div>
        <div class="row mt-3 g-3 align-items-start">
          <div class="col-md-8">
            <div><strong>From:</strong> ${esc(item.from_text)}</div>
            <div><strong>To:</strong> ${esc(item.to_text)}</div>
            <div><strong>Seats:</strong> ${esc(seats)}</div>
            ${rolesHtml}
          </div>
          <div class="col-md-4 text-md-end" id="actions-${item.id}">
          </div>
        </div>
      </div>`;

  const scenario = scenarioSummary(item);
  const scenarioEl = card.querySelector(`#scenario-${item.id}`);
  if (scenarioEl) {
    scenarioEl.innerHTML = `
      <div class="fw-semibold text-primary">${esc(scenario.heading)}</div>
      <div class="text-secondary small mt-1">${scenario.detail}</div>
      ${scenario.countsLine.length ? `<div class="text-secondary small mt-2"><i class="bi bi-people me-1"></i>${scenario.countsLine.join(' · ')}</div>` : ''}
      ${scenario.cta ? `<div class="mt-2">${scenario.cta}</div>` : ''}
    `;
  }

  const actions = card.querySelector(`#actions-${item.id}`);
  const addBtn = (txt, cls, handler) => {
    const b = document.createElement('button');
    b.className = `btn btn-sm ${cls} ms-2`;
    b.textContent = txt;
    b.addEventListener('click', handler);
    actions.appendChild(b);
  };

  if (st === 'open'){
    const manage = document.createElement('a');
    manage.className = 'btn btn-sm btn-outline-success ms-2';
    manage.href = `/manage_ride.php?id=${item.id}`;
    manage.textContent = 'Manage';
    actions.appendChild(manage);

    const del = document.createElement('button');
    del.className='btn btn-sm btn-outline-danger ms-2';
    del.textContent='Delete';
    del.addEventListener('click', async ()=>{
      if(!confirm('Delete this ride?')) return;
      try { await postJSON(`${window.API_BASE}/ride_delete.php`, {id:item.id}); load(el); }
      catch(e){ logError('posted:delete_failed', e, { rideId: item.id }); alert('Delete failed'); }
    });
    actions.appendChild(del);
  }

  if ((st === 'matched' || st === 'confirmed') && item.confirmed){
    addBtn('Start trip', 'btn-outline-primary', async ()=>{
      try{ await postJSON(`${window.API_BASE}/ride_set_status.php`, {ride_id:item.id, status:'in_progress'}); load(el); }
      catch(e){ handleActionError('posted:start_failed', e, 'Failed to start this ride.', { rideId: item.id }); }
    });
    addBtn('Complete', 'btn-primary', async ()=>{
      try{ await postJSON(`${window.API_BASE}/ride_set_status.php`, {ride_id:item.id, status:'completed'}); load(el); }
      catch(e){ handleActionError('posted:complete_failed', e, 'Failed to mark the ride complete.', { rideId: item.id }); }
    });
    addBtn('Cancel', 'btn-outline-secondary', async ()=>{
      if(!confirm('Cancel this ride?')) return;
      try{ await postJSON(`${window.API_BASE}/ride_set_status.php`, {ride_id:item.id, status:'cancelled'}); load(el); }
      catch(e){ handleActionError('posted:cancel_failed', e, 'Failed to cancel this ride.', { rideId: item.id }); }
    });
  }

  if (st === 'in_progress' && item.confirmed){
    addBtn('Complete', 'btn-primary', async ()=>{
      try{ await postJSON(`${window.API_BASE}/ride_set_status.php`, {ride_id:item.id, status:'completed'}); load(el); }
      catch(e){ handleActionError('posted:complete_failed', e, 'Failed to mark the ride complete.', { rideId: item.id }); }
    });
  }

  if (st === 'completed' && item.confirmed && !item.already_rated){
    const rateBtn = document.createElement('button');
    rateBtn.className='btn btn-sm btn-warning ms-2';
    rateBtn.textContent = item.type === 'offer' ? 'Rate passenger' : 'Rate driver';
    rateBtn.addEventListener('click', ()=>{
      const form = document.createElement('form');
      form.className = 'rating-form border rounded-3 bg-body-tertiary p-3 text-start mt-2';
      form.innerHTML = `
        <div class="mb-2">
          <label class="form-label small fw-semibold">Rate this ${item.type === 'offer' ? 'passenger' : 'driver'}</label>
          <div class="btn-group" role="group" aria-label="Rating">
            ${[1,2,3,4,5].map(n => `
              <input type="radio" class="btn-check" name="stars" id="rate-${item.id}-${n}" value="${n}" ${n===5?'checked':''}>
              <label class="btn btn-sm btn-outline-warning" for="rate-${item.id}-${n}">${'★'.repeat(n)}</label>
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
            ride_id: item.id,
            match_id: item.confirmed.match_id,
            stars,
            comment
          });
          load(el);
        } catch (e) {
          logError('posted:rating_failed', e, { rideId: item.id, matchId: item.confirmed?.match_id });
          alert('Rating failed');
          form.querySelectorAll('button,textarea,input').forEach(elm => { elm.disabled = false; });
        }
      });

      form.querySelector('[data-cancel]')?.addEventListener('click', ()=>{
        load(el);
      });
    });
    actions.appendChild(rateBtn);
  }

  return card;
}

export function mountPosted(el){ load(el); }
export function unmountPosted(){ /* no-op */ }
