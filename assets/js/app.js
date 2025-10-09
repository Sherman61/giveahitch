(() => {
  const ridesList = document.getElementById('ridesList');
  const filterType = document.getElementById('filterType');
  const searchBox = document.getElementById('searchBox');
  const refreshBtn = document.getElementById('refreshBtn');
  const form = document.getElementById('rideForm');
  const formAlert = document.getElementById('formAlert');
  const liveBadge = document.getElementById('liveBadge');

  async function fetchRides() {
    const params = new URLSearchParams();
    if (filterType.value) params.set('type', filterType.value);
    if (searchBox.value.trim() !== '') params.set('q', searchBox.value.trim());
    const res = await fetch('/glitchahitch/api/ride_list.php?' + params.toString(), {credentials:'same-origin'});
    const data = await res.json();
    if (!data.ok) return;
    renderRides(data.items || []);
  }

  function contactLinks(item) {
    const parts = [];
    if (item.phone) parts.push(`<a href="tel:${encodeURIComponent(item.phone)}">${escapeHtml(item.phone)}</a>`);
    if (item.whatsapp) {
      const wa = item.whatsapp.replace(/\D+/g,'');
      parts.push(`<a class="whatsapp-link" target="_blank" rel="noopener" href="https://wa.me/${wa}">WhatsApp</a>`);
    }
    return parts.join(' · ');
  }

  function renderRides(items) {
    ridesList.innerHTML = '';
    if (!items.length) {
      ridesList.innerHTML = `<div class="alert alert-info mb-0">No rides found.</div>`;
      return;
    }
    items.forEach(item => {
      const dt = item.ride_datetime ? new Date(item.ride_datetime.replace(' ','T')+'Z') : null;
      const timeStr = dt ? dt.toLocaleString() : 'Any time';
      const seatsStr = item.package_only || item.seats === 0 ? 'Package only' : `${item.seats} seat(s)`;
      const cls = item.type === 'request' ? 'request' : 'offer';
      const el = document.createElement('div');
      el.className = `card ride-card ${cls} shadow-sm`;
      el.innerHTML = `
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <h5 class="card-title mb-1">${item.type === 'offer' ? '🚗 Offer' : '🙋 Request'}</h5>
            <span class="badge text-bg-light">${timeStr}</span>
          </div>
          <p class="mb-1"><strong>From:</strong> ${escapeHtml(item.from_text)}</p>
          <p class="mb-1"><strong>To:</strong> ${escapeHtml(item.to_text)}</p>
          <p class="mb-1"><strong>Seats:</strong> ${seatsStr}</p>
          ${item.note ? `<p class="mb-2"><em>${escapeHtml(item.note)}</em></p>` : ''}
          <p class="mb-0"><strong>Contact:</strong> ${contactLinks(item)}</p>
        </div>`;
      ridesList.appendChild(el);
    });
  }

  function escapeHtml(s){ return s ? s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])) : ''; }

  // Form validation + submit
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) {
      e.stopPropagation();
      form.classList.add('was-validated');
      return;
    }
    const fd = new FormData(form);
    if (!fd.get('phone') && !fd.get('whatsapp')) {
      showFormMsg('Please provide at least one contact field.', 'danger'); return;
    }
    const seats = parseInt(fd.get('seats') || '1', 10);
    if (seats === 0) fd.set('package_only', '1');

    try {
      const res = await fetch('/glitchahitch/api/create_ride.php', { method:'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (data.ok) {
        showFormMsg('Ride posted! It will appear below.', 'success');
        form.reset();
        form.classList.remove('was-validated');
        await fetchRides();
      } else {
        showFormMsg(Object.values(data.errors || {error:'Validation failed.'}).join('<br>'), 'danger');
      }
    } catch (err) {
      showFormMsg('Network error. Please try again.', 'danger');
    }
  });

  function showFormMsg(msg, type) {
    formAlert.innerHTML = `<div class="alert alert-${type}" role="alert">${msg}</div>`;
  }

  refreshBtn.addEventListener('click', fetchRides);
  filterType.addEventListener('change', fetchRides);
  searchBox.addEventListener('input', () => { clearTimeout(window._qT); window._qT=setTimeout(fetchRides, 300); });

  // WebSocket live updates
  let socket;
  function initSocket() {
    if (!window.WS_URL) { liveBadge.innerHTML = 'Live updates: <strong>off</strong>'; return; }
    socket = io(window.WS_URL, {transports:['websocket']});
    socket.on('connect', () => { liveBadge.innerHTML = 'Live updates: <strong>connected</strong>'; });
    socket.on('disconnect', () => { liveBadge.innerHTML = 'Live updates: <strong>disconnected</strong>'; });
    socket.on('ride:new', (payload) => {
      fetchRides();
    });
  }

  initSocket();
  fetchRides();
})();
