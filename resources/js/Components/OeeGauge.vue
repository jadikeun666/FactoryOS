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
          <g class="dial-ticks" aria-hidden="true">
            <line
              v-for="tick in dialTicks"
              :key="tick.angle"
              :x1="tick.x1" :y1="tick.y1" :x2="tick.x2" :y2="tick.y2"
              :stroke-width="tick.major ? 2 : 1"
              class="dial-tick"
              :class="{ 'dial-tick--major': tick.major }"
            />
          </g>
          <path
            d="M 20 100 A 80 80 0 0 1 180 100"
            fill="none"
            stroke="var(--hairline-strong)"
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
 * Soketi AKTIF & PERMANEN via Supervisor (factoryos-soketi.conf).
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

const dialTicks = computed(() => {
  const cx = 100
  const cy = 100
  const rOuter = 92
  const ticks = []
  for (let i = 0; i <= 10; i++) {
    const deg = 180 - i * 18
    const rad = (Math.PI * deg) / 180
    const major = i % 5 === 0
    const rInner = major ? 80 : 85
    ticks.push({
      angle: deg,
      major,
      x1: cx - rInner * Math.cos(rad),
      y1: cy - rInner * Math.sin(rad),
      x2: cx - rOuter * Math.cos(rad),
      y2: cy - rOuter * Math.sin(rad),
    })
  }
  return ticks
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

function metricColor(value, worldClassTarget) {
  const n = toNumber(value)
  if (n === null) return '#6B7280'
  if (n >= worldClassTarget) return '#4A9B6E'
  if (n >= worldClassTarget * 0.7) return '#E8A33D'
  return '#D64545'
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
  gap: 1.1rem;
  padding: 1.35rem 1.25rem;
  background: linear-gradient(180deg, var(--card-bg-start) 0%, var(--card-bg-end) 100%);
  border: 1px solid var(--hairline);
  border-radius: 4px;
  transition: background-color 0.25s ease, border-color 0.25s ease;
}

.gauge-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.gauge-title {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: var(--data-ink-inverse);
}

.live-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-family: var(--font-body);
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--data-ink-muted);
}

.live-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 999px;
  background: var(--hairline-strong);
}

.live-indicator--connected {
  color: var(--signal-green);
}

.live-indicator--connected .live-dot {
  background: var(--signal-green);
  box-shadow: 0 0 6px 0 rgba(74, 155, 110, 0.7);
  animation: pulse-dot 1.6s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.35; }
}
@media (prefers-reduced-motion: reduce) {
  .live-indicator--connected .live-dot { animation: none; }
}

.gauge-empty {
  padding: 2rem 1rem;
  text-align: center;
  font-family: var(--font-body);
  color: var(--data-ink-muted);
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

.dial-tick {
  stroke: var(--hairline);
}
.dial-tick--major {
  stroke: var(--data-ink-muted);
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
  font-family: var(--font-display);
  font-size: 1.875rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.gauge-value__label {
  font-family: var(--font-body);
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--data-ink-muted);
}

.sub-metrics {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.metric-bar__header {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--data-ink-muted);
  margin-bottom: 0.3rem;
}

.metric-bar__header span:last-child {
  font-family: var(--font-display);
  font-weight: 600;
  color: var(--data-ink-inverse);
  font-variant-numeric: tabular-nums;
}

.metric-bar__track {
  height: 0.375rem;
  border-radius: 2px;
  background: var(--hairline-strong);
  overflow: hidden;
}

.metric-bar__fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.4s ease, background-color 0.4s ease;
}

.last-updated {
  font-family: var(--font-display);
  font-size: 0.6875rem;
  color: var(--data-ink-muted);
  margin: 0;
  text-align: center;
}
</style>