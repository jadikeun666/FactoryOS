<template>
  <div class="alert-console">
    <div class="alert-console__header">
      <div class="alert-console__title-group">
        <span class="alert-console__led" :class="{ 'alert-console__led--active': visibleAlerts.length > 0 }"></span>
        <span class="alert-console__title">
          {{ visibleAlerts.length }} Material · {{ statusLabel(currentStatus) }}
        </span>
      </div>
      <div class="alert-console__filter">
        <button
          v-for="status in statusTabs"
          :key="status.value"
          type="button"
          class="filter-tab"
          :class="{ 'filter-tab--active': currentStatus === status.value }"
          @click="switchStatus(status.value)"
        >
          {{ status.label }}
        </button>
      </div>
    </div>

    <ul v-if="visibleAlerts.length > 0" class="alert-log">
      <li v-for="alert in visibleAlerts" :key="alert.id" class="alert-row">
        <span class="alert-row__indicator" :class="`alert-row__indicator--${alert.status}`"></span>
        <span class="alert-row__material">{{ alert.material?.name ?? `Material #${alert.material_id}` }}</span>
        <span class="alert-row__figures">
          <span class="figure"><span class="figure__label">STOK</span><span class="figure__value">{{ formatNumber(alert.current_qty) }}</span></span>
          <span class="figure"><span class="figure__label">ROP</span><span class="figure__value">{{ formatNumber(alert.rop_qty) }}</span></span>
          <span class="figure"><span class="figure__label">EOQ</span><span class="figure__value">{{ formatNumber(alert.eoq_qty) }}</span></span>
        </span>
        <span class="alert-row__actions">
          <button
            v-if="alert.status === 'open'"
            type="button"
            class="btn btn--ghost btn--small"
            :disabled="isUpdating === alert.id"
            @click="updateStatus(alert, 'acknowledged')"
          >
            Tandai Dilihat
          </button>
          <button
            v-if="alert.status === 'acknowledged'"
            type="button"
            class="btn btn--primary btn--small"
            :disabled="isUpdating === alert.id"
            @click="updateStatus(alert, 'ordered')"
          >
            PO Dibuat
          </button>
        </span>
      </li>
    </ul>

    <div v-else class="alert-log__empty">
      Tidak ada reorder alert untuk status "{{ statusLabel(currentStatus) }}".
    </div>
  </div>
</template>

<script setup>
/**
 * AlertBanner.vue — daftar reorder_alerts (FR-08).
 *
 * ASUMSI shape data (dari MrpController::alerts(), lihat app/Http/
 * Controllers/MrpController.php):
 *   [{ id, material_id, current_qty, rop_qty, eoq_qty, status,
 *      created_at, updated_at, material: { id, name, sku, unit, ... } }]
 *   Semua qty adalah string (cast decimal:4 di model ReorderAlert).
 *
 * PENTING: endpoint GET /mrp/alerts bersifat READ-ONLY, tidak memicu
 * pembuatan alert baru (itu tanggung jawab CheckReorderAlertsJob,
 * scheduled 06:00 via Laravel Scheduler). Komponen ini juga TIDAK
 * mengubah status alert langsung -- lihat catatan di bawah soal endpoint
 * update status yang belum ada di backend.
 *
 * UTANG TEKNIS DISENGAJA: tombol "Tandai Dilihat" / "PO Dibuat" memanggil
 * updateStatus() yang melakukan PATCH ke /mrp/alerts/{id}/status -- ENDPOINT
 * INI BELUM ADA DI BACKEND (MrpController hanya py run/show/alerts).
 * Tombol sengaja dibuat agar UI siap, tapi akan gagal 404 sampai endpoint
 * dibuat di sesi backend terpisah. Ini konsisten dengan pola yang sudah
 * ada di Compare.vue (tombol "Terapkan Jadwal" dibuat sebelum endpoint
 * apply() ada). TIDAK membuat endpoint baru di sini karena mengubah
 * status alert adalah keputusan bisnis (siapa yang boleh acknowledge/
 * order) yang di luar scope "frontend MRP" murni -- perlu didiskusikan
 * terpisah (Policy? role apa yang boleh?).
 *
 * FIX BUG (2026-08-09): filter tab (Terbuka/Dilihat/Dipesan) SEBELUMNYA
 * ada di dalam kondisi v-if="visibleAlerts.length > 0" -- begitu tab aktif
 * kosong, seluruh komponen pindah ke cabang v-else yang tidak punya tab
 * sama sekali, sehingga user terjebak tanpa cara pindah tab. Fix: filter
 * tab dipindah ke luar percabangan v-if/v-else, selalu tampil.
 *
 * REDESIGN VISUAL (2026-08-09): dari pola card-pill generik ke tampilan
 * "log console" -- baris data dengan hairline divider dan indikator LED
 * persegi kecil, selaras dengan signature tick-mark dial di OeeGauge.vue.
 * TIDAK ADA perubahan logic JS selain restrukturisasi template di atas.
 */
import { ref, computed, watch } from 'vue'

