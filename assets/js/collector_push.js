async function registerServiceWorkerCollector() {
    if (!('serviceWorker' in navigator)) return null;
    try { return await navigator.serviceWorker.register('../../sw.js'); } catch { return null; }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}

async function enableCollectorPush(vapidKey) {
    if (!('PushManager' in window)) return false;
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return false;
    const reg = await registerServiceWorkerCollector();
    if (!reg) return false;
    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(vapidKey) });
    await fetch('../../push_subscribe.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ subscription: sub }) });
    return true;
}

window.initCollectorPush = async function(vapidKey) {
    try { await enableCollectorPush(vapidKey); } catch (e) { console.error('Push init failed', e); }
}


