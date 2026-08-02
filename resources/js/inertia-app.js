import './bootstrap';
import './echo';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ThemeToggle from './Components/ThemeToggle.vue';

createInertiaApp({
    title: (title) => (title ? `${title} — FactoryOS` : 'FactoryOS'),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);

        // Toggle tema di-mount terpisah dari tree halaman Inertia,
        // supaya muncul konsisten di semua halaman tanpa Layout bersama.
        const toggleRoot = document.createElement('div');
        toggleRoot.id = 'theme-toggle-root';
        document.body.appendChild(toggleRoot);
        createApp(ThemeToggle).mount(toggleRoot);
    },
});