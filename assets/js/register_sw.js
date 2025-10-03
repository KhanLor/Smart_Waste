// Service Worker registration and push subscription management

// Register service worker
async function registerServiceWorker() {
    if (!'serviceWorker' in navigator) {
        console.error('Service workers not supported');
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('sw.js');
        console.log('Service Worker registered');
        return registration;
    } catch (error) {
        console.error('Service Worker registration failed:', error);
    }
}

// Subscribe to push notifications
async function subscribeToPush(registration) {
    // The VAPID public key is injected at runtime by the page into window.__VAPID_PUBLIC_KEY__
    const publicKey = window.__VAPID_PUBLIC_KEY__ || '';

    if (!publicKey) {
        console.error('VAPID public key not found on page (window.__VAPID_PUBLIC_KEY__)');
        return;
    }

    try {
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey)
        });

        // Send subscription to server
        await fetch('../push_subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subscription })
        });

        console.log('Push subscription successful');
    } catch (error) {
        if (Notification.permission === 'denied') {
            console.warn('Push notifications blocked');
        } else {
            console.error('Push subscription error:', error);
        }
    }
}

// Convert VAPID key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Initialize push notifications
async function initPushNotifications() {
    if (!'PushManager' in window) {
        console.warn('Push notifications not supported');
        return;
    }

    // Request notification permission
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return;

    // Register service worker and subscribe
    const registration = await registerServiceWorker();
    if (registration) {
        await subscribeToPush(registration);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only for logged-in residents
    if (document.body.classList.contains('role-resident')) {
        initPushNotifications();
    }
});
