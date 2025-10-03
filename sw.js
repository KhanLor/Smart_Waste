// Service Worker for Smart Waste Management System
// Handles push notifications and offline functionality

const CACHE_NAME = 'smart-waste-v1';
const urlsToCache = [
  '/smart_waste/',
  '/smart_waste/assets/collector.png',
  '/smart_waste/assets/js/notifications.js'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});

// Push event - show notification when push message received
self.addEventListener('push', event => {
  let data = {};
  
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'Smart Waste', body: 'You have a new notification' };
  }

  const options = {
    body: data.body || 'New notification from Smart Waste',
    icon: data.icon || '/smart_waste/assets/collector.png',
    badge: '/smart_waste/assets/collector.png',
    data: data,
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/smart_waste/assets/collector.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss'
      }
    ],
    requireInteraction: true,
    tag: 'smart-waste-notification'
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Smart Waste', options)
  );
});

// Notification click event - handle user interaction
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  const data = event.notification && event.notification.data || {};

  if (event.action === 'view') {
    const target = data && data.schedule_id ? `/smart_waste/dashboard/collector/routes.php` : '/smart_waste/';
    event.waitUntil(clients.openWindow(target));
  } else if (event.action === 'dismiss') {
    // Just close the notification
    event.notification.close();
  } else {
    // Default action - open the site
    const target = data && data.schedule_id ? `/smart_waste/dashboard/collector/routes.php` : '/smart_waste/';
    event.waitUntil(clients.openWindow(target));
  }
});

// Background sync for offline functionality
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

async function doBackgroundSync() {
  // Handle any background sync tasks
  console.log('Background sync triggered');
}
