<template>
  <div class="dashboard-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 2 — OEE &amp; Downtime</p>
        <h1 class="page-title">OEE Dashboard</h1>
        <p class="page-subtitle">Kondisi lantai produksi real-time, Pareto downtime, dan tren historis.</p>
      </div>

      <div class="header-actions">
        <label class="wc-select">
          <span>Mesin</span>
          <select v-model="selectedWorkCenterId" class="input" @change="switchWorkCenter">
            <option v-for="wc in workCenters" :key="wc.id" :value="wc.id">
              {{ wc.name }} ({{ wc.code }})
            </option>
          </select>
        </label>

        <label class="wc-select">
          <span>Tanggal Export</span>
          <input v-model="exportDate" type="date" class="input input--date" />
        </label>

        <button class="btn btn--primary" :disabled="exporting" @click="exportOeePdf">
          <span v-if="exporting">⏳ Memproses...</span>
          <span v-else>⬇ Export PDF Harian</span>
        </button>

        <label class="wc-select">
          <span>Bulan Export</span>
          <input v-model="exportMonth" type="month" class="input input--date" />
        </label>

        <button class="btn btn--secondary" :disabled="exportingTrend" @click="exportOeeTrendExcel">
          <span v-if="exportingTrend">⏳ Memproses...</span>
          <span v-else>⬇ Export Excel Trend</span>
        </button>
      </div>
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
 *
 * MODERNISASI VISUAL TAHAP 2 (2026-08-09): warna trend line SVG (4 seri:
 * OEE/Availability/Performance/Quality) di-generate lewat D3, jadi tidak
 * otomatis ikut var(--token) saat tema di-toggle. Fix: cssVar() membaca
 * token dari getComputedStyle saat renderTrend() dipanggil, dan
 * watch(theme, ...) memicu render ulang tiap kali tema berganti. Palet
 * seri: OEE=data-ink (garis utama tebal), Availability=steel-blue,
 * Performance=signal-amber, Quality=signal-green. Ini SATU-SATUNYA
 * penambahan JS di file ini -- fetch/export/logic lainnya tidak diubah.
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import * as d3 from 'd3'
import OeeGauge from '@/Components/OeeGauge.vue'
import ParetoChart from '@/Components/ParetoChart.vue'
import { useTheme } from '@/composables/useTheme'

const props = defineProps({
  workCenters: { type: Array, required: true },
  selectedWorkCenterId: { type: [Number, String], default: null },
  initialSnapshot: { type: Object, default: null },
  initialTrend: { type: Array, default: () => [] },
  initialPareto: { type: Array, default: () => [] },
  initialBenchmark: { type: Object, default: null },
  dateRange: { type: Object, required: true },
})

const { theme } = useTheme()

const selectedWorkCenterId = ref(props.selectedWorkCenterId)
const snapshot = ref(props.initialSnapshot)
const trend = ref(props.initialTrend)
const pareto = ref(props.initialPareto)
const benchmark = ref(props.initialBenchmark)
const isTrendLoading = ref(false)

// Export PDF OEE Harian: endpoint JSON murni, WAJIB fetch() bukan
// router.post() (lihat claude.md § Catatan Teknis Penting). Halaman ini
// tidak punya date picker sebelumnya (trend pakai rentang 30 hari tetap
// dari server) — ditambah satu <input type="date"> khusus untuk export,
// default hari ini, terpisah dari dateRange trend.
const exportDate = ref(new Date().toISOString().slice(0, 10))
const exporting = ref(false)
let exportPollTimer = null
let exportPollAttempts = 0
const MAX_EXPORT_POLL_ATTEMPTS = 15 // 15 x 2s = 30 detik timeout

async function exportOeePdf() {
  if (exporting.value) return
  exporting.value = true
  exportPollAttempts = 0

  try {
    const res = await fetch('/exports/oee/pdf', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        date: exportDate.value,
        work_center_id: selectedWorkCenterId.value,
      }),
    })

    if (!res.ok) {
      const body = await res.json().catch(() => null)
      alert(body?.message ?? 'Export gagal diproses. Coba lagi.')
      exporting.value = false
      return
    }

    pollExportStatus()
  } catch {
    alert('Gagal menghubungi server untuk export. Periksa koneksi Anda.')
    exporting.value = false
  }
}

