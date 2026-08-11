<template>
  <div class="mrp-grid">
<div class="mrp-grid__header">
      <div class="mrp-grid__title-group">
        <span class="mrp-grid__led" :class="{ 'mrp-grid__led--active': !!mrpRun }"></span>
        <div>
          <span class="mrp-grid__title">Grid MRP</span>
          <p class="mrp-grid__subtitle" v-if="mrpRun">
            Run #{{ mrpRun.id }} · Schedule {{ mrpRun.schedule?.algorithm?.toUpperCase() ?? '–' }}
            · Dihitung {{ formatDateTime(mrpRun.computed_at) }}
          </p>
        </div>
      </div>
      <button type="button" class="btn btn--ghost btn--small" :disabled="isLoading" @click="refresh">
        {{ isLoading ? 'Memuat…' : '↻ Refresh' }}
      </button>
    </div>

    <div v-if="!mrpRun" class="mrp-grid__empty">
      Belum ada MRP run untuk ditampilkan. Jalankan Schedule terlebih dahulu
      (MRP terpicu otomatis via <code>ScheduleCreated</code> event).
    </div>

    <div v-else-if="periods.length === 0" class="mrp-grid__empty">
      MRP run ini belum memiliki requirements (grid kosong).
    </div>

    <div v-else class="mrp-grid__table-wrapper">
      <table class="mrp-table">
        <thead>
          <tr>
            <th class="mrp-table__material-col">Material</th>
            <th class="mrp-table__row-label-col"></th>
            <th v-for="period in periods" :key="period" class="mrp-table__period-col">
              {{ formatPeriod(period) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in materialGroups" :key="group.materialId">
            <tr v-for="(rowDef, idx) in rowDefs" :key="`${group.materialId}-${rowDef.key}`" class="mrp-row">
              <td v-if="idx === 0" :rowspan="rowDefs.length" class="mrp-table__material-col mrp-table__material-name">
                {{ group.materialName }}
              </td>
              <td class="mrp-table__row-label-col">{{ rowDef.label }}</td>
              <td
                v-for="period in periods"
                :key="period"
                class="mrp-table__cell"
                :class="cellClass(group, period, rowDef.key)"
              >
                {{ formatNumber(cellValue(group, period, rowDef.key)) }}
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div v-if="mrpRun && periods.length > 0" class="mrp-grid__legend">
      <span class="legend-item"><span class="legend-swatch legend-swatch--nr"></span> Net Requirement &gt; 0</span>
      <span class="legend-item"><span class="legend-swatch legend-swatch--por"></span> Planned Order Release</span>
    </div>
  </div>
</template>

<script setup>
/**
 * MrpGrid.vue — grid MRP per material × periode (FR-07), tabel native
 * (bukan D3) karena data ini murni tabular, tidak spasial/temporal seperti
 * Gantt.
 *
 * Sumber data: GET /mrp/runs/{mrpRun} (MrpController::show()):
 *   { id, schedule_id, computed_at, created_by, schedule: {...},
 *     requirements: [{ id, mrp_run_id, material_id, period_date,
 *       gross_requirement, scheduled_receipts, projected_on_hand,
 *       net_requirement, planned_order_release, material: { id, name, sku, unit } }] }
 *   Semua angka string (cast decimal:4 di model MrpRequirement).
 *
 * Baris flat per (material, periode) di-pivot di sini menjadi grid
 * material × periode dengan 5 baris data per material (GR/SR/POH/NR/POR),
 * mengikuti format contoh docs/inventory.md § Contoh MRP Grid.
 *
 * CATATAN SKEMA (dari claude.md): planned_order_release disimpan di baris
 * period_date yang SAMA dengan net_requirement (need-date), bukan di
 * release_date terpisah -- kolom tabel period_date di sini murni need-date.
 */
import { ref, computed, watch } from 'vue'

const props = defineProps({
  initialMrpRun: { type: Object, default: null },
  mrpRunId: { type: [Number, String], default: null },
  mrpRunUrl: {
    type: Function,
    default: (id) => `/mrp/runs/${id}`,
  },
})

const mrpRun = ref(props.initialMrpRun)
const isLoading = ref(false)

// Sinkronkan ulang tanpa syarat setiap kali parent mengirim prop baru
// (mis. setelah router.reload() di Dashboard.vue). Pola ini SENGAJA
// tanpa guard `if (val)` -- lihat pelajaran OeeGauge.vue di claude.md:
// guard seperti itu justru mencegah reset/update saat prop memang
// berubah jadi nilai valid baru.
watch(() => props.initialMrpRun, (val) => {
  mrpRun.value = val
})


const rowDefs = [
  { key: 'gross_requirement', label: 'GR' },
  { key: 'scheduled_receipts', label: 'SR' },
  { key: 'projected_on_hand', label: 'POH' },
  { key: 'net_requirement', label: 'NR' },
  { key: 'planned_order_release', label: 'POR' },
]

const periods = computed(() => {
  if (!mrpRun.value?.requirements) return []
  const set = new Set(mrpRun.value.requirements.map((r) => r.period_date))
  return Array.from(set).sort()
})

const materialGroups = computed(() => {
  if (!mrpRun.value?.requirements) return []
  const byMaterial = new Map()

  for (const req of mrpRun.value.requirements) {
    if (!byMaterial.has(req.material_id)) {
      byMaterial.set(req.material_id, {
        materialId: req.material_id,
        materialName: req.material?.name ?? `Material #${req.material_id}`,
        rowsByPeriod: {},
      })
    }
    byMaterial.get(req.material_id).rowsByPeriod[req.period_date] = req
  }

  return Array.from(byMaterial.values()).sort((a, b) => a.materialName.localeCompare(b.materialName))
})

function cellValue(group, period, key) {
  return group.rowsByPeriod[period]?.[key] ?? null
}

function cellClass(group, period, key) {
  const row = group.rowsByPeriod[period]
  if (!row) return ''
  if (key === 'net_requirement' && toNumber(row.net_requirement) > 0) return 'mrp-table__cell--nr'
  if (key === 'planned_order_release' && toNumber(row.planned_order_release) > 0) return 'mrp-table__cell--por'
  return ''
}

function toNumber(value) {
  return value === null || value === undefined ? 0 : Number(value)
}

function formatNumber(value) {
  if (value === null || value === undefined) return '–'
  const n = Number(value)
  if (n === 0) return '0'
  return n.toLocaleString('id-ID', { maximumFractionDigits: 2 })
}

function formatPeriod(period) {
  return new Date(period).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
}

function formatDateTime(iso) {
  if (!iso) return '–'
  return new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

async function refresh() {
  const id = mrpRun.value?.id ?? props.mrpRunId
  if (!id) return
  isLoading.value = true
  try {
    const response = await fetch(props.mrpRunUrl(id), { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error(`Gagal memuat MRP run (${response.status})`)
    mrpRun.value = await response.json()
  } catch (error) {
    console.error('MrpGrid: gagal fetch mrp run', error)
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.mrp-grid {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.25rem;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 10px;
  font-family: var(--font-body);
}

.mrp-grid__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.mrp-grid__title-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.mrp-grid__led {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 2px;
  background: var(--hairline-strong);
  flex-shrink: 0;
}
.mrp-grid__led--active {
  background: var(--signal-green);
  box-shadow: 0 0 6px 1px rgba(74, 155, 110, 0.5);
}

.mrp-grid__title {
  font-family: var(--font-display);
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--data-ink);
}

.mrp-grid__subtitle {
  font-size: 0.75rem;
  color: var(--data-ink-muted);
  margin: 0.15rem 0 0;
}

.mrp-grid__empty {
  padding: 2rem 1rem;
  text-align: center;
  color: var(--data-ink-muted);
  font-size: 0.8125rem;
}

.mrp-grid__empty code {
  background: var(--hairline-soft);
  color: var(--data-ink);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.mrp-grid__table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--hairline-border);
  border-radius: 8px;
}

.mrp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.75rem;
}

.mrp-table th,
.mrp-table td {
  padding: 0.4rem 0.65rem;
  text-align: right;
  border-bottom: 1px solid var(--hairline-soft);
  white-space: nowrap;
}

.mrp-table thead th {
  background: var(--surface-steel);
  color: var(--data-ink-muted);
  font-weight: 600;
  position: sticky;
  top: 0;
}

.mrp-table__material-col {
  text-align: left;
  position: sticky;
  left: 0;
  background: var(--panel-graphite);
  z-index: 1;
  border-right: 1px solid var(--hairline-border);
}

.mrp-table__material-name {
  font-weight: 600;
  color: var(--data-ink);
  vertical-align: top;
}

.mrp-table__row-label-col {
  text-align: left;
  color: var(--data-ink-muted);
  font-weight: 600;
  font-size: 0.6875rem;
}

.mrp-table__period-col {
  min-width: 4.5rem;
}

.mrp-table__cell {
  font-variant-numeric: tabular-nums;
  color: var(--data-ink);
}

.mrp-table__cell--nr {
  background: rgba(232, 163, 61, 0.18);
  font-weight: 700;
  color: var(--signal-amber);
}

.mrp-table__cell--por {
  background: rgba(91, 141, 239, 0.18);
  font-weight: 700;
  color: #5B8DEF;
}

.mrp-row:hover td {
  background-color: var(--surface-steel);
}

.mrp-row:hover td.mrp-table__cell--nr {
  background-color: rgba(232, 163, 61, 0.26);
}

.mrp-row:hover td.mrp-table__cell--por {
  background-color: rgba(91, 141, 239, 0.26);
}

.mrp-grid__legend {
  display: flex;
  gap: 1.25rem;
  font-size: 0.6875rem;
  color: var(--data-ink-muted);
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.legend-swatch {
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 3px;
}

.legend-swatch--nr { background: rgba(232, 163, 61, 0.3); border: 1px solid var(--signal-amber); }
.legend-swatch--por { background: rgba(91, 141, 239, 0.3); border: 1px solid #5B8DEF; }

.btn {
  border-radius: 6px;
  border: 1px solid var(--hairline-border);
  cursor: pointer;
  font-weight: 600;
  flex-shrink: 0;
}

.btn--small {
  padding: 0.3rem 0.65rem;
  font-size: 0.6875rem;
}

.btn--ghost {
  background: var(--panel-graphite);
  color: var(--data-ink-muted);
}

.btn--ghost:hover:not(:disabled) { background: var(--surface-steel); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
