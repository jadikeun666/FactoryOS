<template>
  <div class="pareto-wrapper">
    <div class="pareto-header">
      <div>
        <span class="pareto-title">Pareto Downtime</span>
        <p class="pareto-subtitle">{{ formatDateRange(dateFrom, dateTo) }}</p>
      </div>
      <form class="filter-form" @submit.prevent="applyFilters">
        <label class="filter-field">
          <span>Dari</span>
          <input v-model="localDateFrom" type="date" class="input" />
        </label>
        <label class="filter-field">
          <span>Sampai</span>
          <input v-model="localDateTo" type="date" class="input" />
        </label>
        <button type="submit" class="btn btn--ghost" :disabled="isLoading">Terapkan</button>
      </form>
    </div>

    <div ref="chartContainer" class="chart-container" :aria-busy="isLoading">
      <div v-if="isLoading" class="chart-loading">Memuat data pareto…</div>
      <div v-else-if="rows.length === 0" class="chart-empty">
        Tidak ada downtime tercatat pada rentang ini.
      </div>
      <svg v-show="!isLoading && rows.length > 0" ref="svgRef"></svg>
    </div>

    <table v-if="!isLoading && rows.length > 0" class="pareto-table">
      <thead>
        <tr>
          <th>Kategori</th>
          <th class="num">Total Menit</th>
          <th class="num">%</th>
          <th class="num">Kumulatif</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.category">
          <td>
            <span class="category-tag" :class="`category-tag--${row.category}`">
              {{ categoryLabel(row.category) }}
            </span>
          </td>
          <td class="num">{{ formatNumber(row.total_minutes) }}</td>
          <td class="num">{{ formatNumber(row.percentage) }}%</td>
          <td class="num">{{ formatNumber(row.cumulative) }}%</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
