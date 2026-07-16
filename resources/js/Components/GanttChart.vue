<template>
  <div class="gantt-wrapper">
    <!-- Toggle Algoritma -->
    <div class="algo-toggle" role="tablist" aria-label="Pilih algoritma penjadwalan">
      <button
        v-for="algo in algorithms"
        :key="algo"
        type="button"
        role="tab"
        :aria-selected="currentAlgo === algo"
        class="algo-btn"
        :class="{ 'algo-btn--active': currentAlgo === algo }"
        :disabled="isLoading"
        @click="switchAlgorithm(algo)"
      >
        {{ algo.toUpperCase() }}
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards" aria-live="polite">
      <div class="kpi-card">
        <span class="kpi-card__label">Makespan</span>
        <span class="kpi-card__value">{{ formatMinutes(ganttData.schedule.makespan_minutes) }}</span>
      </div>
      <div class="kpi-card" :class="{ 'kpi-card--warn': ganttData.schedule.total_tardiness_minutes > 0 }">
        <span class="kpi-card__label">Total Tardiness</span>
        <span class="kpi-card__value">{{ formatMinutes(ganttData.schedule.total_tardiness_minutes) }}</span>
      </div>
      <div class="kpi-card" :class="{ 'kpi-card--danger': ganttData.schedule.late_wo_count > 0 }">
        <span class="kpi-card__label">WO Terlambat</span>
        <span class="kpi-card__value">{{ ganttData.schedule.late_wo_count }}</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-card__label">Mean Flow Time</span>
        <span class="kpi-card__value">{{ formatMinutes(ganttData.schedule.mean_flow_time_minutes) }}</span>
      </div>
    </div>

    <!-- Legend: klik nama WO = highlight, sama seperti klik bar -->
    <div class="wo-legend">
      <button
        v-for="wo in ganttData.work_orders"
        :key="wo.id"
        type="button"
        class="wo-legend__item"
        :class="{ 'wo-legend__item--dimmed': selectedWorkOrderId !== null && selectedWorkOrderId !== wo.id }"
        :style="{ '--wo-color': colorScale(wo.id) }"
        @click="toggleSelection(wo.id)"
      >
        <span class="wo-legend__swatch" :class="{ 'wo-legend__swatch--late': wo.is_late }"></span>
        {{ wo.name }}
        <span v-if="wo.is_late" class="wo-legend__late-tag">terlambat</span>
      </button>
    </div>

    <div
      ref="ganttContainer"
      class="gantt-container"
      :aria-busy="isLoading"
    >
      <div v-if="isLoading" class="gantt-loading">Memuat jadwal…</div>
      <div v-else-if="ganttData.assignments.length === 0" class="gantt-empty">
        Belum ada operasi terjadwal untuk algoritma ini.
      </div>
      <svg v-show="!isLoading && ganttData.assignments.length > 0" ref="svgRef"></svg>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import * as d3 from 'd3'

const props = defineProps({
  initialData: { type: Object, required: true },   // data dari server-side render pertama (format docs/gantt.md)
  scheduleIds: { type: Object, required: true },    // { spt: 1, edd: 2, cr: 3, fifo: 4 }
  ganttDataUrl: {
    // fungsi (scheduleId) => url, agar tidak bergantung ke Ziggy route() secara hardcode
    type: Function,
    default: (scheduleId) => `/api/schedules/${scheduleId}/gantt-data`,
  },
})

const algorithms = ['spt', 'edd', 'cr', 'fifo']

const currentAlgo = ref(props.initialData?.schedule?.algorithm ?? 'cr')
const ganttData = ref(props.initialData)
const isLoading = ref(false)
const selectedWorkOrderId = ref(null)

const ganttContainer = ref(null)
const svgRef = ref(null)

const MARGIN = { top: 24, right: 24, bottom: 8, left: 140 }
const ROW_HEIGHT = 40
const MIN_ZOOM_MS = 1000 * 60 * 60 // 1 jam
let fullDomainMs = null // range penuh, batas atas zoom-out

