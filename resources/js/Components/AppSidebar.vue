<template>
  <div>
    <button
      type="button"
      class="sidebar-toggle"
      :aria-expanded="isOpen"
      aria-label="Buka menu navigasi"
      @click="isOpen = true"
    >
      <Icon name="menu" size="18" />
    </button>

    <div v-if="isOpen" class="sidebar-backdrop" @click="isOpen = false"></div>

    <aside class="sidebar" :class="{ 'sidebar--open': isOpen }">
      <div class="sidebar__brand">
        <span class="sidebar__brand-dot"></span>
        <span class="sidebar__brand-text">FactoryOS</span>
        <button type="button" class="sidebar__close" aria-label="Tutup menu" @click="isOpen = false">
          <Icon name="x" size="16" />
        </button>
      </div>

      <nav class="sidebar__nav">
        <Link href="/dashboard" class="nav-link nav-link--top" :class="{ 'nav-link--active': isActive('/dashboard', true) }">
          <Icon name="layout-dashboard" size="15" />
          <span>Dashboard</span>
        </Link>

        <div class="nav-group">
          <span class="nav-group__label">Engine 1 — Scheduler</span>
          <Link href="/work-orders" class="nav-link" :class="{ 'nav-link--active': isActive('/work-orders') }">
            <Icon name="clipboard-list" size="15" />
            <span>Work Order</span>
          </Link>
          <Link href="/schedules/compare" class="nav-link" :class="{ 'nav-link--active': isActive('/schedules') }">
            <Icon name="activity" size="15" />
            <span>Perbandingan Jadwal</span>
          </Link>
        </div>

        <div class="nav-group">
          <span class="nav-group__label">Engine 2 — OEE</span>
          <Link href="/production-logs" class="nav-link" :class="{ 'nav-link--active': isActive('/production-logs') }">
            <Icon name="clipboard-list" size="15" />
            <span>Log Produksi</span>
          </Link>
          <Link href="/oee/dashboard" class="nav-link" :class="{ 'nav-link--active': isActive('/oee') }">
            <Icon name="activity" size="15" />
            <span>OEE Dashboard</span>
          </Link>
        </div>

        <div class="nav-group">
          <span class="nav-group__label">Engine 3 — Inventory</span>
          <Link href="/mrp" class="nav-link" :class="{ 'nav-link--active': isActive('/mrp') }">
            <Icon name="package" size="15" />
            <span>Dashboard MRP</span>
          </Link>
        </div>

        <div class="nav-group">
          <span class="nav-group__label">Master Data</span>
          <Link href="/work-centers" class="nav-link" :class="{ 'nav-link--active': isActive('/work-centers') }">
            <Icon name="database" size="15" />
            <span>Work Center</span>
          </Link>
          <Link href="/materials" class="nav-link" :class="{ 'nav-link--active': isActive('/materials') }">
            <Icon name="database" size="15" />
            <span>Material</span>
          </Link>
          <Link href="/products" class="nav-link" :class="{ 'nav-link--active': isActive('/products') }">
            <Icon name="database" size="15" />
            <span>Produk</span>
          </Link>
        </div>
      </nav>

      <div class="sidebar__footer" v-if="user">
        <div class="user-chip">
          <span class="user-chip__avatar">{{ userInitial }}</span>
          <div class="user-chip__info">
            <span class="user-chip__name">{{ user.name }}</span>
            <span class="user-chip__role">{{ roleLabel }}</span>
          </div>
        </div>
        <Link href="/logout" method="post" as="button" class="logout-btn" title="Keluar">
          <Icon name="log-out" size="15" />
        </Link>
      </div>
    </aside>
  </div>
</template>

