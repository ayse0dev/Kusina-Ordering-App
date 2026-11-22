import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Live-updates connection (Laravel Reverb).
// The app key is public and is read at RUNTIME from a <meta> tag the server renders, so one build works on any host.
// Host/port/scheme default to the page's own address (the web server proxies /app to Reverb);
// set VITE_REVERB_* in .env only for local development, where Reverb runs on its own port.
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content;
const env = import.meta.env;

const secure = (env.VITE_REVERB_SCHEME ?? (window.location.protocol === 'https:' ? 'https' : 'http')) === 'https';
const port = Number(env.VITE_REVERB_PORT) || (secure ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: meta('reverb-key') || env.VITE_REVERB_APP_KEY,
    wsHost: env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: secure,
    enabledTransports: ['ws', 'wss'],
});
