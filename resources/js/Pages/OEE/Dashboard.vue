<template>
  <div class="dashboard-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 2 — OEE &amp; Downtime</p>
        <h1 class="page-title">OEE Dashboard</h1>
        <p class="page-subtitle">Kondisi lantai produksi real-time, Pareto downtime, dan tren historis.</p>
      </div>

      <label class="wc-select">
        <span>Mesin</span>
        <select v-model="selectedWorkCenterId" class="input" @change="switchWorkCenter">
          <option v-for="wc in workCenters" :key="wc.id" :value="wc.id">
            {{ wc.name }} ({{ wc.code }})
          </option>
        </select>
      </label>
    </header>

    <section class="gauge-section">
      <OeeGauge
        :work-center-id="selectedWorkCenterId"
        :work-center-name="selectedWorkCenterName"
        :initial-snapshot="snapshot"
      />

      <div class="benchmark-card" v-if="benchmark">
        <h2 class="section-title">Benchmark vs World Class</h2>
        <div class="benchmark-grid">
          <div v-for="key in benchmarkKeys" :key="key" class="benchmark-item">
            <span class="benchmark-item__label">{{ benchmarkLabel(key) }}</span>
            <div class="benchmark-item__values">
              <span class="benchmark-item__actual">{{ formatPercent(benchmark[key].actual) }}</span>
              <span class="benchmark-item__target">target {{ formatPercent(benchmark[key].world_class) }}</span>
            </div>
            <span
              class="benchmark-item__gap"
              :class="isPositiveGap(benchmark[key].gap) ? 'benchmark-item__gap--good' : 'benchmark-item__gap--bad'"
            >
              {{ formatGap(benchmark[key].gap) }}
            </span>
          </div>
        </div>
      </div>
      <div class="benchmark-card benchmark-card--empty" v-else>
        <h2 class="section-title">Benchmark vs World Class</h2>
        <p class="empty-text">Belum ada snapshot OEE untuk mesin ini.</p>
      </div>
    </section>

    <section class="trend-section">
      <div class="trend-header">
        <h2 class="section-title">Tren OEE Harian</h2>
        <p class="trend-subtitle">{{ formatDateRange(dateRange.from, dateRange.to) }}</p>
      </div>
      <div ref="trendContainer" class="trend-container">
        <div v-if="isTrendLoading" class="trend-loading">Memuat tren…</div>
        <div v-else-if="trend.length === 0" class="trend-empty">
          Belum ada data OEE historis untuk mesin ini pada rentang ini.
        </div>
        <svg v-show="!isTrendLoading && trend.length > 0" ref="trendSvgRef"></svg>
      </div>
    </section>

    <section class="pareto-section">
      <ParetoChart
        :initial-rows="pareto"
        :initial-date-from="dateRange.from"
        :initial-date-to="dateRange.to"
        :work-center-id="selectedWorkCenterId"
      />
    </section>
  </div>
</template>

