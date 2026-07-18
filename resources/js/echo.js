import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Konfigurasi Echo untuk Soketi (self-hosted, Pusher-compatible protocol).
 * @see docs/architecture.md § WebSocket Flow
 * @see docs/oee-formulas.md § Real-time Update Flow
 *
 * CATATAN SESI INI: BROADCAST_CONNECTION masih 'log' di backend (lihat
 * claude.md), jadi Soketi belum benar-benar jalan. Komponen yang memakai
 * Echo (OeeGauge.vue) tetap disiapkan penuh siap pakai — begitu Soketi
 * diaktifkan di sesi lain, tidak perlu ubah kode Vue sama sekali.
 */
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY ?? 'factoryos-key',
    wsHost: import.meta.env.VITE_PUSHER_HOST ?? '127.0.0.1',
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    disableStats: true,
});

export default window.Echo;