function pollExportStatus() {
  const statusParams = new URLSearchParams({
    date: exportDate.value,
    work_center_id: String(selectedWorkCenterId.value ?? ''),
  })

  exportPollTimer = setInterval(async () => {
    exportPollAttempts++

    try {
      const res = await fetch(`/exports/oee/pdf/status?${statusParams.toString()}`, {
        headers: { Accept: 'application/json' },
      })
      const data = await res.json()

      if (data.ready && data.path) {
        clearInterval(exportPollTimer)
        exporting.value = false
        window.location.href = `/exports/download?path=${encodeURIComponent(data.path)}`
        return
      }
    } catch {
      // Diamkan satu kegagalan poll, coba lagi di interval berikutnya.
    }

    if (exportPollAttempts >= MAX_EXPORT_POLL_ATTEMPTS) {
      clearInterval(exportPollTimer)
      exporting.value = false
      alert('Export memakan waktu lebih lama dari biasanya. Coba lagi sesaat lagi.')
    }
  }, 2000)
}

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

function cssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

function renderTrend() {
  if (!trendSvgRef.value || !trendContainer.value) return
  if (trend.value.length === 0) return

  const data = trend.value.map((d) => ({ ...d, dateObj: new Date(d.date) }))
  const width = Math.max(trendContainer.value.clientWidth, 480)
  const height = 220
  const margin = { top: 20, right: 24, bottom: 32, left: 44 }

  const colorAxisText = cssVar('--data-ink-muted') || '#64748B'
  const colorAxisLine = cssVar('--hairline-strong') || '#CBD5E1'
  const colorLegendText = cssVar('--data-ink-muted') || '#475569'
  const colorOee = cssVar('--data-ink') || '#0F172A'

  const svg = d3.select(trendSvgRef.value)
  svg.selectAll('*').remove()
  svg.attr('width', width).attr('height', height).attr('viewBox', `0 0 ${width} ${height}`)

  const xScale = d3.scaleTime()
    .domain(d3.extent(data, (d) => d.dateObj))
    .range([margin.left, width - margin.right])

  const yScale = d3.scaleLinear()
    .domain([0, 1])
    .range([height - margin.bottom, margin.top])

  const xAxisG = svg.append('g')
    .attr('class', 'x-axis')
    .attr('transform', `translate(0, ${height - margin.bottom})`)
    .call(d3.axisBottom(xScale).ticks(Math.min(data.length, 6)).tickFormat(d3.timeFormat('%d %b')))
  xAxisG.selectAll('text').attr('fill', colorAxisText)
  xAxisG.selectAll('path, line').attr('stroke', colorAxisLine)

  const yAxisG = svg.append('g')
    .attr('class', 'y-axis')
    .attr('transform', `translate(${margin.left}, 0)`)
    .call(d3.axisLeft(yScale).ticks(5).tickFormat((d) => `${Math.round(d * 100)}%`))
  yAxisG.selectAll('text').attr('fill', colorAxisText)
  yAxisG.selectAll('path, line').attr('stroke', colorAxisLine)

  const series = [
    { key: 'oee', color: colorOee, label: 'OEE' },
    { key: 'availability', color: '#5B8DEF', label: 'Availability' },
    { key: 'performance', color: '#E8A33D', label: 'Performance' },
    { key: 'quality', color: '#4A9B6E', label: 'Quality' },
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
      .attr('opacity', s.key === 'oee' ? 1 : 0.75)
      .attr('d', lineGen)

    svg.append('g')
      .selectAll(`circle.point-${s.key}`)
      .data(data)
      .join('circle')
      .attr('class', `point-${s.key}`)
      .attr('cx', (d) => xScale(d.dateObj))
      .attr('cy', (d) => yScale(Number(d[s.key])))
      .attr('r', s.key === 'oee' ? 4 : 3)
      .attr('fill', s.color)
      .attr('opacity', s.key === 'oee' ? 1 : 0.75)
  })

  const legend = svg.append('g').attr('transform', `translate(${margin.left}, ${margin.top - 10})`)
  series.forEach((s, i) => {
    const g = legend.append('g').attr('transform', `translate(${i * 110}, 0)`)
    g.append('rect').attr('width', 10).attr('height', 10).attr('fill', s.color).attr('rx', 2)
    g.append('text').attr('x', 14).attr('y', 9).attr('class', 'legend-text').attr('fill', colorLegendText).text(s.label)
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
  if (exportPollTimer) clearInterval(exportPollTimer)
  if (exportTrendPollTimer) clearInterval(exportTrendPollTimer)
})

