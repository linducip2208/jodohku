import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY ?? 'local';
const host = import.meta.env.VITE_REVERB_HOST ?? 'localhost';
const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

window.dispatchEchoMessage = function (conversationId, message) {
    window.dispatchEvent(new CustomEvent('echo:message-received', { detail: { conversationId, message } }));
};

if (window.Echo && window.Echo.connector) {
    window.Echo.connector.pusher.connection.bind('connected', () => {
        window.dispatchEvent(new CustomEvent('echo:connected'));
    });
}
