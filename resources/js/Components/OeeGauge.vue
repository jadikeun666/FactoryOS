<template>
  <div class="oee-gauge">
    <div class="gauge-header">
      <span class="gauge-title">{{ workCenterName ?? `Mesin #${workCenterId}` }}</span>
      <span class="live-indicator" :class="{ 'live-indicator--connected': isConnected }">
        <span class="live-dot"></span>
        {{ isConnected ? 'Live' : 'Offline' }}
      </span>
    </div>

    <div v-if="!snapshot" class="gauge-empty">
      Belum ada data OEE untuk mesin ini.
    </div>

    <template v-else>
      <div class="main-gauge">
        <svg ref="svgRef" viewBox="0 0 200 120" class="arc-svg">
          <path
            d="M 20 100 A 80 80 0 0 1 180 100"
            fill="none"
            stroke="#E2E8F0"
            stroke-width="14"
            stroke-linecap="round"
          />
          <path
            :d="arcPath"
            fill="none"
            :stroke="oeeColor"
            stroke-width="14"
            stroke-linecap="round"
            class="arc-fill"
          />
        </svg>
        <div class="gauge-value">
          <span class="gauge-value__number" :style="{ color: oeeColor }">{{ formatPercent(snapshot.oee) }}</span>
          <span class="gauge-value__label">OEE</span>
        </div>
      </div>

      <div class="sub-metrics">
        <div class="metric-bar">
          <div class="metric-bar__header">
            <span>Availability</span>
            <span>{{ formatPercent(snapshot.availability) }}</span>
          </div>
          <div class="metric-bar__track">
            <div
              class="metric-bar__fill"
              :style="{ width: percentWidth(snapshot.availability), backgroundColor: metricColor(snapshot.availability, 0.90) }"
            ></div>
          </div>
        </div>

        <div class="metric-bar">
          <div class="metric-bar__header">
            <span>Performance</span>
            <span>{{ formatPercent(snapshot.performance) }}</span>
          </div>
          <div class="metric-bar__track">
            <div
              class="metric-bar__fill"
              :style="{ width: percentWidth(snapshot.performance), backgroundColor: metricColor(snapshot.performance, 0.95) }"
            ></div>
          </div>
        </div>

        <div class="metric-bar">
          <div class="metric-bar__header">
            <span>Quality</span>
            <span>{{ formatPercent(snapshot.quality) }}</span>
          </div>
          <div class="metric-bar__track">
            <div
              class="metric-bar__fill"
              :style="{ width: percentWidth(snapshot.quality), backgroundColor: metricColor(snapshot.quality, 0.9999) }"
            ></div>
          </div>
        </div>
      </div>

      <p class="last-updated">
        Terakhir dihitung: {{ formatDateTime(snapshot.computed_at) }}
      </p>
    </template>
  </div>
</template>

<script setup>
/**
 * OeeGauge.vue — gauge OEE real-time per mesin.
 * @see docs/oee-formulas.md § Real-time Update Flow (Soketi)
 * @see docs/architecture.md § WebSocket Flow
 *
 * Live update: subscribe ke private channel `work-center.{workCenterId}`,
 * event `oee.updated` (broadcastAs custom -> WAJIB pakai titik di depan
 * saat listen(), lihat app/Events/OeeUpdated.php).
 *
 * ASUMSI props initialSnapshot: shape sama seperti payload broadcastWith()
 * di OeeUpdated.php -> { work_center_id, log_date, shift_id, availability,
 * performance, quality, oee, computed_at } (semua rasio dikirim sebagai
 * string dari backend, bukan number).
 *
 * CATATAN: BROADCAST_CONNECTION backend masih 'log' di sesi ini (lihat
 * claude.md) -- komponen ini sudah siap pakai Echo, tapi tidak akan
 * menerima event nyata sampai Soketi diaktifkan di sesi lain. Badge
 * "Live/Offline" akan tetap menunjukkan status koneksi Echo yang
 * sebenarnya, jadi ini bukan bug -- itu memang belum terhubung.
 */
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  workCenterId: { type: [Number, String], required: true },
  workCenterName: { type: String, default: null },
  initialSnapshot: { type: Object, default: null },
})

const snapshot = ref(props.initialSnapshot)
const isConnected = ref(false)

let channel = null

const WORLD_CLASS_OEE = 0.85

function toNumber(value) {
  return value === null || value === undefined ? null : Number(value)
}

