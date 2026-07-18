<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 2 — OEE &amp; Downtime</p>
        <h1 class="page-title">
          Log #{{ productionLog.id }}
          <span class="status-badge" :class="productionLog.is_validated ? 'status-badge--validated' : 'status-badge--draft'">
            {{ productionLog.is_validated ? 'Tervalidasi' : 'Draft' }}
          </span>
        </h1>
        <p class="page-subtitle">
          {{ productionLog.work_center?.name ?? '–' }} · {{ productionLog.shift?.name ?? '–' }} · {{ formatDate(productionLog.log_date) }}
        </p>
      </div>
      <div class="header-actions">
        <Link href="/production-logs" class="btn btn--ghost">← Daftar</Link>
        <Link v-if="!productionLog.is_validated" :href="editUrl" class="btn btn--ghost">Edit</Link>
        <button v-if="!productionLog.is_validated" type="button" class="btn btn--primary" @click="validateLog">
          Validasi
        </button>
      </div>
    </header>

    <section class="metrics-grid">
      <div class="metric-card">
        <span class="metric-label">Planned</span>
        <span class="metric-value">{{ formatNumber(productionLog.planned_minutes) }} mnt</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Downtime</span>
        <span class="metric-value">{{ formatNumber(productionLog.downtime_minutes) }} mnt</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Actual Output</span>
        <span class="metric-value">{{ formatNumber(productionLog.actual_output) }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Good Output</span>
        <span class="metric-value">{{ formatNumber(productionLog.good_output) }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">Ideal Cycle Time</span>
        <span class="metric-value">{{ formatNumber(productionLog.ideal_cycle_time_minutes, 6) }} mnt/unit</span>
      </div>
    </section>

    <section class="downtime-section">
      <div class="section-header">
        <h2 class="section-title">Downtime Events</h2>
        <button
          v-if="!productionLog.is_validated"
          type="button"
          class="btn btn--ghost btn--sm"
          @click="showAddForm = !showAddForm"
        >
          {{ showAddForm ? 'Batal' : '+ Tambah Downtime' }}
        </button>
      </div>

      <form v-if="showAddForm" class="add-downtime-form" @submit.prevent="submitDowntime">
        <label class="field field--compact">
          <span class="field-label">Kategori *</span>
          <select v-model="downtimeForm.reason_category" class="input">
            <option value="" disabled>Pilih kategori</option>
            <option value="breakdown">Breakdown</option>
            <option value="setup">Setup</option>
            <option value="material">Material</option>
            <option value="operator">Operator</option>
            <option value="other">Lainnya</option>
          </select>
        </label>

        <label class="field field--compact">
          <span class="field-label">Durasi (menit) *</span>
          <input v-model.number="downtimeForm.duration_minutes" type="number" step="0.01" min="0.01" class="input" />
        </label>

        <label class="field field--compact">
          <span class="field-label">Mulai *</span>
          <input v-model="downtimeForm.started_at" type="datetime-local" class="input" />
        </label>

        <label class="field field--grow">
          <span class="field-label">Keterangan</span>
          <input v-model="downtimeForm.reason_detail" type="text" maxlength="255" class="input" placeholder="opsional" />
        </label>

        <button type="submit" class="btn btn--primary btn--sm" :disabled="downtimeForm.processing">
          {{ downtimeForm.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </form>

      <p v-if="downtimeEvents.length === 0" class="downtime-empty">Belum ada downtime dicatat.</p>

      <table v-else class="downtime-table">
        <thead>
          <tr>
            <th>Kategori</th>
            <th>Keterangan</th>
            <th class="num">Durasi</th>
            <th>Mulai</th>
            <th v-if="!productionLog.is_validated" class="actions-col"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="event in downtimeEvents" :key="event.id">
            <td>
              <span class="category-tag" :class="`category-tag--${event.reason_category}`">
                {{ categoryLabel(event.reason_category) }}
              </span>
            </td>
            <td>{{ event.reason_detail || '–' }}</td>
            <td class="num">{{ formatNumber(event.duration_minutes) }} mnt</td>
            <td>{{ formatDateTime(event.started_at) }}</td>
            <td v-if="!productionLog.is_validated" class="actions-col">
              <button type="button" class="link-action link-action--danger" @click="removeDowntime(event.id)">
                Hapus
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="!productionLog.is_validated" class="danger-zone">
      <h2 class="section-title">Zona Berbahaya</h2>
      <p class="danger-text">Log yang dihapus tidak bisa dikembalikan.</p>
      <button type="button" class="btn btn--danger" @click="destroyLog">Hapus Log Ini</button>
    </section>
  </div>
</template>

<script setup>
/**
 * ASUMSI: ProductionLogController@show merender:
 *   productionLog: model dengan relasi workCenter, shift, downtimeEvents ter-load
 *
 * Aksi Edit/Validasi/Hapus disembunyikan di UI jika is_validated=true (mencerminkan
 * immutability rule di docs/engineering-rules.md § 2), tapi enforcement
 * sesungguhnya tetap di ProductionLogPolicy sisi server.
 */
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  productionLog: { type: Object, required: true },
})

const downtimeEvents = computed(() => props.productionLog.downtime_events ?? [])
const editUrl = computed(() => `/production-logs/${props.productionLog.id}/edit`)

const showAddForm = ref(false)

const downtimeForm = useForm({
  reason_category: '',
  reason_detail: '',
  duration_minutes: null,
  started_at: '',
})

function submitDowntime() {
  downtimeForm.post(`/production-logs/${props.productionLog.id}/downtime-events`, {
    preserveScroll: true,
    onSuccess: () => {
      downtimeForm.reset()
      showAddForm.value = false
    },
  })
}

function removeDowntime(downtimeEventId) {
  if (!confirm('Hapus downtime event ini?')) return
  router.delete(`/production-logs/${props.productionLog.id}/downtime-events/${downtimeEventId}`, {
    preserveScroll: true,
  })
}

function validateLog() {
  if (!confirm('Setelah divalidasi, log ini tidak bisa diedit lagi. Lanjutkan?')) return
  router.patch(`/production-logs/${props.productionLog.id}/validate`, {}, { preserveScroll: true })
}

function destroyLog() {
  if (!confirm('Yakin hapus log produksi ini? Tindakan tidak bisa dibatalkan.')) return
  router.delete(`/production-logs/${props.productionLog.id}`)
}

function categoryLabel(category) {
  const labels = {
    breakdown: 'Breakdown',
    setup: 'Setup',
    material: 'Material',
    operator: 'Operator',
    other: 'Lainnya',
  }
  return labels[category] ?? category
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

function formatDateTime(dateStr) {
  if (!dateStr) return '–'
  return new Date(dateStr).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function formatNumber(value, decimals = 2) {
  if (value === null || value === undefined) return '–'
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: decimals })
}
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1.5rem;
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.page-eyebrow {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #F59E0B;
  margin: 0 0 0.25rem;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 1.375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: #64748B;
  margin: 0.35rem 0 0;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.status-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.15rem 0.55rem;
  border-radius: 999px;
}

.status-badge--draft { background: #FEF3C7; color: #92400E; }
.status-badge--validated { background: #DCFCE7; color: #166534; }

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
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
}

.metric-label {
  font-size: 0.75rem;
  color: #64748B;
}

.metric-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.downtime-section,
.danger-zone {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  padding: 1.25rem;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.add-downtime-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.65rem;
  padding: 0.85rem;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.field--compact { min-width: 140px; }
.field--grow { flex: 1 1 200px; }

.field-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
}

.input {
  padding: 0.45rem 0.6rem;
  font-size: 0.8125rem;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  color: #0F172A;
}

.input:focus {
  outline: 2px solid #F59E0B;
  outline-offset: 1px;
}

.downtime-empty {
  font-size: 0.8125rem;
  color: #94A3B8;
}

.downtime-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.downtime-table th {
  text-align: left;
  padding: 0.55rem 0.7rem;
  background: #F8FAFC;
  color: #64748B;
  font-weight: 600;
  font-size: 0.6875rem;
  text-transform: uppercase;
  border-bottom: 1px solid #E5E7EB;
}

.downtime-table td {
  padding: 0.55rem 0.7rem;
  border-bottom: 1px solid #F1F5F9;
  color: #1E293B;
}

.downtime-table th.num,
.downtime-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.actions-col {
  width: 1%;
  white-space: nowrap;
  text-align: right;
}

.category-tag {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
  background: #F1F5F9;
  color: #475569;
}

.category-tag--breakdown { background: #FEE2E2; color: #B91C1C; }
.category-tag--setup { background: #FEF3C7; color: #92400E; }
.category-tag--material { background: #DBEAFE; color: #1D4ED8; }
.category-tag--operator { background: #EDE9FE; color: #6D28D9; }
.category-tag--other { background: #F1F5F9; color: #475569; }

.link-action {
  background: none;
  border: none;
  padding: 0;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
}

.link-action--danger { color: #DC2626; }

.danger-zone {
  border-color: #FCA5A5;
}

.danger-text {
  font-size: 0.8125rem;
  color: #7F1D1D;
  margin: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  transition: background-color 0.15s ease, transform 0.12s ease;
}

.btn:active { transform: translateY(1px); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn--sm { padding: 0.3rem 0.65rem; font-size: 0.75rem; }

.btn--primary { background: #0F172A; color: #F8FAFC; }
.btn--primary:hover:not(:disabled) { background: #1E293B; }

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
}
.btn--ghost:hover { background: #F1F5F9; }

.btn--danger {
  background: #FEF2F2;
  border-color: #FCA5A5;
  color: #DC2626;
  width: fit-content;
}
.btn--danger:hover { background: #FEE2E2; }
</style>