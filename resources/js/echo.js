import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

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