// Export Excel OEE Trend Bulanan: endpoint JSON murni, WAJIB fetch()
// bukan router.post() (lihat claude.md § Catatan Teknis Penting).
const exportMonth = ref(new Date().toISOString().slice(0, 7)) // format YYYY-MM
const exportingTrend = ref(false)
let exportTrendPollTimer = null
let exportTrendPollAttempts = 0
const MAX_EXPORT_TREND_POLL_ATTEMPTS = 15 // 15 x 2s = 30 detik timeout

async function exportOeeTrendExcel() {
  if (exportingTrend.value) return
  exportingTrend.value = true
  exportTrendPollAttempts = 0

  try {
    const res = await fetch('/exports/oee-trend/excel', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
      },
      body: JSON.stringify({ month: exportMonth.value }),
    })

    if (!res.ok) {
      const body = await res.json().catch(() => null)
      alert(body?.message ?? 'Export gagal diproses. Coba lagi.')
      exportingTrend.value = false
      return
    }

    pollExportTrendStatus()
  } catch {
    alert('Gagal menghubungi server untuk export. Periksa koneksi Anda.')
    exportingTrend.value = false
  }
}

function pollExportTrendStatus() {
  const month = exportMonth.value

  exportTrendPollTimer = setInterval(async () => {
    exportTrendPollAttempts++

    try {
      const res = await fetch(`/exports/oee-trend/excel/status?month=${encodeURIComponent(month)}`, {
        headers: { Accept: 'application/json' },
      })
      const data = await res.json()

      if (data.ready && data.path) {
        clearInterval(exportTrendPollTimer)
        exportingTrend.value = false
        window.location.href = `/exports/download?path=${encodeURIComponent(data.path)}`
        return
      }
    } catch {
      // Diamkan satu kegagalan poll, coba lagi di interval berikutnya.
    }

    if (exportTrendPollAttempts >= MAX_EXPORT_TREND_POLL_ATTEMPTS) {
      clearInterval(exportTrendPollTimer)
      exportingTrend.value = false
      alert('Export memakan waktu lebih lama dari biasanya. Coba lagi sesaat lagi.')
    }
  }, 2000)
}

watch(trend, () => nextTick(() => renderTrend()))
watch(theme, () => nextTick(() => renderTrend()))
</script>

<style scoped>
.dashboard-page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
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

.wc-select {
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
  min-width: 220px;
}

.input:focus {
  outline: 2px solid var(--signal-amber);
  outline-offset: 1px;
}

.input--date {
  min-width: 150px;
}

.header-actions {
  display: flex;
  align-items: flex-end;
  gap: 0.75rem;
  flex-wrap: wrap;
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
  transition: background-color 0.15s ease, transform 0.12s ease;
}

.btn:active { transform: translateY(1px); }

.btn--primary {
  background: var(--signal-amber);
  color: #1C1F26;
}

.btn--primary:hover:not(:disabled) { filter: brightness(1.08); }

.btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--secondary {
  background: var(--panel-graphite);
  border-color: var(--hairline-border);
  color: var(--data-ink-muted);
}

.btn--secondary:hover:not(:disabled) { background: var(--surface-steel); }

.btn--secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.section-title {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--data-ink);
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
  background: var(--surface-steel);
  border-radius: 8px;
}

.benchmark-item__label {
  font-size: 0.6875rem;
  color: var(--data-ink-muted);
  font-weight: 600;
}

.benchmark-item__values {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
}

.benchmark-item__actual {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--data-ink);
  font-variant-numeric: tabular-nums;
}

.benchmark-item__target {
  font-size: 0.6875rem;
  color: var(--data-ink-muted);
}

.benchmark-item__gap {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 600;
  width: fit-content;
}

.benchmark-item__gap--good { color: var(--signal-green); }
.benchmark-item__gap--bad { color: var(--signal-red); }

.benchmark-card--empty .empty-text {
  color: var(--data-ink-muted);
  font-size: 0.8125rem;
  margin: 0;
}

.trend-section {
  padding: 1.25rem;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
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
  color: var(--data-ink-muted);
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
  color: var(--data-ink-muted);
  font-size: 0.8125rem;
}

:deep(.x-axis text),
:deep(.y-axis text) {
  font-family: var(--font-display);
  font-size: 0.6875rem;
}

:deep(.legend-text) {
  font-family: var(--font-display);
  font-size: 0.6875rem;
}

.pareto-section {
  display: flex;
  flex-direction: column;
}
</style>