<script setup>
/**
 * AppSidebar.vue — navigasi global sidebar kiri (2026-08-09). Di-mount
 * TERPISAH dari tree Inertia langsung di inertia-app.js, mengikuti pola
 * ThemeToggle.vue -- karena project ini tidak punya folder Layouts/
 * (semua halaman standalone). Body diberi display:flex via app.css supaya
 * sidebar mendorong konten #app, bukan menimpa (overlay) di desktop.
 *
 * Deteksi halaman aktif: usePage().url dibandingkan prefix path setiap
 * link. exact=true untuk /dashboard (supaya tidak ikut aktif di path
 * lain yang kebetulan diawali sama).
 *
 * Info user (nama, role, logout) diambil dari shared prop Inertia
 * auth.user (HandleInertiaRequests::share(), sudah expose role sejak
 * sesi 2026-07-23).
 */
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const page = usePage()
const isOpen = ref(false)

const user = computed(() => page.props.auth?.user ?? null)
const userInitial = computed(() => (user.value?.name?.charAt(0) ?? '?').toUpperCase())
const roleLabel = computed(() => {
  const role = user.value?.role
  const labels = { admin: 'Admin', ppic: 'PPIC', operator: 'Operator', production_manager: 'Production Manager' }
  return labels[role] ?? role ?? '–'
})

function isActive(prefix, exact = false) {
  const url = page.url.split('?')[0]
  return exact ? url === prefix : (url === prefix || url.startsWith(prefix + '/'))
}
</script>

<style scoped>
.sidebar-toggle {
  display: none;
  position: fixed;
  top: 0.85rem;
  left: 0.85rem;
  z-index: 50;
  align-items: center;
  justify-content: center;
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 6px;
  border: 1px solid var(--hairline-border);
  background: var(--panel-graphite-raised);
  color: var(--data-ink);
  cursor: pointer;
}

.sidebar-backdrop {
  display: none;
}

.sidebar {
  position: sticky;
  top: 0;
  height: 100vh;
  width: 224px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: var(--panel-graphite);
  border-right: 1px solid var(--hairline-border);
  font-family: var(--font-body);
  z-index: 30;
}

.sidebar__brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1.1rem 1rem;
  border-bottom: 1px solid var(--hairline-border);
}

.sidebar__brand-dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 2px;
  background: var(--signal-green);
  box-shadow: 0 0 6px 1px rgba(74, 155, 110, 0.5);
  flex-shrink: 0;
}

.sidebar__brand-text {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--data-ink);
  letter-spacing: 0.01em;
}

.sidebar__close {
  display: none;
  margin-left: auto;
  border: none;
  background: none;
  color: var(--data-ink-muted);
  cursor: pointer;
}

.sidebar__nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.85rem 0.65rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.nav-group {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.nav-group__label {
  font-family: var(--font-display);
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--data-ink-muted);
  padding: 0.4rem 0.6rem 0.3rem;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.5rem 0.6rem;
  border-radius: 5px;
  border-left: 2px solid transparent;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--data-ink-muted);
  text-decoration: none;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.nav-link:hover {
  background: var(--surface-steel);
  color: var(--data-ink);
}

.nav-link--active {
  background: var(--surface-steel);
  border-left-color: var(--signal-amber);
  color: var(--signal-amber);
  font-weight: 600;
}

.nav-link--top {
  margin-bottom: 0.2rem;
  font-weight: 600;
}

.sidebar__footer {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.85rem 1rem;
  border-top: 1px solid var(--hairline-border);
}

.user-chip {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex: 1;
  min-width: 0;
}

.user-chip__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 999px;
  background: var(--signal-amber);
  color: #1C1F26;
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.user-chip__info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-chip__name {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--data-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-chip__role {
  font-family: var(--font-display);
  font-size: 0.625rem;
  color: var(--data-ink-muted);
}

.logout-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 5px;
  border: 1px solid var(--hairline-border);
  background: transparent;
  color: var(--data-ink-muted);
  cursor: pointer;
  flex-shrink: 0;
}

.logout-btn:hover {
  background: rgba(214, 69, 69, 0.12);
  border-color: rgba(214, 69, 69, 0.4);
  color: var(--signal-red);
}

@media (max-width: 900px) {
  .sidebar-toggle {
    display: flex;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    transform: translateX(-100%);
    transition: transform 0.2s ease;
  }

  .sidebar--open {
    transform: translateX(0);
  }

  .sidebar__close {
    display: block;
  }

  .sidebar-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 25;
  }
}
</style>
