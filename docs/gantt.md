# docs/gantt.md — D3.js Gantt Chart Specification

## Referensi
- Bostock, M. et al. — D3.js Documentation: https://d3js.org
- Pinedo, M.L. (2016). *Scheduling: Theory, Algorithms, and Systems*, Bab 6 — visualisasi JSSP

---

## Data Format dari Backend

`GanttBuilderService::build(Schedule $schedule)` mengembalikan JSON:

```json
{
  "schedule": {
    "id": 1,
    "algorithm": "cr",
    "makespan_minutes": 540,
    "total_tardiness_minutes": 60,
    "late_wo_count": 1,
    "mean_flow_time_minutes": 280,
    "scheduled_from": "2024-01-15T07:00:00"
  },
  "work_centers": [
    { "id": 1, "name": "Mesin Bubut 01", "code": "M01" },
    { "id": 2, "name": "Mesin Frais 01", "code": "M02" }
  ],
  "work_orders": [
    { "id": 10, "name": "WO-2024-010", "product": "Poros A", "due_date": "2024-01-15T16:00:00", "is_late": false },
    { "id": 11, "name": "WO-2024-011", "product": "Bracket B", "due_date": "2024-01-15T14:00:00", "is_late": true }
  ],
  "assignments": [
    {
      "wo_operation_id": 101,
      "work_order_id": 10,
      "work_order_name": "WO-2024-010",
      "work_center_id": 1,
      "work_center_name": "Mesin Bubut 01",
      "sequence": 1,
      "start_at": "2024-01-15T07:00:00",
      "end_at": "2024-01-15T08:30:00",
      "duration_minutes": 90,
      "is_late": false
    }
  ]
}
```

---

## Layout SVG

```
┌────────────────────────────────────────────────────────────┐
│  [Summary Cards: Makespan | Total Tardiness | Late WO | MFT] │
│  [Toggle: SPT | EDD | CR | FIFO]                            │
├──────────┬─────────────────────────────────────────────────┤
│  Mesin   │  07:00   08:00   09:00  ...  16:00              │
├──────────┼─────────────────────────────────────────────────┤
│  M01     │  [WO-010 Op1 ████] [WO-011 Op2 ██]             │
│  M02     │          [WO-011 Op1 ███] [WO-010 Op2 █████]   │
│  M03     │  [WO-012 Op1 ██████████████]                    │
│          │                         ↑                       │
│          │                    due date line (merah)         │
└──────────┴─────────────────────────────────────────────────┘
```

---

## D3.js Implementation Guide

### Skala

```js
// Sumbu X: waktu
const xScale = d3.scaleTime()
  .domain([scheduledFrom, maxEndTime])
  .range([LABEL_WIDTH, width])

// Sumbu Y: work centers (band scale)
const yScale = d3.scaleBand()
  .domain(workCenters.map(wc => wc.id))
  .range([0, height])
  .padding(0.2)

// Warna per WO (ordinal scale, 10 warna berbeda)
const colorScale = d3.scaleOrdinal(d3.schemeTableau10)
  .domain(workOrders.map(wo => wo.id))
```

### Bar per Operasi

```js
svg.selectAll('.assignment')
  .data(assignments)
  .join('rect')
  .attr('class', d => `assignment ${d.is_late ? 'late' : ''}`)
  .attr('x', d => xScale(new Date(d.start_at)))
  .attr('y', d => yScale(d.work_center_id))
  .attr('width', d => xScale(new Date(d.end_at)) - xScale(new Date(d.start_at)))
  .attr('height', yScale.bandwidth())
  .attr('fill', d => d.is_late ? '#EF4444' : colorScale(d.work_order_id))
  .attr('rx', 3)
  .attr('opacity', 0.85)
```

### Due Date Lines

```js
// Garis vertikal merah per WO yang punya due date
svg.selectAll('.due-date-line')
  .data(workOrders)
  .join('line')
  .attr('class', 'due-date-line')
  .attr('x1', d => xScale(new Date(d.due_date)))
  .attr('x2', d => xScale(new Date(d.due_date)))
  .attr('y1', 0)
  .attr('y2', height)
  .attr('stroke', '#EF4444')
  .attr('stroke-width', 1.5)
  .attr('stroke-dasharray', '4,3')
  .attr('opacity', 0.7)
```

