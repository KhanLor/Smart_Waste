// Minimal tracking helper (not yet used directly; logic is embedded in tracking.php)
window.Tracking = {
    createMarker(map, latlng, name) {
        return L.marker(latlng).bindPopup(name).addTo(map);
    }
};
