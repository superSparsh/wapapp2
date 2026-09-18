import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const INVALID_WS_HOSTS = new Set(['', '0.0.0.0', '127.0.0.1', 'localhost', '::1']);

function resolveReverbConfig() {
    const runtime = window.__REVERB__ && typeof window.__REVERB__ === 'object' ? window.__REVERB__ : {};
    const pageHost = window.location.hostname;
    const pageSecure = window.location.protocol === 'https:';
    const bakedHost = String(import.meta.env.VITE_REVERB_HOST || '');
    const bakedScheme = String(import.meta.env.VITE_REVERB_SCHEME || 'https');
    const bakedKey = String(import.meta.env.VITE_REVERB_APP_KEY || '');

    let host = String(runtime.host || bakedHost || pageHost).trim();
    if (INVALID_WS_HOSTS.has(host)) {
        host = pageHost;
    }

    const scheme = String(runtime.scheme || (pageSecure ? 'https' : bakedScheme) || 'http');
    const forceTLS = pageSecure || scheme === 'https';
    const sameOrigin = host === pageHost;
    const defaultPort = forceTLS ? 443 : 80;
    let port = Number(runtime.port || import.meta.env.VITE_REVERB_PORT || defaultPort);

    // Same-origin HTTPS is proxied by Apache on 443 — never talk to 0.0.0.0:8080.
    if (sameOrigin && pageSecure) {
        port = 443;
    } else if (!Number.isFinite(port) || port <= 0) {
        port = defaultPort;
    }

    return {
        enabled: runtime.enabled === true && Boolean(runtime.key || bakedKey),
        key: String(runtime.key || bakedKey),
        host,
        port,
        forceTLS,
    };
}

const reverb = resolveReverbConfig();

if (reverb.enabled && reverb.key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: reverb.forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        },
    });
}

export default window.Echo;
