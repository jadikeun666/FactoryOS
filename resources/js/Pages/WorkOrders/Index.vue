<template>
  <div class="wo-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 1 — Job Shop Scheduler</p>
        <h1 class="page-title">Work Order</h1>
        <p class="page-subtitle">Daftar pesanan produksi beserta status pengerjaannya.</p>
      </div>
      <Link href="/work-orders/create" class="btn btn--primary">
        <Icon name="clipboard-list" size="14" /> Tambah Work Order
      </Link>
    </header>

    <div v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</div>
    <div v-if="flashError" class="flash flash--error">{{ flashError }}</div>

    <div class="filter-bar">
      <button
        v-for="status in statusFilters"
        :key="status.value"
        type="button"
        class="filter-tab"
        :class="{ 'filter-tab--active': currentStatus === status.value }"
        @click="switchStatus(status.value)"
      >
        {{ status.label }}
      </button>
    </div>

    <div class="table-wrapper">
      <table class="wo-table">
        <thead>
          <tr>
            <th>WO</th>
            <th>Produk</th>
            <th class="num">Qty</th>
            <th>Due Date</th>
            <th class="num">Prioritas</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="wo in workOrders.data" :key="wo.id">
            <td class="wo-code">WO-{{ wo.id }}</td>
            <td>{{ wo.product?.name ?? '–' }}</td>
            <td class="num">{{ formatNumber(wo.qty) }}</td>
            <td>{{ formatDate(wo.due_date) }}</td>
            <td class="num">{{ wo.priority }}</td>
            <td>
              <span class="status-tag" :class="`status-tag--${wo.status}`">
                {{ statusLabel(wo.status) }}
              </span>
            </td>
            <td class="actions-col">
              <Link :href="`/work-orders/${wo.id}`" class="link-action">Detail</Link>
            </td>
          </tr>
          <tr v-if="workOrders.data.length === 0">
            <td colspan="7" class="empty-row">Belum ada Work Order.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="workOrders.links && workOrders.links.length > 3" class="pagination" aria-label="Pagination">
      <template v-for="(link, idx) in workOrders.links" :key="idx">
        <Link
          v-if="link.url"
          :href="link.url"
          class="page-link"
          :class="{ 'page-link--active': link.active }"
          v-html="link.label"
        />
        <span v-else class="page-link page-link--disabled" v-html="link.label"></span>
      </template>
    </nav>
  </div>
</template>

<script setup>
/**
 * WorkOrders/Index.vue — daftar Work Order (US-01, US-02, FR-02).
 * @see app/Http/Controllers/WorkOrderController.php@index
 *
 * Props: workOrders (paginate hasil WorkOrder::with('product')),
 * filters ({ status }).
 *
 * MODERNISASI (2026-08-09): halaman ini sebelumnya TIDAK PERNAH ADA sama
 * sekali -- link sidebar ke /work-orders menghasilkan error "Page not
 * found" karena file Vue-nya belum pernah dibuat, meski route & controller
 * backend sudah lengkap sejak awal project. Dibangun dari nol mengikuti
 * pola desain Master Data CRUD yang sudah ada (WorkCenters/Index.vue).
 */
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const props = defineProps({
  workOrders: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)

const currentStatus = ref(props.filters?.status ?? '')

const statusFilters = [
  { value: '', label: 'Semua' },
  { value: 'draft', label: 'Draft' },
  { value: 'scheduled', label: 'Terjadwal' },
  { value: 'in_progress', label: 'Berjalan' },
  { value: 'done', label: 'Selesai' },
  { value: 'late', label: 'Terlambat' },
]

function switchStatus(status) {
  currentStatus.value = status
  router.get('/work-orders', status ? { status } : {}, { preserveState: true, replace: true })
}

function statusLabel(status) {
  return statusFilters.find((s) => s.value === status)?.label ?? status
}

function formatNumber(value) {
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<style scoped>
.wo-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
  font-family: var(--font-body);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.page-eyebrow {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--signal-amber);
  margin: 0 0 0.25rem;
}

.page-title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--data-ink);
  margin: 0;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: var(--data-ink-muted);
  margin: 0.35rem 0 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 1.1rem;
  font-family: var(--font-display);
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
}

.btn--primary {
  background: var(--signal-amber);
  color: #1C1F26;
}

.btn--primary:hover { filter: brightness(1.08); }

.flash {
  padding: 0.65rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
}

.flash--success {
  background: rgba(74, 155, 110, 0.12);
  border: 1px solid rgba(74, 155, 110, 0.4);
  color: var(--signal-green);
}

.flash--error {
  background: rgba(214, 69, 69, 0.12);
  border: 1px solid rgba(214, 69, 69, 0.4);
  color: var(--signal-red);
}

.filter-bar {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.25rem;
  background: var(--surface-steel);
  border: 1px solid var(--hairline);
  border-radius: 8px;
  width: fit-content;
}

.filter-tab {
  padding: 0.35rem 0.75rem;
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--data-ink-muted);
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.filter-tab:hover { background: var(--hairline-soft); }

.filter-tab--active {
  background: var(--panel-graphite);
  color: var(--signal-amber);
  box-shadow: 0 0 0 1px var(--hairline-border);
}

.table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--hairline-border);
  border-radius: 10px;
}

.wo-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.wo-table th,
.wo-table td {
  padding: 0.6rem 0.9rem;
  text-align: left;
  border-bottom: 1px solid var(--hairline-soft);
}

.wo-table thead th {
  background: var(--surface-steel);
  color: var(--data-ink-muted);
  font-weight: 600;
  font-size: 0.75rem;
}

.wo-table th.num,
.wo-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.wo-table td {
  color: var(--data-ink);
}

.wo-code {
  font-family: var(--font-display);
  font-weight: 600;
  color: var(--data-ink);
}

.status-tag {
  display: inline-block;
  padding: 0.15rem 0.55rem;
  border-radius: 4px;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  font-weight: 600;
}

.status-tag--draft { background: var(--hairline-soft); color: var(--data-ink-muted); }
.status-tag--scheduled { background: rgba(91, 141, 239, 0.18); color: #5B8DEF; }
.status-tag--in_progress { background: rgba(232, 163, 61, 0.18); color: var(--signal-amber); }
.status-tag--done { background: rgba(74, 155, 110, 0.18); color: var(--signal-green); }
.status-tag--late { background: rgba(214, 69, 69, 0.18); color: var(--signal-red); }

.actions-col {
  white-space: nowrap;
}

.link-action {
  font-size: 0.75rem;
  font-weight: 600;
  color: #5B8DEF;
  text-decoration: none;
}

.link-action:hover { text-decoration: underline; }

.empty-row {
  text-align: center;
  color: var(--data-ink-muted);
  padding: 2rem;
}

.pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.page-link {
  padding: 0.35rem 0.65rem;
  font-family: var(--font-display);
  font-size: 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--hairline-border);
  color: var(--data-ink-muted);
  text-decoration: none;
  background: var(--panel-graphite);
}

.page-link:hover { background: var(--surface-steel); }

.page-link--active {
  background: var(--signal-amber);
  color: #1C1F26;
  border-color: var(--signal-amber);
}

.page-link--disabled {
  color: var(--hairline-strong);
  cursor: not-allowed;
}
</style>
