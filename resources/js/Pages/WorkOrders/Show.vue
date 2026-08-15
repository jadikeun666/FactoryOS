<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 1 — Job Shop Scheduler</p>
        <h1 class="page-title">
          WO-{{ workOrder.id }}
          <span class="status-tag" :class="`status-tag--${workOrder.status}`">
            {{ statusLabel(workOrder.status) }}
          </span>
        </h1>
        <p class="page-subtitle">{{ workOrder.product?.name ?? '–' }} ({{ workOrder.product?.sku ?? '–' }})</p>
      </div>
      <div class="header-actions">
        <Link href="/work-orders" class="btn btn--ghost"><Icon name="arrow-left" size="14" /> Daftar</Link>
        <Link v-if="canManage" :href="`/work-orders/${workOrder.id}/edit`" class="btn btn--ghost">Edit</Link>
      </div>
    </header>

    <div v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</div>
    <div v-if="flashError" class="flash flash--error">{{ flashError }}</div>

    <section class="metrics-grid">
      <div class="metric-card">
        <span class="metric-label">Qty</span>
        <span class="metric-value">{{ formatNumber(workOrder.qty) }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Release Date</span>
        <span class="metric-value metric-value--small">{{ formatDate(workOrder.release_date) }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Due Date</span>
        <span class="metric-value metric-value--small">{{ formatDate(workOrder.due_date) }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Prioritas</span>
        <span class="metric-value">{{ workOrder.priority }}</span>
      </div>
    </section>

    <section v-if="workOrder.notes" class="panel">
      <h2 class="panel-title">Catatan</h2>
      <p class="notes-text">{{ workOrder.notes }}</p>
    </section>

    <section v-if="canManage" class="panel">
      <h2 class="panel-title">Aksi</h2>
      <div class="action-row">
        <button
          v-for="target in availableTransitions"
          :key="target"
          type="button"
          class="btn btn--secondary"
          :disabled="isTransitioning"
          @click="transitionStatus(target)"
        >
          → {{ statusLabel(target) }}
        </button>
        <span v-if="availableTransitions.length === 0" class="no-transition-text">
          Tidak ada transisi status lanjutan dari status ini.
        </span>

        <button type="button" class="btn btn--ghost" :disabled="isRegenerating" @click="regenerateOperations">
          <Icon name="refresh-cw" size="14" :spin="isRegenerating" /> Regenerate Operasi
        </button>

        <button
          v-if="canDelete"
          type="button"
          class="btn btn--danger"
          @click="destroyWorkOrder"
        >
          <Icon name="x" size="14" /> Hapus WO
        </button>
      </div>
    </section>

    <section class="panel">
      <h2 class="panel-title">Operasi ({{ workOrder.operations?.length ?? 0 }})</h2>
      <table v-if="workOrder.operations?.length > 0" class="ops-table">
        <thead>
          <tr>
            <th class="num">Urutan</th>
            <th>Mesin</th>
            <th>Mulai Rencana</th>
            <th>Selesai Rencana</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="op in workOrder.operations" :key="op.id">
            <td class="num">{{ op.sequence }}</td>
            <td>{{ op.work_center?.name ?? '–' }} <span class="muted">({{ op.work_center?.code ?? '–' }})</span></td>
            <td>{{ formatDateTime(op.planned_start) }}</td>
            <td>{{ formatDateTime(op.planned_end) }}</td>
            <td>
              <span class="op-status-tag" :class="`op-status-tag--${op.status}`">
                {{ opStatusLabel(op.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="empty-note">
        Belum ada operasi tergenerate. Coba klik "Regenerate Operasi" di atas
        setelah memastikan Routing produk sudah lengkap.
      </p>
    </section>
  </div>
</template>

<script setup>
/**
 * WorkOrders/Show.vue — detail Work Order + transisi status + tabel
 * operasi (US-01, US-02, FR-02).
 * @see app/Http/Controllers/WorkOrderController.php@show,@updateStatus,@regenerateOperations
 * @see app/Services/WorkOrder/WorkOrderStatusService.php (matrix transisi,
 *      DIREPLIKASI persis di STATUS_TRANSITIONS di bawah untuk UI --
 *      backend tetap source of truth, ini murni supaya tombol yang
 *      ditampilkan tidak menawarkan transisi yang pasti ditolak server)
 *
 * Props: workOrder (dengan product, operations.workCenter ter-load).
 *
 * canManage: cek client-side murni untuk UI hiding (creator ATAU admin),
 * BUKAN pengganti WorkOrderPolicy -- backend tetap authorize() ulang di
 * setiap action. Pola sama dengan WorkCenters/Index.vue.
 */
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const props = defineProps({
  workOrder: { type: Object, required: true },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)

const currentUser = computed(() => page.props.auth?.user ?? null)
const canManage = computed(() =>
  currentUser.value && (currentUser.value.id === props.workOrder.created_by || currentUser.value.role === 'admin')
)
const canDelete = computed(() =>
  canManage.value && !['in_progress', 'done'].includes(props.workOrder.status)
)

const STATUS_TRANSITIONS = {
  draft: ['scheduled'],
  scheduled: ['in_progress', 'late', 'draft'],
  in_progress: ['done', 'late'],
  late: ['in_progress', 'done'],
  done: [],
}

const STATUS_LABELS = {
  draft: 'Draft',
  scheduled: 'Terjadwal',
  in_progress: 'Berjalan',
  done: 'Selesai',
  late: 'Terlambat',
}

const OP_STATUS_LABELS = {
  pending: 'Menunggu',
  running: 'Berjalan',
  done: 'Selesai',
  skipped: 'Dilewati',
}

const availableTransitions = computed(() => STATUS_TRANSITIONS[props.workOrder.status] ?? [])

function statusLabel(status) {
  return STATUS_LABELS[status] ?? status
}

function opStatusLabel(status) {
  return OP_STATUS_LABELS[status] ?? status
}

function formatNumber(value) {
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 })
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(dateStr) {
  if (!dateStr) return '–'
  return new Date(dateStr).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

const isTransitioning = ref(false)
function transitionStatus(target) {
  isTransitioning.value = true
  router.patch(`/work-orders/${props.workOrder.id}/status`, { status: target }, {
    preserveScroll: true,
    onFinish: () => { isTransitioning.value = false },
  })
}

const isRegenerating = ref(false)
function regenerateOperations() {
  isRegenerating.value = true
  router.post(`/work-orders/${props.workOrder.id}/regenerate-operations`, {}, {
    preserveScroll: true,
    onFinish: () => { isRegenerating.value = false },
  })
}

function destroyWorkOrder() {
  if (!window.confirm(`Hapus Work Order WO-${props.workOrder.id}? Aksi ini tidak bisa dibatalkan.`)) return
  router.delete(`/work-orders/${props.workOrder.id}`)
}
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 900px;
  margin: 0 auto;
  font-family: var(--font-body);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
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
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--data-ink);
  margin: 0;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: var(--data-ink-muted);
  margin: 0.35rem 0 0;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.status-tag {
  font-family: var(--font-display);
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.15rem 0.55rem;
  border-radius: 4px;
}

.status-tag--draft { background: var(--hairline-soft); color: var(--data-ink-muted); }
.status-tag--scheduled { background: rgba(91, 141, 239, 0.18); color: #5B8DEF; }
.status-tag--in_progress { background: rgba(232, 163, 61, 0.18); color: var(--signal-amber); }
.status-tag--done { background: rgba(74, 155, 110, 0.18); color: var(--signal-green); }
.status-tag--late { background: rgba(214, 69, 69, 0.18); color: var(--signal-red); }

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

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 0.75rem;
}

.metric-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 1rem;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 8px;
}

.metric-label {
  font-size: 0.75rem;
  color: var(--data-ink-muted);
}

.metric-value {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--data-ink);
  font-variant-numeric: tabular-nums;
}

.metric-value--small {
  font-size: 0.9375rem;
}

.panel {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 10px;
  padding: 1.25rem;
}

.panel-title {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--data-ink);
  margin: 0;
}

.notes-text {
  font-size: 0.8125rem;
  color: var(--data-ink);
  margin: 0;
  white-space: pre-wrap;
}

.action-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
}

.no-transition-text {
  font-size: 0.75rem;
  color: var(--data-ink-muted);
}

.ops-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.ops-table th,
.ops-table td {
  padding: 0.55rem 0.7rem;
  text-align: left;
  border-bottom: 1px solid var(--hairline-soft);
  color: var(--data-ink);
}

.ops-table thead th {
  color: var(--data-ink-muted);
  font-weight: 600;
  font-size: 0.6875rem;
  text-transform: uppercase;
}

.ops-table th.num,
.ops-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.muted {
  color: var(--data-ink-muted);
  font-size: 0.75rem;
}

.op-status-tag {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  font-weight: 600;
  background: var(--hairline-soft);
  color: var(--data-ink-muted);
}

.op-status-tag--running { background: rgba(232, 163, 61, 0.18); color: var(--signal-amber); }
.op-status-tag--done { background: rgba(74, 155, 110, 0.18); color: var(--signal-green); }
.op-status-tag--skipped { background: rgba(214, 69, 69, 0.18); color: var(--signal-red); }

.empty-note {
  font-size: 0.8125rem;
  color: var(--data-ink-muted);
  margin: 0;
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
  transition: background-color 0.15s ease;
}

.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.btn--ghost {
  background: var(--panel-graphite);
  border-color: var(--hairline-border);
  color: var(--data-ink-muted);
}

.btn--ghost:hover { background: var(--surface-steel); }

.btn--secondary {
  background: var(--surface-steel);
  border-color: var(--hairline-border);
  color: var(--data-ink);
}

.btn--secondary:hover:not(:disabled) { background: var(--hairline-soft); }

.btn--danger {
  background: rgba(214, 69, 69, 0.12);
  border-color: rgba(214, 69, 69, 0.4);
  color: var(--signal-red);
}

.btn--danger:hover { background: rgba(214, 69, 69, 0.2); }
</style>
