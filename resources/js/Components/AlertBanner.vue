<template>
  <div v-if="visibleAlerts.length > 0" class="alert-banner">
    <div class="alert-banner__header">
      <span class="alert-banner__title">
        ⚠️ {{ visibleAlerts.length }} Material Perlu Perhatian
      </span>
      <div class="alert-banner__filter">
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

    <ul class="alert-list">
      <li v-for="alert in visibleAlerts" :key="alert.id" class="alert-item">
        <div class="alert-item__main">
          <span class="alert-item__material">{{ alert.material?.name ?? `Material #${alert.material_id}` }}</span>
          <span class="status-tag" :class="`status-tag--${alert.status}`">
            {{ statusLabel(alert.status) }}
          </span>
        </div>
        <div class="alert-item__figures">
          <span>Stok: <strong>{{ formatNumber(alert.current_qty) }}</strong></span>
          <span>ROP: <strong>{{ formatNumber(alert.rop_qty) }}</strong></span>
          <span>EOQ: <strong>{{ formatNumber(alert.eoq_qty) }}</strong></span>
        </div>
        <div class="alert-item__actions">
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
        </div>
      </li>
    </ul>
  </div>

  <div v-else class="alert-banner alert-banner--empty">
    ✅ Tidak ada reorder alert untuk status "{{ statusLabel(currentStatus) }}".
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
.alert-banner {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.1rem 1.25rem;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 10px;
}

.alert-banner--empty {
  background: #F0FDF4;
  border-color: #BBF7D0;
  color: #15803D;
  font-size: 0.8125rem;
  text-align: center;
  padding: 1rem;
}

.alert-banner__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.alert-banner__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #92400E;
}

.alert-banner__filter {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.2rem;
  background: rgba(255, 255, 255, 0.6);
  border-radius: 8px;
}

.filter-tab {
  padding: 0.3rem 0.7rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #92400E;
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.filter-tab--active {
  background: #92400E;
  color: #FFFBEB;
}

.alert-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.alert-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.6rem;
  padding: 0.6rem 0.8rem;
  background: #FFFFFF;
  border: 1px solid #FDE68A;
  border-radius: 8px;
}

.alert-item__main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 160px;
}

.alert-item__material {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #0F172A;
}

.status-tag {
  font-size: 0.625rem;
  font-weight: 700;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
}

.status-tag--open { background: #FEE2E2; color: #B91C1C; }
.status-tag--acknowledged { background: #FEF3C7; color: #92400E; }
.status-tag--ordered { background: #DCFCE7; color: #15803D; }

.alert-item__figures {
  display: flex;
  gap: 0.9rem;
  font-size: 0.75rem;
  color: #475569;
}

.alert-item__figures strong {
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.alert-item__actions {
  display: flex;
  gap: 0.4rem;
}

.btn {
  border-radius: 6px;
  border: 1px solid transparent;
  cursor: pointer;
  font-weight: 600;
}

.btn--small {
  padding: 0.3rem 0.6rem;
  font-size: 0.6875rem;
}

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
}

.btn--primary {
  background: #0F172A;
  color: #F8FAFC;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>