<script setup>
/**
 * OEE/Dashboard.vue — halaman gabungan Engine 2 (US-08, US-09, US-15 parsial).
 * @see docs/oee-formulas.md § Real-time Update Flow, § OEE Trend & Benchmark
 * @see app/Http/Controllers/OeeController.php
 *
 * Props sesuai OeeController::dashboard():
 *   workCenters:          [{ id, name, code }]
 *   selectedWorkCenterId: int|null
 *   initialSnapshot:      OeeSnapshot|null (attributes: availability,
 *                          performance, quality, oee, computed_at, dst.)
 *   initialTrend:         array hasil OeeCalculatorService::trendData()
 *   initialPareto:        array hasil DowntimeAnalysisService::paretoDowntime()
 *   initialBenchmark:     hasil OeeCalculatorService::benchmarkVsWorldClass() | null
 *   dateRange:            { from, to } (string date, rentang 30 hari default)
 *
 * Ganti mesin memicu fetch ulang trend + snapshot terbaru via endpoint
 * /api/oee/trend (ParetoChart.vue fetch pareto-nya sendiri lewat watch
 * pada prop work-center-id).
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import * as d3 from 'd3'
import OeeGauge from '@/Components/OeeGauge.vue'
import ParetoChart from '@/Components/ParetoChart.vue'

const props = defineProps({
  workCenters: { type: Array, required: true },
  selectedWorkCenterId: { type: [Number, String], default: null },
  initialSnapshot: { type: Object, default: null },
  initialTrend: { type: Array, default: () => [] },
  initialPareto: { type: Array, default: () => [] },
  initialBenchmark: { type: Object, default: null },
  dateRange: { type: Object, required: true },
})

const selectedWorkCenterId = ref(props.selectedWorkCenterId)
const snapshot = ref(props.initialSnapshot)
const trend = ref(props.initialTrend)
const pareto = ref(props.initialPareto)
const benchmark = ref(props.initialBenchmark)
const isTrendLoading = ref(false)

const trendContainer = ref(null)
const trendSvgRef = ref(null)

const benchmarkKeys = ['oee', 'availability', 'performance', 'quality']

const selectedWorkCenterName = computed(() => {
  const wc = props.workCenters.find((w) => w.id === selectedWorkCenterId.value)
  return wc ? `${wc.name} (${wc.code})` : null
})

function benchmarkLabel(key) {
  const labels = { oee: 'OEE', availability: 'Availability', performance: 'Performance', quality: 'Quality' }
  return labels[key] ?? key
}

function formatPercent(value) {
  if (value === null || value === undefined) return '–'
  return `${(Number(value) * 100).toFixed(1)}%`
}

function formatGap(value) {
  if (value === null || value === undefined) return '–'
  const n = Number(value) * 100
  const sign = n >= 0 ? '+' : ''
  return `${sign}${n.toFixed(1)}pp`
}

function isPositiveGap(value) {
  return Number(value) >= 0
}

function formatDateRange(from, to) {
  const f = new Date(from).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  const t = new Date(to).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return `${f} – ${t}`
}

async function fetchWorkCenterData(workCenterId) {
  isTrendLoading.value = true
  try {
    const trendParams = new URLSearchParams({
      work_center_id: String(workCenterId),
      date_from: props.dateRange.from,
      date_to: props.dateRange.to,
    })
    const [trendResponse, snapshotResponse] = await Promise.all([
      fetch(`/api/oee/trend?${trendParams.toString()}`, { headers: { Accept: 'application/json' } }),
      fetch(`/api/oee/work-centers/${workCenterId}/latest-snapshot`, { headers: { Accept: 'application/json' } }),
    ])

    if (!trendResponse.ok) throw new Error(`Gagal memuat trend (${trendResponse.status})`)
    if (!snapshotResponse.ok) throw new Error(`Gagal memuat snapshot (${snapshotResponse.status})`)

    trend.value = await trendResponse.json()

    // Snapshot & benchmark sekarang diambil dari endpoint khusus
    // (latest-snapshot), bukan di-derive dari titik terakhir trend --
    // trend adalah rata-rata harian lintas shift, tidak cukup presisi
    // untuk benchmark per-shift asli.
    const snapshotData = await snapshotResponse.json()
    snapshot.value = snapshotData.snapshot
    benchmark.value = snapshotData.benchmark
  } catch (error) {
    console.error('OEE Dashboard: gagal fetch data mesin', error)
    trend.value = []
    snapshot.value = null
    benchmark.value = null
  } finally {
    isTrendLoading.value = false
  }
}

function switchWorkCenter() {
  fetchWorkCenterData(selectedWorkCenterId.value)
}

function renderTrend() {
  if (!trendSvgRef.value || !trendContainer.value) return
  if (trend.value.length === 0) return

  const data = trend.value.map((d) => ({ ...d, dateObj: new Date(d.date) }))
  const width = Math.max(trendContainer.value.clientWidth, 480)
  const height = 220
  const margin = { top: 20, right: 24, bottom: 32, left: 44 }

  const svg = d3.select(trendSvgRef.value)
  svg.selectAll('*').remove()
  svg.attr('width', width).attr('height', height).attr('viewBox', `0 0 ${width} ${height}`)

  const xScale = d3.scaleTime()
    .domain(d3.extent(data, (d) => d.dateObj))
    .range([margin.left, width - margin.right])

  const yScale = d3.scaleLinear()
    .domain([0, 1])
    .range([height - margin.bottom, margin.top])

  svg.append('g')
    .attr('class', 'x-axis')
    .attr('transform', `translate(0, ${height - margin.bottom})`)
    .call(d3.axisBottom(xScale).ticks(Math.min(data.length, 6)).tickFormat(d3.timeFormat('%d %b')))

  svg.append('g')
    .attr('class', 'y-axis')
    .attr('transform', `translate(${margin.left}, 0)`)
    .call(d3.axisLeft(yScale).ticks(5).tickFormat((d) => `${Math.round(d * 100)}%`))

  const series = [
    { key: 'oee', color: '#0F172A', label: 'OEE' },
    { key: 'availability', color: '#2563EB', label: 'Availability' },
    { key: 'performance', color: '#D97706', label: 'Performance' },
    { key: 'quality', color: '#16A34A', label: 'Quality' },
  ]

  series.forEach((s) => {
    const lineGen = d3.line()
      .x((d) => xScale(d.dateObj))
      .y((d) => yScale(Number(d[s.key])))

    svg.append('path')
      .datum(data)
      .attr('fill', 'none')
      .attr('stroke', s.color)
      .attr('stroke-width', s.key === 'oee' ? 2.5 : 1.5)
      .attr('opacity', s.key === 'oee' ? 1 : 0.6)
      .attr('d', lineGen)

    // Marker titik per data point -- penting supaya data dengan hanya
    // 1 titik (mis. baru 1 hari snapshot tercatat) tetap terlihat, karena
    // <path> garis butuh minimal 2 titik untuk tergambar sama sekali.
    svg.append('g')
      .selectAll(`circle.point-${s.key}`)
      .data(data)
      .join('circle')
      .attr('class', `point-${s.key}`)
      .attr('cx', (d) => xScale(d.dateObj))
      .attr('cy', (d) => yScale(Number(d[s.key])))
      .attr('r', s.key === 'oee' ? 4 : 3)
      .attr('fill', s.color)
      .attr('opacity', s.key === 'oee' ? 1 : 0.6)
  })

  // Legend sederhana
  const legend = svg.append('g').attr('transform', `translate(${margin.left}, ${margin.top - 10})`)
  series.forEach((s, i) => {
    const g = legend.append('g').attr('transform', `translate(${i * 110}, 0)`)
    g.append('rect').attr('width', 10).attr('height', 10).attr('fill', s.color).attr('rx', 2)
    g.append('text').attr('x', 14).attr('y', 9).attr('class', 'legend-text').text(s.label)
  })
}

let resizeObserver = null

onMounted(async () => {
  await nextTick()
  renderTrend()
  resizeObserver = new ResizeObserver(() => renderTrend())
  if (trendContainer.value) resizeObserver.observe(trendContainer.value)
})

onBeforeUnmount(() => {
  if (resizeObserver && trendContainer.value) resizeObserver.unobserve(trendContainer.value)
})

watch(trend, () => nextTick(() => renderTrend()))
</script>

<style scoped>
.dashboard-page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1.5rem;
  max-width: 1200px;
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

.wc-select {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #475569;
}

.input {
  padding: 0.45rem 0.65rem;
  font-size: 0.8125rem;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  min-width: 220px;
}

.gauge-section {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(260px, 1fr);
  gap: 1.25rem;
}

@media (max-width: 720px) {
  .gauge-section { grid-template-columns: 1fr; }
}

.benchmark-card {
  padding: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.section-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.benchmark-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.85rem;
}

.benchmark-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.65rem;
  background: #F8FAFC;
  border-radius: 8px;
}

.benchmark-item__label {
  font-size: 0.6875rem;
  color: #64748B;
  font-weight: 600;
}

.benchmark-item__values {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
}

.benchmark-item__actual {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.benchmark-item__target {
  font-size: 0.6875rem;
  color: #94A3B8;
}

.benchmark-item__gap {
  font-size: 0.75rem;
  font-weight: 600;
  width: fit-content;
}

.benchmark-item__gap--good { color: #16A34A; }
.benchmark-item__gap--bad { color: #DC2626; }

.benchmark-card--empty .empty-text {
  color: #94A3B8;
  font-size: 0.8125rem;
  margin: 0;
}

.trend-section {
  padding: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.trend-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.trend-subtitle {
  font-size: 0.75rem;
  color: #94A3B8;
  margin: 0;
}

.trend-container {
  position: relative;
  min-height: 180px;
}

.trend-loading,
.trend-empty {
  padding: 2.5rem 1rem;
  text-align: center;
  color: #94A3B8;
  font-size: 0.8125rem;
}

:deep(.x-axis text),
:deep(.y-axis text) {
  font-size: 0.6875rem;
  fill: #64748B;
}

:deep(.x-axis path), :deep(.x-axis line),
:deep(.y-axis path), :deep(.y-axis line) {
  stroke: #CBD5E1;
}

:deep(.legend-text) {
  font-size: 0.6875rem;
  fill: #475569;
}

.pareto-section {
  display: flex;
  flex-direction: column;
}
</style>