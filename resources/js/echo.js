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
 * Echo (OeeGauge.vue) tetap disiapkan penuh siap pakai -- begitu Soketi
 * diaktifkan di sesi lain (isi VITE_PUSHER_* di .env + npx soketi start),
 * tidak perlu ubah kode Vue sama sekali.
 */

// Helper: fallback ke default kalau env var undefined ATAU string kosong.
// `??` saja tidak cukup -- nullish coalescing tidak menangkap "" (string
// kosong), yang sering terjadi kalau .env pakai sintaks interpolasi
// "${VAR}" yang gagal ter-resolve (Laravel .env bukan bash, tidak selalu
// expand seperti itu). Bug ini pernah membuat Echo diam-diam mencoba
// konek ke domain pusher.com asli alih-alih Soketi lokal.
function envOrDefault(value, fallback) {
    return value === undefined || value === null || value === '' ? fallback : value;
}

const wsHost = envOrDefault(import.meta.env.VITE_PUSHER_HOST, '127.0.0.1');
const wsPort = envOrDefault(import.meta.env.VITE_PUSHER_PORT, 6001);
const appKey = envOrDefault(import.meta.env.VITE_PUSHER_APP_KEY, null);

if (!appKey) {
    // Soketi/broadcasting belum dikonfigurasi di sesi ini. Jangan
    // inisialisasi Echo sama sekali -- lebih baik komponen menampilkan
    // status "Offline" secara sengaja (window.Echo undefined) daripada
    // diam-diam mencoba konek ke domain eksternal pusher.com.
    console.info('[FactoryOS] Echo tidak diinisialisasi: VITE_PUSHER_APP_KEY belum di-set. Ini normal selama Soketi belum diaktifkan.');
} else {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: appKey,
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS: envOrDefault(import.meta.env.VITE_PUSHER_SCHEME, 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        cluster: envOrDefault(import.meta.env.VITE_PUSHER_APP_CLUSTER, 'mt1'),
        disableStats: true,
    });
}

export default window.Echo;