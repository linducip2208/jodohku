import './bootstrap';
import './echo';
import Alpine from 'alpinejs';

// Bundled Alpine (offline/PWA-safe). The layout keeps the CDN tag only as a
// fallback when the built bundle is missing (e.g. assets not yet built).
window.Alpine = window.Alpine ?? Alpine;
if (!window.Alpine.__jodohkuStarted) {
    window.Alpine.__jodohkuStarted = true;
    try {
        window.Alpine.start();
    } catch (e) {
        // CDN fallback will start it instead.
    }
}
