import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { TOKEN } from './api';

window.Pusher = Pusher;
const env = import.meta.env;

export const echo = new Echo({
  broadcaster: 'reverb',
  key: env.VITE_REVERB_APP_KEY,
  wsHost: env.VITE_REVERB_HOST,
  wsPort: Number(env.VITE_REVERB_PORT ?? 80),
  wssPort: Number(env.VITE_REVERB_PORT ?? 443),
  forceTLS: env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: env.VITE_AUTH_ENDPOINT,
  auth: { headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' } },
});
