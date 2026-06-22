import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export function createEchoClient(reverbConfig = {}) {
    if (!reverbConfig.key) {
        return null;
    }

    return new Echo({
        broadcaster: 'reverb',
        key: reverbConfig.key,
        wsHost: reverbConfig.host || window.location.hostname,
        wsPort: Number(reverbConfig.port || 8080),
        wssPort: Number(reverbConfig.port || 8080),
        forceTLS: String(reverbConfig.scheme || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
