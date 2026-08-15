import './bootstrap';
import './echo';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ThemeToggle from './Components/ThemeToggle.vue';
import AppSidebar from './Components/AppSidebar.vue';

createInertiaApp({
    title: (title) => (title ? `${title} — FactoryOS` : 'FactoryOS'),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);

        // Sidebar navigasi global di-mount TERPISAH dari tree Inertia,
        // sebagai sibling SEBELUM #app di DOM (bukan sesudah seperti
        // ThemeToggle) -- supaya body:flex mendorong #app ke kanan,
        // bukan menimpanya. Lihat AppSidebar.vue untuk detail.
        const sidebarRoot = document.createElement('div');
        sidebarRoot.id = 'sidebar-root';
        document.body.insertBefore(sidebarRoot, document.body.firstChild);
        createApp(AppSidebar).mount(sidebarRoot);

        // Toggle tema di-mount terpisah dari tree halaman Inertia,
        // supaya muncul konsisten di semua halaman tanpa Layout bersama.
        const toggleRoot = document.createElement('div');
        toggleRoot.id = 'theme-toggle-root';
        document.body.appendChild(toggleRoot);
        createApp(ThemeToggle).mount(toggleRoot);
    },
});
