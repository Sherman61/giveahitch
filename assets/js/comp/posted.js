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

async function load(el){
  el.innerHTML = `
    <h1 class="h4 mb-3">I posted (offers & looking)</h1>
    <div id="ownMsg" class="d-none alert"></div>
    <div id="ownList" class="vstack gap-3"></div>
  `;

  const msg  = el.querySelector('#ownMsg');
  const list = el.querySelector('#ownList');

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
    list.innerHTML = '<div class="alert alert-info">No rides yet.</div>';
    return;
  }

  list.innerHTML = '';
  for (const item of items){
    const dt = item.ride_datetime ? new Date(item.ride_datetime.replace(' ','T')+'Z').toLocaleString() : 'Any time';
    const seats = (item.package_only || item.seats===0) ? 'Package only' : `${item.seats} seat(s)`;
    const st = item.status || 'open';
    const card = document.createElement('div');
    card.className = 'card ride-card shadow-sm';

    // If there’s a confirmed match, show both roles clearly
    let rolesHtml = '';
    if (item.confirmed){
      const d = item.confirmed;
      rolesHtml = `
        <div class="mt-2">
          <div class="d-flex align-items-center gap-2"><span class="badge text-bg-primary">Driver</span><span>${esc(d.driver_display||('User #'+d.driver_user_id))}</span></div>
          <div class="d-flex align-items-center gap-2 mt-1"><span class="badge text-bg-success">Passenger</span><span>${esc(d.passenger_display||('User #'+d.passenger_user_id))}</span></div>
          <div class="mt-1"><strong>Contact:</strong> ${contactHtml(d.driver_phone||'', d.driver_whatsapp||'')} ${contactHtml(d.passenger_phone||'', d.passenger_whatsapp||'')}</div>
        </div>`;
    }

    card.innerHTML = `
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-1">${item.type==='offer'?'🚗 Offer':'🙋 Looking (request)'}</h5>
            ${badge(st)}
          </div>
          <span class="badge text-bg-light">${esc(dt)}</span>
        </div>
        <div class="row mt-2">
          <div class="col-md-8">
            <div><strong>From:</strong> ${esc(item.from_text)}</div>
            <div><strong>To:</strong> ${esc(item.to_text)}</div>
            <div><strong>Seats:</strong> ${esc(seats)}</div>
            ${rolesHtml}
          </div>
          <div class="col-md-4 text-end" id="actions-${item.id}">
          </div>
        </div>
      </div>`;

    const actions = card.querySelector(`#actions-${item.id}`);

    // ACTIONS for owner (manage until completed)
    // API you should expose (minimal):
    // - match_confirm.php {ride_id, match_id, csrf}
    // - ride_set_status.php {ride_id, status: 'in_progress'|'completed'|'cancelled', csrf}
    // - ride_delete.php {id, csrf}
    // - rate_submit.php {ride_id, target_user_id, stars, role: 'driver'|'passenger', csrf}
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

    if (st === 'matched' && item.confirmed){
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

    if (st === 'completed' && item.confirmed){
      // show Rate button if not already rated
      if (!item.already_rated){
        const rateBtn = document.createElement('button');
        rateBtn.className='btn btn-sm btn-warning ms-2';
        rateBtn.textContent='Rate';
        rateBtn.addEventListener('click', ()=>{
          rateBtn.replaceWith(ratingStarRow(async (stars)=>{
            try{
              // who do I rate? If I created the ride and it was an offer → I’m the driver: rate passenger.
              // If I created the ride and it was a request → I’m the passenger: rate driver.
              const targetId = (item.type==='offer') ? item.confirmed.passenger_user_id : item.confirmed.driver_user_id;
              const role     = (item.type==='offer') ? 'passenger' : 'driver';
              await postJSON(`${window.API_BASE}/rate_submit.php`, {
                ride_id: item.id, target_user_id: targetId, stars, role
              });
              load(el);
            }catch(e){ logError('posted:rating_failed', e, { rideId: item.id, targetId: item.confirmed?.match_id }); alert('Rating failed'); }
          }));
        });
        actions.appendChild(rateBtn);
      }
    }

    list.appendChild(card);
  }
}

export function mountPosted(el){ load(el); }
export function unmountPosted(){ /* no-op */ }