### Tooltip

```js
const tooltip = d3.select('body').append('div')
  .attr('class', 'gantt-tooltip')
  .style('position', 'absolute')
  .style('visibility', 'hidden')

svg.selectAll('.assignment')
  .on('mouseover', (event, d) => {
    tooltip
      .style('visibility', 'visible')
      .html(`
        <strong>${d.work_order_name}</strong><br>
        Operasi: ${d.sequence}<br>
        Mesin: ${d.work_center_name}<br>
        Mulai: ${formatTime(d.start_at)}<br>
        Selesai: ${formatTime(d.end_at)}<br>
        Durasi: ${d.duration_minutes} menit<br>
        Status: ${d.is_late ? '⚠️ Terlambat' : '✅ On Time'}
      `)
  })
  .on('mousemove', (event) => {
    tooltip
      .style('top', (event.pageY - 10) + 'px')
      .style('left', (event.pageX + 10) + 'px')
  })
  .on('mouseout', () => tooltip.style('visibility', 'hidden'))
```

---

## Interactivity Spec

| Aksi | Behavior |
|---|---|
| Hover bar | Tooltip: nama WO, produk, mesin, durasi, on-time/late |
| Klik bar | Highlight semua bar WO yang sama (opacity lainnya turun ke 0.3) |
| Klik lagi | Deselect, kembali normal |
| Toggle algoritma | Fetch data baru dari API, re-render seluruh Gantt tanpa reload halaman |
| Zoom in/out | Mouse wheel pada area Gantt, scale X berubah (min 1 jam, max full range) |
| Scroll horizontal | Jika makespan > lebar layar, scrollable |
| Klik nama WO (legend) | Sama dengan klik bar: highlight semua operasi WO tersebut |

---

## Vue Component Structure

```vue
<!-- resources/js/Components/GanttChart.vue -->
<template>
  <div class="gantt-wrapper">
    <div class="algo-toggle">
      <button v-for="algo in algorithms" :key="algo"
        :class="{ active: currentAlgo === algo }"
        @click="switchAlgorithm(algo)">
        {{ algo.toUpperCase() }}
      </button>
    </div>

    <div class="summary-cards">
      <!-- KpiCard per metric -->
    </div>

    <div ref="ganttContainer" class="gantt-container overflow-x-auto">
      <!-- D3 renders here -->
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import * as d3 from 'd3'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  initialData: Object,   // data dari server-side render pertama
  scheduleIds: Object,   // { spt: 1, edd: 2, cr: 3, fifo: 4 }
})

const currentAlgo = ref('cr')
const ganttData = ref(props.initialData)
const ganttContainer = ref(null)

async function switchAlgorithm(algo) {
  currentAlgo.value = algo
  const response = await fetch(route('schedules.gantt-data', props.scheduleIds[algo]))
  ganttData.value = await response.json()
}

watch(ganttData, () => renderGantt())
onMounted(() => renderGantt())

function renderGantt() {
  // D3 render logic di sini
  // Clear existing, lalu build ulang dari ganttData.value
}
</script>
```

---

## CSS Variables (Tailwind Compatible)

```css
.assignment.late    { fill: #EF4444; }  /* red-500 */
.due-date-line      { stroke: #EF4444; }
.gantt-tooltip {
  background: white;
  border: 1px solid #E5E7EB;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 0.875rem;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  z-index: 9999;
}
.gantt-container {
  min-height: 300px;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  padding: 16px;
}
```

---

## API Endpoint

```
GET /api/schedules/{schedule}/gantt-data
Response: JSON sesuai format di atas
Auth: Sanctum (atau session-based Inertia)
```

Endpoint ini dipanggil client-side saat user toggle algoritma. Data awal di-pass
lewat Inertia props pada load pertama (tidak perlu fetch ulang saat mount).
