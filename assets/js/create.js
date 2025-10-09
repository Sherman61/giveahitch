(() => {
  const form = document.getElementById('rideForm');
  const alertBox = document.getElementById('formAlert');

  function showMsg(msg, type='danger') {
    alertBox.innerHTML = `<div class="alert alert-${type} mb-0">${msg}</div>`;
  }

  function okPhone(s){ return s === '' || /^\+?[0-9\s\-()]{7,32}$/.test(s); }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) {
      e.stopPropagation();
      form.classList.add('was-validated');
      return;
    }
    const fd = new FormData(form);

    // front-end validations beyond HTML5:
    const phone = (fd.get('phone')||'').trim();
    const wa    = (fd.get('whatsapp')||'').trim();
    if (!okPhone(phone) || !okPhone(wa)) {
      showMsg('Phone/WhatsApp format invalid.'); return;
    }
    if (phone === '' && wa === '') {
      showMsg('Provide at least one contact (Phone or WhatsApp).'); return;
    }
    const seats = parseInt(fd.get('seats') || '1', 10);
    if (seats === 0) fd.set('package_only','1');

    try {
      const res = await fetch(`${API_BASE}/ride_create.php`, { method:'POST', body: fd, credentials:'same-origin' });
      const data = await res.json().catch(()=>({ok:false,error:'Invalid server response'}));
      if (!res.ok || !data.ok) {
        const msg = data.errors ? Object.values(data.errors).join('<br>') : (data.error || 'Failed to post');
        showMsg(msg); return;
      }
      showMsg('Ride posted successfully!', 'success');
      form.reset(); form.classList.remove('was-validated');
    } catch (err) {
      showMsg('Network error. Please try again.');
    }
  });
})();