/**
 * ParetoChart.vue — bar chart + garis kumulatif untuk Pareto Analysis
 * Downtime (US-09, FR-05).
 * @see docs/oee-formulas.md § Pareto Analysis Downtime
 * @see app/Services/OEE/DowntimeAnalysisService.php (paretoDowntime, final & teruji)
 *
 * Format data yang dikonsumsi (satu row per kategori, sudah urut DESC by
 * total_minutes dari service):
 *   { category, total_minutes, percentage, cumulative } — semua string
 *   (bcmath) dari backend.
 *
 * Fetch ulang ke /api/oee/pareto saat filter tanggal/mesin berubah,
 * mengikuti pola fetch di GanttChart.vue (bukan reload Inertia halaman
 * penuh). Endpoint ini pakai middleware auth:sanctum + session stateful,
 * sudah terverifikasi jalan (lihat verifikasi gantt-data sebelumnya).
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import * as d3 from 'd3'

const props = defineProps({
  initialRows: { type: Array, default: () => [] },
  initialDateFrom: { type: String, required: true },
  initialDateTo: { type: String, required: true },
  workCenterId: { type: [Number, String], default: null },
  paretoUrl: {
    type: Function,
    default: () => '/api/oee/pareto',
  },
})

const rows = ref(props.initialRows)
const dateFrom = ref(props.initialDateFrom)
const dateTo = ref(props.initialDateTo)
const isLoading = ref(false)

const localDateFrom = ref(props.initialDateFrom)
const localDateTo = ref(props.initialDateTo)

const chartContainer = ref(null)
const svgRef = ref(null)

const CATEGORY_LABELS = {
  breakdown: 'Breakdown',
  setup: 'Setup',
  material: 'Material',
  operator: 'Operator',
  other: 'Lainnya',
}

const CATEGORY_COLORS = {
  breakdown: '#DC2626',
  setup: '#D97706',
  material: '#2563EB',
  operator: '#7C3AED',
  other: '#64748B',
}

function categoryLabel(category) {
  return CATEGORY_LABELS[category] ?? category
}

function formatNumber(value) {
  if (value === null || value === undefined) return '–'
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1 })
}

function formatDateRange(from, to) {
  const f = new Date(from).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  const t = new Date(to).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return `${f} – ${t}`
}

async function fetchPareto() {
  isLoading.value = true
  try {
    const params = new URLSearchParams({
      date_from: dateFrom.value,
      date_to: dateTo.value,
    })
    if (props.workCenterId) params.set('work_center_id', String(props.workCenterId))

    const response = await fetch(`${props.paretoUrl()}?${params.toString()}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) throw new Error(`Gagal memuat data pareto (${response.status})`)
    rows.value = await response.json()
  } catch (error) {
    console.error('ParetoChart: gagal fetch data', error)
    rows.value = []
  } finally {
    isLoading.value = false
  }
}

function applyFilters() {
  dateFrom.value = localDateFrom.value
  dateTo.value = localDateTo.value
  fetchPareto()
}

function renderChart() {
  if (!svgRef.value || !chartContainer.value) return
  if (rows.value.length === 0) return

  const data = rows.value
  const containerWidth = Math.max(chartContainer.value.clientWidth, 480)
  const width = containerWidth
  const height = 280
  const margin = { top: 20, right: 48, bottom: 60, left: 56 }

  const svg = d3.select(svgRef.value)
  svg.selectAll('*').remove()
  svg
    .attr('width', width)
    .attr('height', height)
    .attr('viewBox', `0 0 ${width} ${height}`)

  const xScale = d3.scaleBand()
    .domain(data.map((d) => d.category))
    .range([margin.left, width - margin.right])
    .padding(0.3)

  const maxMinutes = d3.max(data, (d) => Number(d.total_minutes)) ?? 0
  const yScaleBars = d3.scaleLinear()
    .domain([0, maxMinutes * 1.1 || 1])
    .range([height - margin.bottom, margin.top])

  const yScaleLine = d3.scaleLinear()
    .domain([0, 100])
    .range([height - margin.bottom, margin.top])

  // Sumbu X
  svg.append('g')
    .attr('class', 'x-axis')
    .attr('transform', `translate(0, ${height - margin.bottom})`)
    .call(d3.axisBottom(xScale).tickFormat((d) => categoryLabel(d)))

  // Sumbu Y kiri (menit)
  svg.append('g')
    .attr('class', 'y-axis-left')
    .attr('transform', `translate(${margin.left}, 0)`)
    .call(d3.axisLeft(yScaleBars).ticks(5))

  // Sumbu Y kanan (persentase kumulatif)
  svg.append('g')
    .attr('class', 'y-axis-right')
    .attr('transform', `translate(${width - margin.right}, 0)`)
    .call(d3.axisRight(yScaleLine).ticks(5).tickFormat((d) => `${d}%`))

  const tooltip = ensureTooltip()

  // Bars
  svg.append('g')
    .attr('class', 'bars-group')
    .selectAll('rect.bar')
    .data(data, (d) => d.category)
    .join('rect')
    .attr('class', 'bar')
    .attr('x', (d) => xScale(d.category))
    .attr('y', (d) => yScaleBars(Number(d.total_minutes)))
    .attr('width', xScale.bandwidth())
    .attr('height', (d) => (height - margin.bottom) - yScaleBars(Number(d.total_minutes)))
    .attr('fill', (d) => CATEGORY_COLORS[d.category] ?? '#94A3B8')
    .attr('rx', 3)
    .style('cursor', 'pointer')
    .on('mouseover', (event, d) => {
      tooltip.style('visibility', 'visible').html(`
        <strong>${categoryLabel(d.category)}</strong><br>
        Total: ${formatNumber(d.total_minutes)} menit<br>
        Persentase: ${formatNumber(d.percentage)}%<br>
        Kumulatif: ${formatNumber(d.cumulative)}%
      `)
    })
    .on('mousemove', (event) => {
      tooltip.style('top', `${event.pageY - 10}px`).style('left', `${event.pageX + 10}px`)
    })
    .on('mouseout', () => tooltip.style('visibility', 'hidden'))

  // Garis kumulatif
  const lineGen = d3.line()
    .x((d) => xScale(d.category) + xScale.bandwidth() / 2)
    .y((d) => yScaleLine(Number(d.cumulative)))

  svg.append('path')
    .datum(data)
    .attr('class', 'cumulative-line')
    .attr('fill', 'none')
    .attr('d', lineGen)

  svg.append('g')
    .selectAll('circle.cumulative-point')
    .data(data)
    .join('circle')
    .attr('class', 'cumulative-point')
    .attr('cx', (d) => xScale(d.category) + xScale.bandwidth() / 2)
    .attr('cy', (d) => yScaleLine(Number(d.cumulative)))
    .attr('r', 4)

  // Garis 80% (referensi vital few)
  svg.append('line')
    .attr('class', 'threshold-80')
    .attr('x1', margin.left)
    .attr('x2', width - margin.right)
    .attr('y1', yScaleLine(80))
    .attr('y2', yScaleLine(80))
}

let tooltipEl = null
function ensureTooltip() {
  if (tooltipEl) return tooltipEl
  tooltipEl = d3.select('body').append('div')
    .attr('class', 'pareto-tooltip')
    .style('position', 'absolute')
    .style('visibility', 'hidden')
  return tooltipEl
}

let resizeObserver = null

onMounted(async () => {
  await nextTick()
  renderChart()
  resizeObserver = new ResizeObserver(() => renderChart())
  if (chartContainer.value) resizeObserver.observe(chartContainer.value)
})

onBeforeUnmount(() => {
  if (resizeObserver && chartContainer.value) resizeObserver.unobserve(chartContainer.value)
  if (tooltipEl) tooltipEl.remove()
})

watch(rows, () => nextTick(() => renderChart()))
watch(() => props.workCenterId, () => fetchPareto())
</script>

<style scoped>
.pareto-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.pareto-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.pareto-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0F172A;
}

.pareto-subtitle {
  font-size: 0.75rem;
  color: #94A3B8;
  margin: 0.15rem 0 0;
}

.filter-form {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.6875rem;
  color: #475569;
}

.input {
  padding: 0.35rem 0.55rem;
  font-size: 0.75rem;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
}

.btn {
  padding: 0.4rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 6px;
  border: 1px solid #E2E8F0;
  background: #FFFFFF;
  color: #334155;
  cursor: pointer;
}

.btn:hover:not(:disabled) { background: #F8FAFC; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.chart-container {
  position: relative;
  min-height: 200px;
}

.chart-loading,
.chart-empty {
  padding: 2.5rem 1rem;
  text-align: center;
  color: #94A3B8;
  font-size: 0.8125rem;
}

.pareto-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.pareto-table th {
  text-align: left;
  padding: 0.5rem 0.7rem;
  background: #F8FAFC;
  color: #64748B;
  font-size: 0.6875rem;
  text-transform: uppercase;
  border-bottom: 1px solid #E5E7EB;
}

.pareto-table td {
  padding: 0.5rem 0.7rem;
  border-bottom: 1px solid #F1F5F9;
}

.pareto-table th.num,
.pareto-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
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

:deep(.x-axis text),
:deep(.y-axis-left text),
:deep(.y-axis-right text) {
  font-size: 0.6875rem;
  fill: #64748B;
}

:deep(.x-axis path), :deep(.x-axis line),
:deep(.y-axis-left path), :deep(.y-axis-left line),
:deep(.y-axis-right path), :deep(.y-axis-right line) {
  stroke: #CBD5E1;
}

:deep(.cumulative-line) {
  stroke: #0F172A;
  stroke-width: 2;
}

:deep(.cumulative-point) {
  fill: #0F172A;
  stroke: #FFFFFF;
  stroke-width: 1.5;
}

:deep(.threshold-80) {
  stroke: #EF4444;
  stroke-width: 1;
  stroke-dasharray: 4 3;
  opacity: 0.6;
}
</style>

<style>
.pareto-tooltip {
  background: #0F172A;
  color: #F8FAFC;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 0.75rem;
  line-height: 1.4;
  box-shadow: 0 8px 16px rgba(15, 23, 42, 0.25);
  z-index: 9999;
  pointer-events: none;
}
</style>