let zoomBehavior = null
let xScale = null
let yScale = null
let colorScaleFn = null
let tooltipEl = null

function colorScale(workOrderId) {
  return colorScaleFn ? colorScaleFn(workOrderId) : '#94A3B8'
}

async function switchAlgorithm(algo) {
  if (algo === currentAlgo.value || isLoading.value) return

  const scheduleId = props.scheduleIds[algo]
  if (!scheduleId) return

  isLoading.value = true
  selectedWorkOrderId.value = null

  try {
    const response = await fetch(props.ganttDataUrl(scheduleId), {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) throw new Error(`Gagal memuat data gantt (${response.status})`)
    ganttData.value = await response.json()
    currentAlgo.value = algo
  } catch (error) {
    console.error('GanttChart: gagal fetch data algoritma', algo, error)
  } finally {
    isLoading.value = false
  }
}

function toggleSelection(workOrderId) {
  selectedWorkOrderId.value = selectedWorkOrderId.value === workOrderId ? null : workOrderId
  applySelectionStyles()
}

function formatMinutes(minutes) {
  if (minutes === null || minutes === undefined) return '–'
  const rounded = Math.round(minutes)
  const h = Math.floor(rounded / 60)
  const m = rounded % 60
  return h > 0 ? `${h}j ${m}m` : `${m}m`
}

function formatTime(iso) {
  if (!iso) return '–'
  const d = new Date(iso)
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function ensureTooltip() {
  if (tooltipEl) return tooltipEl
  tooltipEl = d3.select('body').append('div')
    .attr('class', 'gantt-tooltip')
    .style('position', 'absolute')
    .style('visibility', 'hidden')
  return tooltipEl
}

function applySelectionStyles() {
  const svg = d3.select(svgRef.value)
  const selected = selectedWorkOrderId.value

  svg.selectAll('.assignment')
    .transition().duration(150)
    .attr('opacity', (d) => {
      if (selected === null) return 0.85
      return d.work_order_id === selected ? 0.95 : 0.25
    })
}

function renderGantt() {
  if (!svgRef.value || !ganttContainer.value) return
  if (!ganttData.value.assignments.length) return

  const data = ganttData.value
  const containerWidth = Math.max(ganttContainer.value.clientWidth, 640)
  const rowCount = Math.max(data.work_centers.length, 1)
  const height = MARGIN.top + MARGIN.bottom + rowCount * ROW_HEIGHT

  // domain waktu penuh: dari scheduled_from sampai end_at terjauh
  const scheduledFrom = new Date(data.schedule.scheduled_from)
  const maxEnd = d3.max(data.assignments, (d) => new Date(d.end_at))
  const domainStart = scheduledFrom
  const domainEnd = maxEnd ?? scheduledFrom
  fullDomainMs = [domainStart, domainEnd]

  const width = containerWidth

  const svg = d3.select(svgRef.value)
  svg.selectAll('*').remove()
  svg
    .attr('width', width)
    .attr('height', height)
    .attr('viewBox', `0 0 ${width} ${height}`)

  const clipId = 'gantt-clip'
  svg.append('defs').append('clipPath')
    .attr('id', clipId)
    .append('rect')
    .attr('x', MARGIN.left)
    .attr('y', 0)
    .attr('width', width - MARGIN.left - MARGIN.right)
    .attr('height', height)

  xScale = d3.scaleTime()
    .domain([domainStart, domainEnd])
    .range([MARGIN.left, width - MARGIN.right])

  yScale = d3.scaleBand()
    .domain(data.work_centers.map((wc) => wc.id))
    .range([MARGIN.top, height])
    .padding(0.25)

  colorScaleFn = d3.scaleOrdinal(d3.schemeTableau10)
    .domain(data.work_orders.map((wo) => wo.id))

  // Sumbu waktu di atas
  const xAxis = d3.axisTop(xScale).ticks(Math.max(width / 120, 4)).tickFormat(d3.timeFormat('%H:%M'))
  svg.append('g')
    .attr('class', 'x-axis')
    .attr('transform', `translate(0, ${MARGIN.top})`)
    .call(xAxis)

  // Label mesin di kiri
  svg.append('g')
    .attr('class', 'wc-labels')
    .selectAll('text')
    .data(data.work_centers)
    .join('text')
    .attr('x', MARGIN.left - 12)
    .attr('y', (d) => yScale(d.id) + yScale.bandwidth() / 2)
    .attr('dy', '0.35em')
    .attr('text-anchor', 'end')
    .attr('class', 'wc-label')
    .text((d) => d.name)

  // Garis pemisah baris per mesin
  svg.append('g')
    .selectAll('line.row-divider')
    .data(data.work_centers)
    .join('line')
    .attr('class', 'row-divider')
    .attr('x1', MARGIN.left)
    .attr('x2', width - MARGIN.right)
    .attr('y1', (d) => yScale(d.id) + yScale.bandwidth() + yScale.paddingOuter() * yScale.step() / 2)
    .attr('y2', (d) => yScale(d.id) + yScale.bandwidth() + yScale.paddingOuter() * yScale.step() / 2)

  // Grup dengan clip-path untuk zoom/pan
  const plotArea = svg.append('g').attr('clip-path', `url(#${clipId})`)

  // Due date lines
  plotArea.append('g')
    .attr('class', 'due-date-lines')
    .selectAll('line.due-date-line')
    .data(data.work_orders.filter((wo) => wo.due_date))
    .join('line')
    .attr('class', 'due-date-line')
    .attr('x1', (d) => xScale(new Date(d.due_date)))
    .attr('x2', (d) => xScale(new Date(d.due_date)))
    .attr('y1', MARGIN.top)
    .attr('y2', height)

  const tooltip = ensureTooltip()

  // Bar per operasi
  plotArea.append('g')
    .attr('class', 'assignments-group')
    .selectAll('rect.assignment')
    .data(data.assignments, (d) => d.wo_operation_id)
    .join('rect')
    .attr('class', (d) => `assignment${d.is_late ? ' assignment--late' : ''}`)
    .attr('x', (d) => xScale(new Date(d.start_at)))
    .attr('y', (d) => yScale(d.work_center_id))
    .attr('width', (d) => Math.max(xScale(new Date(d.end_at)) - xScale(new Date(d.start_at)), 1))
    .attr('height', yScale.bandwidth())
    .attr('rx', 3)
    .attr('fill', (d) => (d.is_late ? '#EF4444' : colorScaleFn(d.work_order_id)))
    .attr('opacity', 0.85)
    .style('cursor', 'pointer')
    .on('mouseover', (event, d) => {
      tooltip.style('visibility', 'visible').html(`
        <strong>${escapeHtml(d.work_order_name)}</strong><br>
        Operasi ke-${d.sequence}<br>
        Mesin: ${escapeHtml(d.work_center_name)}<br>
        Mulai: ${formatTime(d.start_at)}<br>
        Selesai: ${formatTime(d.end_at)}<br>
        Durasi: ${d.duration_minutes} menit<br>
        Status: ${d.is_late ? '⚠️ Terlambat' : '✅ Tepat waktu'}
      `)
    })
    .on('mousemove', (event) => {
      tooltip
        .style('top', `${event.pageY - 10}px`)
        .style('left', `${event.pageX + 10}px`)
    })
    .on('mouseout', () => tooltip.style('visibility', 'hidden'))
    .on('click', (event, d) => toggleSelection(d.work_order_id))

  applySelectionStyles()

  // Zoom & pan horizontal (min 1 jam, max full range), scroll vertikal biasa via overflow
  zoomBehavior = d3.zoom()
    .scaleExtent([1, (domainEnd - domainStart) / MIN_ZOOM_MS])
    .translateExtent([[MARGIN.left, 0], [width - MARGIN.right, height]])
    .extent([[MARGIN.left, 0], [width - MARGIN.right, height]])
    .on('zoom', (event) => {
      const newX = event.transform.rescaleX(xScale)
      svg.select('.x-axis').call(d3.axisTop(newX).ticks(Math.max(width / 120, 4)).tickFormat(d3.timeFormat('%H:%M')))
      plotArea.selectAll('rect.assignment')
        .attr('x', (d) => newX(new Date(d.start_at)))
        .attr('width', (d) => Math.max(newX(new Date(d.end_at)) - newX(new Date(d.start_at)), 1))
      plotArea.selectAll('line.due-date-line')
        .attr('x1', (d) => newX(new Date(d.due_date)))
        .attr('x2', (d) => newX(new Date(d.due_date)))
    })

  svg.call(zoomBehavior)
}

function escapeHtml(str) {
  const div = document.createElement('div')
  div.textContent = str ?? ''
  return div.innerHTML
}

let resizeObserver = null

onMounted(async () => {
  await nextTick()
  renderGantt()
  resizeObserver = new ResizeObserver(() => renderGantt())
  if (ganttContainer.value) resizeObserver.observe(ganttContainer.value)
})

onBeforeUnmount(() => {
  if (resizeObserver && ganttContainer.value) resizeObserver.unobserve(ganttContainer.value)
  if (tooltipEl) tooltipEl.remove()
})

watch(ganttData, () => nextTick(() => renderGantt()))
watch(selectedWorkOrderId, () => applySelectionStyles())
</script>

<style scoped>
.gantt-wrapper {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.algo-toggle {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.25rem;
  background: #F1F5F9;
  border-radius: 8px;
  width: fit-content;
}

.algo-btn {
  padding: 0.4rem 0.9rem;
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: #475569;
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.algo-btn:hover:not(:disabled) {
  background: #E2E8F0;
}

.algo-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.algo-btn--active {
  background: #1E293B;
  color: #F8FAFC;
}

.algo-btn:focus-visible,
.wo-legend__item:focus-visible {
  outline: 2px solid #F59E0B;
  outline-offset: 2px;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.75rem 1rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
}

.kpi-card__label {
  font-size: 0.75rem;
  color: #64748B;
}

.kpi-card__value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.kpi-card--warn .kpi-card__value { color: #B45309; }
.kpi-card--danger .kpi-card__value { color: #DC2626; }

.wo-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.wo-legend__item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.25rem 0.6rem;
  font-size: 0.75rem;
  color: #334155;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 999px;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.wo-legend__item--dimmed {
  opacity: 0.35;
}

.wo-legend__swatch {
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 999px;
  background: var(--wo-color, #94A3B8);
  flex-shrink: 0;
}

.wo-legend__swatch--late {
  background: #EF4444;
}

.wo-legend__late-tag {
  color: #DC2626;
  font-weight: 600;
}

.gantt-container {
  position: relative;
  min-height: 240px;
  overflow-x: auto;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  padding: 12px 8px;
  background: #FFFFFF;
}

.gantt-loading,
.gantt-empty {
  padding: 2.5rem 1rem;
  text-align: center;
  color: #64748B;
  font-size: 0.875rem;
}

:deep(.wc-label) {
  font-size: 0.75rem;
  fill: #334155;
}

:deep(.x-axis text) {
  font-size: 0.6875rem;
  fill: #64748B;
}

:deep(.x-axis path),
:deep(.x-axis line) {
  stroke: #CBD5E1;
}

:deep(.row-divider) {
  stroke: #F1F5F9;
  stroke-width: 1;
}

:deep(.due-date-line) {
  stroke: #EF4444;
  stroke-width: 1.5;
  stroke-dasharray: 4 3;
  opacity: 0.7;
}

:deep(.assignment--late) {
  stroke: #B91C1C;
  stroke-width: 1;
}
</style>

<style>
/* Tooltip di-append ke <body>, jadi styling-nya global (bukan scoped) */
.gantt-tooltip {
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