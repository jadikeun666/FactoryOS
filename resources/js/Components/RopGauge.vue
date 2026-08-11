<template>
  <div class="rop-console">
    <div class="rop-console__header">
      <span class="rop-console__title">Status Stok vs ROP</span>
      <button type="button" class="btn btn--ghost btn--small" :disabled="isLoading" @click="refresh">
        {{ isLoading ? 'Memuat…' : '↻ Refresh' }}
      </button>
    </div>

    <div v-if="materials.length === 0" class="rop-console__empty">
      Belum ada material dengan parameter inventory (EOQ/Safety Stock/ROP).
    </div>

    <ul v-else class="material-log">
      <li
        v-for="material in sortedMaterials"
        :key="material.material_id"
        class="material-row"
      >
        <div class="material-row__head">
          <span class="material-row__indicator" :class="`material-row__indicator--${severity(material)}`"></span>
          <span class="material-row__name">{{ material.name }}</span>
          <span class="severity-tag" :class="`severity-tag--${severity(material)}`">
            {{ severityLabel(severity(material)) }}
          </span>
        </div>

        <div class="stock-bar">
          <div class="stock-bar__track">
            <div
              class="stock-bar__zone stock-bar__zone--danger"
              :style="{ width: `${zoneWidth(material, 'safety')}%` }"
            ></div>
            <div
              class="stock-bar__zone stock-bar__zone--warn"
              :style="{ width: `${zoneWidth(material, 'rop')}%` }"
            ></div>
            <div
              class="stock-bar__fill"
              :style="{ width: `${fillWidth(material)}%` }"
            ></div>
            <div
              class="stock-bar__marker"
              :style="{ left: `${markerPosition(material, 'safety_stock')}%` }"
              title="Safety Stock"
            ></div>
            <div
              class="stock-bar__marker stock-bar__marker--rop"
              :style="{ left: `${markerPosition(material, 'rop')}%` }"
              title="Reorder Point"
            ></div>
          </div>
        </div>

        <div class="material-row__figures">
          <span class="figure"><span class="figure__label">QTY</span><span class="figure__value">{{ formatNumber(material.qty_on_hand) }} {{ material.unit }}</span></span>
          <span class="figure"><span class="figure__label">SS</span><span class="figure__value">{{ formatNumber(material.safety_stock) }}</span></span>
          <span class="figure"><span class="figure__label">ROP</span><span class="figure__value">{{ formatNumber(material.rop) }}</span></span>
          <span class="figure"><span class="figure__label">EOQ</span><span class="figure__value">{{ formatNumber(material.eoq) }}</span></span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
/**
 * RopGauge.vue — visual perbandingan qty_on_hand vs safety_stock vs ROP
 * per material (FR-06), dikonsumsi dari GET /inventory/status
 * (InventoryController::status(), read-only, tidak ada kalkulasi).
 *
 * ASUMSI shape data per item (semua angka string, cast decimal:4):
 *   { material_id, name, sku, unit, qty_on_hand, qty_on_order,
 *     safety_stock, rop, eoq, last_updated }
 *
 * TIDAK live-update via Echo (beda dengan OeeGauge.vue) -- tidak ada
 * event broadcast untuk perubahan inventory di docs/architecture.md
 * (InventoryTransacted didokumentasikan tapi tidak broadcast ke frontend,
 * hanya trigger UpdateReorderAlertsListener di backend). Refresh manual
 * via tombol, konsisten dengan sifat data ini (stok berubah lebih jarang
 * dan tidak butuh real-time sekritis OEE).
 *
 * Severity level (murni logic tampilan, BUKAN keputusan bisnis -- itu
 * tetap tanggung jawab CheckReorderAlertsJob/ReorderAlert):
 *   'critical' -> qty_on_hand <= safety_stock
 *   'warning'  -> qty_on_hand <= rop (tapi > safety_stock)
 *   'safe'     -> qty_on_hand > rop
 *
 * REDESIGN VISUAL (2026-08-09): dari grid card-pill generik ke daftar
 * baris "log console" (selaras AlertBanner.vue) -- indikator LED persegi
 * kecil per baris, hairline divider, tipografi mono untuk data. Bar
 * visual (posisi qty vs safety stock vs ROP) DIPERTAHANKAN karena itu
 * informasi fungsional nyata, bukan dekorasi -- hanya kontainernya yang
 * diubah dari card lembut ke baris penuh-lebar. TIDAK ADA perubahan
 * logic JS (computed/fungsi severity/scale/format) di file ini.
 */
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  initialMaterials: { type: Array, default: () => [] },
  statusUrl: { type: String, default: '/inventory/status' },
})

const materials = ref(props.initialMaterials)
const isLoading = ref(false)

onMounted(() => {
  if (materials.value.length === 0) {
    refresh()
  }
})

