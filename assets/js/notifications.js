// Lightweight browser notifications helper
// Usage:
//   await Notifications.requestPermissionOnce();
//   Notifications.show({ title: 'New message', body: 'Text', icon: '/smart_waste/assets/collector.png', onclick: () => {...} });

window.Notifications = (function() {
	function isSupported() {
		return 'Notification' in window;
	}

	async function requestPermissionOnce() {
		if (!isSupported()) return false;
		if (Notification.permission === 'granted') return true;
		if (Notification.permission === 'denied') return false;
		const perm = await Notification.requestPermission();
		return perm === 'granted';
	}

	function show(opts) {
		if (!isSupported() || Notification.permission !== 'granted') return null;
		const n = new Notification(opts.title || 'Notification', {
			body: opts.body || '',
			icon: opts.icon || undefined,
			badge: opts.badge || undefined,
			data: opts.data || undefined
		});
		if (typeof opts.onclick === 'function') {
			n.onclick = function(event) {
				event.preventDefault();
				try { opts.onclick(event); } catch (_) {}
				n.close();
			};
		}
		return n;
	}

	return { isSupported, requestPermissionOnce, show };
})();


