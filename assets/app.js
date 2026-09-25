'use strict';
// Only public assets are cached. Attendance is never queued or stored offline.
if ('serviceWorker' in navigator && window.isSecureContext) {
  navigator.serviceWorker.register('sw.js').catch(() => {});
}
function connectionState() {
  document.querySelectorAll('[data-connection]').forEach(el => el.textContent = navigator.onLine ? 'Conectado' : 'Sin conexión');
  document.querySelectorAll('[data-offline]').forEach(el => el.hidden = navigator.onLine);
}
addEventListener('online', connectionState);
addEventListener('offline', connectionState);
connectionState();
let installPrompt;
addEventListener('beforeinstallprompt', event => {
  event.preventDefault(); installPrompt = event;
  document.querySelectorAll('[data-install]').forEach(button => {
    button.hidden = false;
    button.onclick = async () => { await installPrompt.prompt(); button.hidden = true; installPrompt = null; };
  });
});
document.querySelectorAll('[data-clock]').forEach(el => {
  const initial = Number(el.dataset.server), started = performance.now();
  const formatter = new Intl.DateTimeFormat('es-CL', {hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23',timeZone:el.dataset.zone});
  const update = () => { el.textContent = formatter.format(new Date(initial + performance.now() - started)); };
  update(); setInterval(update,1000);
});
document.querySelectorAll('[data-schedule-mode]').forEach(el => {
  const update = () => {
    const box = el.closest('form').querySelector('.schedule-days');
    box.classList.toggle('disabled', el.value === 'none');
    box.querySelectorAll('input').forEach(input => input.disabled = el.value === 'none');
  };
  el.addEventListener('change',update); update();
});
document.querySelectorAll('[data-punch]').forEach(form => {
  let busy = false;
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    const status = form.querySelector('.location-status'), button = form.querySelector('button');
    if (!navigator.onLine) { status.textContent = 'Necesitas conexión a Internet para marcar.'; return; }
    if (!navigator.geolocation || !window.isSecureContext) { status.textContent = 'La ubicación requiere un navegador compatible y una conexión HTTPS.'; return; }
    busy = true; button.disabled = true;
    try {
      status.textContent = 'Preparando un marcado seguro…';
      const data = new URLSearchParams({action:'challenge',csrf:form.elements.csrf.value});
      const controller = new AbortController(); const timer = setTimeout(() => controller.abort(),15000);
      let response;
      try { response = await fetch('index.php', {method:'POST',body:data,credentials:'same-origin',cache:'no-store',signal:controller.signal}); }
      finally { clearTimeout(timer); }
      const challenge = await response.json();
      if (!response.ok || !challenge.token) throw new Error(challenge.error || 'No pudimos validar tu sesión. Actualiza la página.');
      status.textContent = 'Obteniendo ubicación… Acepta el permiso del navegador.';
      const position = await new Promise((resolve,reject) => navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:true,maximumAge:0,timeout:25000}));
      if (!navigator.onLine) throw new Error('Se perdió la conexión. No se ha enviado el marcado.');
      form.elements.latitude.value = position.coords.latitude;
      form.elements.longitude.value = position.coords.longitude;
      form.elements.accuracy.value = position.coords.accuracy;
      form.elements.challenge.value = challenge.token;
      status.textContent = 'Guardando marcado… Espera la confirmación.';
      HTMLFormElement.prototype.submit.call(form);
    } catch (error) {
      const messages = {1:'No se autorizó la ubicación. Actívala en los permisos del navegador y vuelve a intentar.',2:'No pudimos obtener tu ubicación. Revisa el GPS o la ubicación del dispositivo.',3:'La ubicación tardó demasiado. Vuelve a intentar.'};
      status.textContent = messages[error.code] || (error.name === 'AbortError' ? 'El servidor no respondió. Revisa tu conexión.' : error.message || 'No se pudo registrar. Revisa tu historial antes de intentar nuevamente.');
      busy = false; button.disabled = false;
    }
  });
});
// A back-forward cache must not restore a private page after logout.
addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
