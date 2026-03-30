const esc = (value) => value ? String(value).replace(/[&<>"']/g, (char) => (
  {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]
)) : '';

function parseServerDate(value){
  if (!value) return null;
  const normalized = String(value).trim().replace(' ', 'T');
  const dt = new Date(normalized);
  return Number.isNaN(dt.getTime()) ? null : dt;
}

function formatDateTime(value, fallback = 'Any time'){
  const dt = parseServerDate(value);
  if (!dt) return fallback;
  return dt.toLocaleString([], {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function formatPostedTime(value){
  const dt = parseServerDate(value);
  if (!dt) return 'Posted recently';
  return `Posted ${formatDateTime(value, 'Posted recently')}`;
}

function rideTypeLabel(type){
  return type === 'offer' ? 'Ride you are offering' : 'Ride you are requesting';
}

function joinedTripLabel(type){
  return type === 'offer' ? 'Ride you offered to join' : 'Ride request you answered';
}

function responderRoleLabel(type, count = 1){
  if (type === 'offer') return count === 1 ? 'rider' : 'riders';
  return count === 1 ? 'driver' : 'drivers';
}

function rideCounterpartyLabel(type){
  return type === 'offer' ? 'rider' : 'driver';
}

function roleTitle(type, perspective = 'responder'){
  if (perspective === 'responder') {
    return type === 'offer' ? 'Rider' : 'Driver';
  }
  return type === 'offer' ? 'Driver' : 'Passenger';
}

export {
  esc,
  formatDateTime,
  formatPostedTime,
  joinedTripLabel,
  parseServerDate,
  responderRoleLabel,
  rideCounterpartyLabel,
  rideTypeLabel,
  roleTitle,
};