const props = defineProps({
  initialAlerts: { type: Array, default: () => [] },
  alertsUrl: { type: String, default: '/mrp/alerts' },
})

const alerts = ref(props.initialAlerts)
const currentStatus = ref('open')
const isUpdating = ref(null)

watch(() => props.initialAlerts, (val) => {
  alerts.value = val
})


const statusTabs = [
  { value: 'open', label: 'Terbuka' },
  { value: 'acknowledged', label: 'Dilihat' },
  { value: 'ordered', label: 'Dipesan' },
]

const visibleAlerts = computed(() =>
  alerts.value.filter((a) => a.status === currentStatus.value)
)

function statusLabel(status) {
  return statusTabs.find((s) => s.value === status)?.label ?? status
}

function formatNumber(value) {
  if (value === null || value === undefined) return '–'
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })
}

async function switchStatus(status) {
  currentStatus.value = status
  try {
    const response = await fetch(`${props.alertsUrl}?status=${status}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) throw new Error(`Gagal memuat alert (${response.status})`)
    const fresh = await response.json()
    // Gabungkan, jangan timpa seluruhnya -- supaya tab lain yg sudah
    // dimuat sebelumnya tidak hilang dari state lokal.
    const others = alerts.value.filter((a) => a.status !== status)
    alerts.value = [...others, ...fresh]
  } catch (error) {
    console.error('AlertBanner: gagal fetch status', status, error)
  }
}

// Placeholder -- lihat catatan UTANG TEKNIS DISENGAJA di atas.
async function updateStatus(alert, newStatus) {
  isUpdating.value = alert.id
  try {
    const response = await fetch(`/mrp/alerts/${alert.id}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ status: newStatus }),
    })
    if (!response.ok) throw new Error(`Gagal update status (${response.status})`)
    const updated = await response.json()
    alerts.value = alerts.value.map((a) => (a.id === updated.id ? updated : a))
  } catch (error) {
    console.error('AlertBanner: gagal update status alert', alert.id, error)
  } finally {
    isUpdating.value = null
  }
}
</script>

<style scoped>
.alert-console {
  display: flex;
  flex-direction: column;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 6px;
  font-family: var(--font-body);
  overflow: hidden;
}

.alert-console__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.6rem;
  padding: 0.85rem 1.1rem;
  border-bottom: 1px solid var(--hairline-border);
  background: var(--surface-steel);
}

.alert-console__title-group {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.alert-console__led {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 2px;
  background: var(--hairline-strong);
  flex-shrink: 0;
}

.alert-console__led--active {
  background: var(--signal-amber);
  box-shadow: 0 0 6px 1px rgba(232, 163, 61, 0.6);
  animation: led-pulse 2s ease-in-out infinite;
}

@keyframes led-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

@media (prefers-reduced-motion: reduce) {
  .alert-console__led--active { animation: none; }
}

.alert-console__title {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--data-ink);
}

.alert-console__filter {
  display: inline-flex;
  gap: 0.15rem;
}

.filter-tab {
  padding: 0.3rem 0.65rem;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--data-ink-muted);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 4px;
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.filter-tab:hover {
  color: var(--data-ink);
}

.filter-tab--active {
  color: var(--signal-amber);
  border-color: var(--hairline-border);
}

.alert-log {
  list-style: none;
  margin: 0;
  padding: 0;
}

.alert-row {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  align-items: center;
  gap: 0.9rem;
  padding: 0.65rem 1.1rem;
  border-bottom: 1px solid var(--hairline-soft);
}

.alert-row:last-child {
  border-bottom: none;
}

.alert-row__indicator {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 1px;
  flex-shrink: 0;
}

.alert-row__indicator--open { background: var(--signal-red); }
.alert-row__indicator--acknowledged { background: var(--signal-amber); }
.alert-row__indicator--ordered { background: var(--signal-green); }

.alert-row__material {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--data-ink);
}

.alert-row__figures {
  display: flex;
  gap: 1rem;
}

.figure {
  display: flex;
  align-items: baseline;
  gap: 0.3rem;
}

.figure__label {
  font-family: var(--font-display);
  font-size: 0.625rem;
  letter-spacing: 0.04em;
  color: var(--data-ink-muted);
}

.figure__value {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--data-ink);
  font-variant-numeric: tabular-nums;
}

.alert-row__actions {
  display: flex;
  gap: 0.4rem;
}

.alert-log__empty {
  padding: 1.75rem 1.1rem;
  text-align: center;
  color: var(--data-ink-muted);
  font-size: 0.8125rem;
}

.btn {
  border-radius: 4px;
  border: 1px solid var(--hairline-border);
  cursor: pointer;
  font-family: var(--font-display);
  font-weight: 600;
  letter-spacing: 0.02em;
}

.btn--small {
  padding: 0.3rem 0.6rem;
  font-size: 0.6875rem;
}

.btn--ghost {
  background: transparent;
  color: var(--data-ink-muted);
}

.btn--ghost:hover:not(:disabled) { background: var(--surface-steel); color: var(--data-ink); }

.btn--primary {
  background: var(--signal-amber);
  border-color: var(--signal-amber);
  color: #1C1F26;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
