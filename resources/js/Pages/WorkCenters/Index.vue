<template>
  <div class="wc-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Master Data</p>
        <h1 class="page-title">Work Center</h1>
        <p class="page-subtitle">Daftar mesin produksi beserta kapasitas dan status aktif.</p>
      </div>
      <Link v-if="canCreate" href="/work-centers/create" class="btn btn--primary">+ Tambah Mesin</Link>
    </header>

    <div v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</div>
    <div v-if="flashError" class="flash flash--error">{{ flashError }}</div>

    <div class="table-wrapper">
      <table class="wc-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th class="num">Kapasitas/Shift (menit)</th>
            <th class="num">Setup (menit)</th>
            <th>Status</th>
            <th v-if="canManage"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="wc in workCenters" :key="wc.id">
            <td class="wc-code">{{ wc.code }}</td>
            <td>{{ wc.name }}</td>
            <td class="num">{{ formatNumber(wc.capacity_per_shift_minutes) }}</td>
            <td class="num">{{ formatNumber(wc.setup_time_minutes) }}</td>
            <td>
              <button
                v-if="canManage"
                type="button"
                class="status-toggle"
                :class="wc.is_active ? 'status-toggle--active' : 'status-toggle--inactive'"
                @click="toggleActive(wc)"
              >
                {{ wc.is_active ? 'Aktif' : 'Nonaktif' }}
              </button>
              <span v-else class="status-tag" :class="wc.is_active ? 'status-tag--active' : 'status-tag--inactive'">
                {{ wc.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td v-if="canManage" class="actions-col">
              <Link :href="`/work-centers/${wc.id}/edit`" class="link-action">Edit</Link>
              <button type="button" class="link-action link-action--danger" @click="confirmDelete(wc)">Hapus</button>
            </td>
          </tr>
          <tr v-if="workCenters.length === 0">
            <td :colspan="canManage ? 6 : 5" class="empty-row">Belum ada Work Center.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
/**
 * WorkCenters/Index.vue — daftar Work Center (FR-01).
 * ASUMSI props dari WorkCenterController::index():
 *   workCenters: WorkCenter[] (semua kolom model)
 *
 * canCreate/canManage: TIDAK di-pass eksplisit dari controller (controller
 * hanya authorize() untuk aksi yang benar2 dieksekusi). Di sini kita pakai
 * $page.props.auth.user + role check client-side murni untuk UI hiding --
 * bukan pengganti authorize() backend, backend tetap jadi source of truth
 * (WorkCenterPolicy sudah authorize create/update/delete di controller).
 * Jika user non-admin memaksa akses langsung, backend akan menolak dengan
 * 403 walau tombol ini disembunyikan.
 */
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  workCenters: { type: Array, required: true },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)

// Sekarang role sudah di-share via HandleInertiaRequests -- cek nyata,
// bukan hardcode true. Tetap hanya untuk UI hiding; backend (WorkCenterPolicy)
// tetap source of truth otorisasi.
const canCreate = computed(() => page.props.auth?.user?.role === 'admin')
const canManage = computed(() => page.props.auth?.user?.role === 'admin')

function formatNumber(value) {
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })
}

function toggleActive(wc) {
  router.patch(`/work-centers/${wc.id}/toggle-active`, {}, { preserveScroll: true })
}

function confirmDelete(wc) {
  if (!window.confirm(`Hapus Work Center "${wc.name}"? Aksi ini tidak bisa dibatalkan.`)) return
  router.delete(`/work-centers/${wc.id}`, { preserveScroll: true })
}
</script>

<style scoped>
.wc-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
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
  font-size: 1.5rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: #64748B;
  margin: 0.35rem 0 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  padding: 0.55rem 1.1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
}

.btn--primary {
  background: #0F172A;
  color: #F8FAFC;
}

.btn--primary:hover {
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.25);
}

.flash {
  padding: 0.65rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
}

.flash--success {
  background: #F0FDF4;
  border: 1px solid #BBF7D0;
  color: #15803D;
}

.flash--error {
  background: #FEF2F2;
  border: 1px solid #FECACA;
  color: #B91C1C;
}

.table-wrapper {
  overflow-x: auto;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.wc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.wc-table th,
.wc-table td {
  padding: 0.6rem 0.9rem;
  text-align: left;
  border-bottom: 1px solid #F1F5F9;
}

.wc-table thead th {
  background: #F8FAFC;
  color: #64748B;
  font-weight: 600;
  font-size: 0.75rem;
}

.wc-table th.num,
.wc-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.wc-code {
  font-family: monospace;
  font-weight: 600;
  color: #334155;
}

.status-toggle {
  padding: 0.2rem 0.65rem;
  font-size: 0.6875rem;
  font-weight: 700;
  border-radius: 999px;
  border: none;
  cursor: pointer;
  text-transform: uppercase;
}

.status-toggle--active {
  background: #DCFCE7;
  color: #15803D;
}

.status-toggle--inactive {
  background: #F1F5F9;
  color: #64748B;
}

.status-tag {
  padding: 0.2rem 0.65rem;
  font-size: 0.6875rem;
  font-weight: 700;
  border-radius: 999px;
  text-transform: uppercase;
}

.status-tag--active { background: #DCFCE7; color: #15803D; }
.status-tag--inactive { background: #F1F5F9; color: #64748B; }

.actions-col {
  display: flex;
  gap: 0.6rem;
  white-space: nowrap;
}

.link-action {
  font-size: 0.75rem;
  font-weight: 600;
  color: #2563EB;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  text-decoration: none;
}

.link-action:hover {
  text-decoration: underline;
}

.link-action--danger {
  color: #DC2626;
}

.empty-row {
  text-align: center;
  color: #94A3B8;
  padding: 2rem;
}
</style>