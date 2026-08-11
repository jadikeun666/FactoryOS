<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 2 — OEE &amp; Downtime</p>
        <h1 class="page-title">Log Produksi</h1>
        <p class="page-subtitle">Riwayat log produksi per mesin, per shift, per hari.</p>
      </div>
      <Link :href="createUrl" class="btn btn--primary">+ Log Baru</Link>
    </header>

    <form class="filter-bar" @submit.prevent="applyFilters">
      <label class="filter-field">
        <span>Tanggal</span>
        <input v-model="localFilters.log_date" type="date" class="input" />
      </label>
      <button type="submit" class="btn btn--ghost">Terapkan</button>
      <button v-if="hasActiveFilter" type="button" class="btn btn--ghost" @click="clearFilters">
        Reset
      </button>
    </form>

    <div class="table-shell">
      <table class="log-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Mesin</th>
            <th>Shift</th>
            <th class="num">Planned</th>
            <th class="num">Downtime</th>
            <th class="num">Output</th>
            <th class="num">Good</th>
            <th>Status</th>
            <th class="actions-col"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="logs.data.length === 0">
            <td colspan="9" class="empty-row">Belum ada log produksi.</td>
          </tr>
          <tr v-for="log in logs.data" :key="log.id">
            <td>{{ formatDate(log.log_date) }}</td>
            <td>{{ log.work_center?.name ?? '–' }}</td>
            <td>{{ log.shift?.name ?? '–' }}</td>
            <td class="num">{{ formatNumber(log.planned_minutes) }}</td>
            <td class="num">{{ formatNumber(log.downtime_minutes) }}</td>
            <td class="num">{{ formatNumber(log.actual_output) }}</td>
            <td class="num">{{ formatNumber(log.good_output) }}</td>
            <td>
              <span class="status-badge" :class="log.is_validated ? 'status-badge--validated' : 'status-badge--draft'">
                {{ log.is_validated ? 'Tervalidasi' : 'Draft' }}
              </span>
            </td>
            <td class="actions-col">
              <Link :href="showUrl(log.id)" class="link-action">Detail</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="logs.links && logs.links.length > 3" class="pagination" aria-label="Pagination">
      <template v-for="(link, idx) in logs.links" :key="idx">
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
 * ASUMSI: ProductionLogController@index merender:
 *   logs:    hasil paginate() dari ProductionLog::with(['workCenter','shift']),
 *            diserialisasi Inertia sebagai { data: [...], links: [...], ... }
 *   filters: { work_center_id, log_date } (nilai filter yang sedang aktif)
 *
 * Filter mesin belum ditampilkan karena controller saat ini belum mengirim
 * daftar workCenters ke halaman index — hanya filter tanggal yang aktif.
 */
import { reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const createUrl = '/production-logs/create'

const localFilters = reactive({
  log_date: props.filters?.log_date ?? '',
})

const hasActiveFilter = computed(() => !!localFilters.log_date)

function applyFilters() {
  router.get('/production-logs', { ...localFilters }, { preserveState: true, replace: true })
}

function clearFilters() {
  localFilters.log_date = ''
  router.get('/production-logs', {}, { preserveState: true, replace: true })
}

function showUrl(id) {
  return `/production-logs/${id}`
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatNumber(value) {
  if (value === null || value === undefined) return '–'
  const n = Number(value)
  return n.toLocaleString('id-ID', { maximumFractionDigits: 2 })
}
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 1200px;
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
  padding: 0.5rem 1rem;
  font-family: var(--font-display);
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  transition: background-color 0.15s ease, transform 0.12s ease;
}

.btn:active { transform: translateY(1px); }

.btn--primary {
  background: var(--signal-amber);
  color: #1C1F26;
}

.btn--primary:hover { filter: brightness(1.08); }

.btn--ghost {
  background: var(--panel-graphite);
  border-color: var(--hairline-border);
  color: var(--data-ink-muted);
}

.btn--ghost:hover { background: var(--surface-steel); }

.filter-bar {
  display: flex;
  align-items: flex-end;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  color: var(--data-ink-muted);
}

.input {
  padding: 0.45rem 0.65rem;
  font-size: 0.8125rem;
  font-family: var(--font-body);
  border: 1px solid var(--hairline-border);
  border-radius: 6px;
  color: var(--data-ink);
  background: var(--panel-graphite);
}

.input:focus {
  outline: 2px solid var(--signal-amber);
  outline-offset: 1px;
}

.table-shell {
  overflow-x: auto;
  border: 1px solid var(--hairline-border);
  border-radius: 8px;
  background: var(--panel-graphite);
}

.log-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.log-table th {
  text-align: left;
  padding: 0.65rem 0.85rem;
  background: var(--surface-steel);
  color: var(--data-ink-muted);
  font-weight: 600;
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: 1px solid var(--hairline-border);
}

.log-table td {
  padding: 0.65rem 0.85rem;
  border-bottom: 1px solid var(--hairline-soft);
  color: var(--data-ink);
}

.log-table th.num,
.log-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.actions-col {
  width: 1%;
  white-space: nowrap;
  text-align: right;
}

.empty-row {
  text-align: center;
  color: var(--data-ink-muted);
  padding: 2rem;
}

.status-badge {
  display: inline-block;
  padding: 0.15rem 0.55rem;
  border-radius: 4px;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  font-weight: 600;
}

.status-badge--draft {
  background: rgba(232, 163, 61, 0.18);
  color: var(--signal-amber);
}

.status-badge--validated {
  background: rgba(74, 155, 110, 0.18);
  color: var(--signal-green);
}

.link-action {
  color: #5B8DEF;
  font-weight: 600;
  text-decoration: none;
}

.link-action:hover {
  text-decoration: underline;
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

.page-link:hover {
  background: var(--surface-steel);
}

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