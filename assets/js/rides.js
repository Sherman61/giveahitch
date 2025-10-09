//assets/js/rides.js

(() => {
  const list = document.getElementById('ridesList');
  const filterType = document.getElementById('filterType');
  const searchBox = document.getElementById('searchBox');
  const refreshBtn = document.getElementById('refreshBtn');
  const liveBadge = document.getElementById('liveBadge');

  function escapeHtml(s){ return s ? s.replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'}[m])):''; }

  async function fetchRides() {
    const p = new URLSearchParams();
    if (filterType && filterType.value) p.set('type', filterType.value);
    const q = (searchBox && searchBox.value || '').trim();
    if (q) p.set('q', q);
    try {
      const res = await fetch(`${API_BASE}/ride_list.php?${p.toString()}`, {credentials:'same-origin'});
      const data = await res.json().catch(()=>({ok:false,items:[]}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to load');
      render(data.items);
    } catch (err) {
      list.innerHTML = `<div class="alert alert-danger">Failed to load rides: ${escapeHtml(err.message||'error')}</div>`;
    }
  }

  function render(items) {
    list.innerHTML = '';
    if (!items || !items.length) {
      list.innerHTML = `<div class="alert alert-info mb-0">No rides found.</div>`;
      return;
    }

    const meId = window.ME_USER_ID || null;

    for (const item of items) {
      const dt = item.ride_datetime ? new Date(item.ride_datetime.replace(' ','T')+'Z') : null;
      const timeStr = dt ? dt.toLocaleString() : 'Any time';
      const seatsStr = (item.package_only || item.seats===0) ? 'Package only' : `${item.seats} seat(s)`;
      const cls = item.type === 'request' ? 'request' : 'offer';
      const owner = item.owner_display ? `${item.owner_display}` : 'User';
      const canAccept = (meId && meId !== item.user_id && item.status === 'open');

      const phoneLink = item.phone ? `<a href="tel:${encodeURIComponent(item.phone)}" class="me-2">${escapeHtml(item.phone)}</a>` : '';
      const waNum = item.whatsapp ? item.whatsapp.replace(/\D+/g,'') : '';
      const waLink = item.whatsapp ? `<a target="_blank" rel="noopener" href="https://wa.me/${waNum}">WhatsApp</a>` : '';

      const byUser = item.user_id
        ? `<a href="/user.php?id=${item.user_id}" class="text-decoration-none">${escapeHtml(owner)}</a>`
        : escapeHtml(owner);

      const card = document.createElement('div');
      card.className = `card ride-card ${cls} shadow-sm`;
      card.innerHTML = `
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <h5 class="card-title mb-1">${item.type==='offer'?'🚗 Offer':'🙋 Request'}</h5>
            <span class="badge text-bg-light">${timeStr}</span>
          </div>
          <p class="mb-1"><strong>By:</strong> ${byUser}</p>
          <p class="mb-1"><strong>From:</strong> ${escapeHtml(item.from_text)}</p>
          <p class="mb-1"><strong>To:</strong> ${escapeHtml(item.to_text)}</p>
          <p class="mb-1"><strong>Seats:</strong> ${seatsStr}</p>
          ${item.note ? `<p class="mb-2"><em>${escapeHtml(item.note)}</em></p>` : ''}
          <div class="d-flex justify-content-between align-items-center">
            <p class="mb-0"><strong>Contact:</strong> ${phoneLink}${waLink}</p>
            ${canAccept ? `<button class="btn btn-sm btn-success" data-accept="${item.id}">Accept ride</button>` : ''}
          </div>
        </div>`;

      // Bind accept after element exists
      const btn = card.querySelector('button[data-accept]');
      if (btn) {
        btn.addEventListener('click', async () => {
          if(!confirm('Accept this ride?')) return;
          const id = parseInt(btn.getAttribute('data-accept'),10);
          try{ 
            const res = await fetch('/api/match_create.php',{
              method:'POST',
              headers:{'Content-Type':'application/json'},
              credentials:'same-origin',
              body: JSON.stringify({ride_id:id, csrf: window.CSRF_TOKEN || ''})
            });
            const data = await res.json();
            if(!res.ok || !data.ok) throw new Error(data.error||'Failed');
            alert('Accepted! Once done, the passenger should confirm completion.');
            fetchRides();
          }catch(e){ alert('Error: '+(e.message||e)); }
        });
      }

      list.appendChild(card);
    }
  }

  if (refreshBtn) refreshBtn.addEventListener('click', fetchRides);
  if (filterType) filterType.addEventListener('change', fetchRides);
  if (searchBox) {
    let t;
    searchBox.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(fetchRides, 300); });
  }

  // WS live updates
  try {
    const s = io({ path: '/socket.io', transports: ['websocket','polling'], timeout: 20000 });
    const badge = liveBadge;
    s.on('connect', () => { if (badge) badge.innerHTML = 'Live: <strong>on</strong>'; });
    s.on('disconnect', () => { if (badge) badge.innerHTML = 'Live: <strong>off</strong>'; });
    s.on('ride:new', () => fetchRides());
  } catch {}

  fetchRides();
})();