const oeeRatio = computed(() => {
  if (!snapshot.value) return 0
  return Math.min(Math.max(toNumber(snapshot.value.oee) ?? 0, 0), 1)
})

const oeeColor = computed(() => metricColor(snapshot.value?.oee, WORLD_CLASS_OEE))

// Arc dari 0 (awal semicircle) sampai proporsi oeeRatio; total sweep 180°.
const arcPath = computed(() => {
  const sweepDeg = 180 * oeeRatio.value
  const angleRad = (Math.PI * sweepDeg) / 180
  const cx = 100
  const cy = 100
  const r = 80
  const startX = 20
  const startY = 100
  const endX = cx - r * Math.cos(angleRad)
  const endY = cy - r * Math.sin(angleRad)
  const largeArc = sweepDeg > 180 ? 1 : 0
  return `M ${startX} ${startY} A ${r} ${r} 0 ${largeArc} 1 ${endX} ${endY}`
})

function formatPercent(value) {
  const n = toNumber(value)
  if (n === null) return '–'
  return `${(n * 100).toFixed(1)}%`
}

function percentWidth(value) {
  const n = toNumber(value)
  if (n === null) return '0%'
  return `${Math.min(Math.max(n * 100, 0), 100)}%`
}

// Hijau jika >= world class, kuning jika di rentang typical (>=0.6 dari target), merah jika di bawah itu.
function metricColor(value, worldClassTarget) {
  const n = toNumber(value)
  if (n === null) return '#94A3B8'
  if (n >= worldClassTarget) return '#16A34A'
  if (n >= worldClassTarget * 0.7) return '#D97706'
  return '#DC2626'
}

function formatDateTime(iso) {
  if (!iso) return '–'
  return new Date(iso).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}

function subscribe(workCenterId) {
  unsubscribe()
  if (!window.Echo) {
    console.warn('OeeGauge: window.Echo belum tersedia — pastikan resources/js/echo.js sudah di-import.')
    return
  }

  channel = window.Echo.private(`work-center.${workCenterId}`)

  channel.subscribed(() => {
    isConnected.value = true
  })

  channel.error((error) => {
    isConnected.value = false
    console.error('OeeGauge: gagal subscribe channel work-center.' + workCenterId, error)
  })

  // Titik di depan WAJIB karena OeeUpdated::broadcastAs() memakai nama
  // kustom 'oee.updated' (bukan default namespaced App\Events\OeeUpdated).
  channel.listen('.oee.updated', (event) => {
    if (event?.snapshot) {
      snapshot.value = event.snapshot
    }
  })
}

function unsubscribe() {
  if (channel && window.Echo) {
    window.Echo.leave(`work-center.${props.workCenterId}`)
  }
  channel = null
  isConnected.value = false
}

onMounted(() => subscribe(props.workCenterId))
onBeforeUnmount(() => unsubscribe())
watch(() => props.workCenterId, (newId) => subscribe(newId))
watch(() => props.initialSnapshot, (val) => {
  snapshot.value = val
})
</script>

<style scoped>
.oee-gauge {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.gauge-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.gauge-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #0F172A;
}

.live-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.6875rem;
  font-weight: 600;
  color: #94A3B8;
}

.live-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 999px;
  background: #CBD5E1;
}

.live-indicator--connected {
  color: #16A34A;
}

.live-indicator--connected .live-dot {
  background: #22C55E;
  animation: pulse-dot 1.6s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.35; }
}

.gauge-empty {
  padding: 2rem 1rem;
  text-align: center;
  color: #94A3B8;
  font-size: 0.8125rem;
}

.main-gauge {
  position: relative;
  display: flex;
  justify-content: center;
}

.arc-svg {
  width: 100%;
  max-width: 240px;
  height: auto;
}

.arc-fill {
  transition: stroke-dasharray 0.4s ease, d 0.4s ease;
}

.gauge-value {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.gauge-value__number {
  font-size: 1.75rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.gauge-value__label {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94A3B8;
}

.sub-metrics {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.metric-bar__header {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #475569;
  margin-bottom: 0.25rem;
}

.metric-bar__header span:last-child {
  font-weight: 600;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.metric-bar__track {
  height: 0.4rem;
  border-radius: 999px;
  background: #F1F5F9;
  overflow: hidden;
}

.metric-bar__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease, background-color 0.4s ease;
}

.last-updated {
  font-size: 0.6875rem;
  color: #94A3B8;
  margin: 0;
  text-align: center;
}
</style>