const sortedMaterials = computed(() =>
  [...materials.value].sort((a, b) => severityRank(severity(b)) - severityRank(severity(a)))
)

function toNumber(value) {
  return value === null || value === undefined ? 0 : Number(value)
}

function severity(material) {
  const qty = toNumber(material.qty_on_hand)
  const safety = toNumber(material.safety_stock)
  const rop = toNumber(material.rop)
  if (qty <= safety) return 'critical'
  if (qty <= rop) return 'warning'
  return 'safe'
}

function severityRank(level) {
  return { critical: 2, warning: 1, safe: 0 }[level] ?? 0
}

function severityLabel(level) {
  return { critical: 'Kritis', warning: 'Perlu Order', safe: 'Aman' }[level] ?? level
}

// Skala visual: 0 sampai max(qty_on_hand, rop * 1.3) supaya marker ROP/Safety Stock selalu terlihat proporsional
function scaleMax(material) {
  const qty = toNumber(material.qty_on_hand)
  const rop = toNumber(material.rop)
  return Math.max(qty, rop * 1.3, 1)
}

function fillWidth(material) {
  const qty = toNumber(material.qty_on_hand)
  return Math.min((qty / scaleMax(material)) * 100, 100)
}

function markerPosition(material, key) {
  const value = toNumber(material[key])
  return Math.min((value / scaleMax(material)) * 100, 100)
}

function zoneWidth(material, zone) {
  if (zone === 'safety') return markerPosition(material, 'safety_stock')
  return markerPosition(material, 'rop')
}

function formatNumber(value) {
  return toNumber(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })
}

async function refresh() {
  isLoading.value = true
  try {
    const response = await fetch(props.statusUrl, { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error(`Gagal memuat status inventory (${response.status})`)
    materials.value = await response.json()
  } catch (error) {
    console.error('RopGauge: gagal fetch status inventory', error)
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.rop-console {
  display: flex;
  flex-direction: column;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 6px;
  font-family: var(--font-body);
  overflow: hidden;
}

.rop-console__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1.1rem;
  border-bottom: 1px solid var(--hairline-border);
  background: var(--surface-steel);
}

.rop-console__title {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--data-ink);
}

.rop-console__empty {
  padding: 1.75rem 1.1rem;
  text-align: center;
  color: var(--data-ink-muted);
  font-size: 0.8125rem;
}

.material-log {
  list-style: none;
  margin: 0;
  padding: 0;
}

.material-row {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem 1.1rem;
  border-bottom: 1px solid var(--hairline-soft);
}

.material-row:last-child {
  border-bottom: none;
}

.material-row__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.material-row__indicator {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 1px;
  flex-shrink: 0;
}

.material-row__indicator--critical { background: var(--signal-red); }
.material-row__indicator--warning { background: var(--signal-amber); }
.material-row__indicator--safe { background: var(--signal-green); }

.material-row__name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--data-ink);
  flex: 1;
}

.severity-tag {
  font-family: var(--font-display);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 0.1rem 0.5rem;
  border-radius: 3px;
  text-transform: uppercase;
  flex-shrink: 0;
}

.severity-tag--critical { background: rgba(214, 69, 69, 0.18); color: var(--signal-red); }
.severity-tag--warning { background: rgba(232, 163, 61, 0.18); color: var(--signal-amber); }
.severity-tag--safe { background: rgba(74, 155, 110, 0.18); color: var(--signal-green); }

.stock-bar__track {
  position: relative;
  height: 0.4rem;
  border-radius: 2px;
  background: var(--hairline-soft);
  overflow: hidden;
}

.stock-bar__zone {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
}

.stock-bar__zone--danger { background: rgba(214, 69, 69, 0.2); z-index: 1; }
.stock-bar__zone--warn { background: rgba(232, 163, 61, 0.2); z-index: 0; }

.stock-bar__fill {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  background: #5B8DEF;
  z-index: 2;
  transition: width 0.4s ease;
}

.stock-bar__marker {
  position: absolute;
  top: -1px;
  width: 2px;
  height: calc(100% + 2px);
  background: var(--hairline-strong);
  z-index: 3;
}

.stock-bar__marker--rop {
  background: var(--signal-red);
}

.material-row__figures {
  display: flex;
  gap: 1.1rem;
  flex-wrap: wrap;
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

.btn {
  border-radius: 4px;
  border: 1px solid var(--hairline-border);
  cursor: pointer;
  font-family: var(--font-display);
  font-weight: 600;
  letter-spacing: 0.02em;
}

.btn--small {
  padding: 0.3rem 0.65rem;
  font-size: 0.6875rem;
}

.btn--ghost {
  background: transparent;
  color: var(--data-ink-muted);
}

.btn--ghost:hover:not(:disabled) { background: var(--panel-graphite-raised); color: var(--data-ink); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
