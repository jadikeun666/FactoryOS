<template>
  <div class="rop-gauge">
    <div class="rop-gauge__header">
      <span class="rop-gauge__title">Status Stok vs ROP</span>
      <button type="button" class="btn btn--ghost btn--small" :disabled="isLoading" @click="refresh">
        {{ isLoading ? 'Memuat…' : '↻ Refresh' }}
      </button>
    </div>

    <div v-if="materials.length === 0" class="rop-gauge__empty">
      Belum ada material dengan parameter inventory (EOQ/Safety Stock/ROP).
    </div>

    <div v-else class="rop-gauge__grid">
      <div
        v-for="material in sortedMaterials"
        :key="material.material_id"
        class="material-card"
        :class="`material-card--${severity(material)}`"
      >
        <div class="material-card__head">
          <span class="material-card__name">{{ material.name }}</span>
          <span class="severity-tag" :class="`severity-tag--${severity(material)}`">
            {{ severityLabel(severity(material)) }}
          </span>
        </div>

        <div class="stock-bar">
          <div class="stock-bar__track">
            <!-- Zona aman: dari 0 sampai ROP (merah/kuning), dari ROP ke atas (hijau) -->
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

        <dl class="material-card__figures">
          <div><dt>Qty On Hand</dt><dd>{{ formatNumber(material.qty_on_hand) }} {{ material.unit }}</dd></div>
          <div><dt>Safety Stock</dt><dd>{{ formatNumber(material.safety_stock) }}</dd></div>
          <div><dt>ROP</dt><dd>{{ formatNumber(material.rop) }}</dd></div>
          <div><dt>EOQ</dt><dd>{{ formatNumber(material.eoq) }}</dd></div>
        </dl>
      </div>
    </div>
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
.rop-gauge {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.rop-gauge__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.rop-gauge__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #0F172A;
}

.rop-gauge__empty {
  padding: 2rem 1rem;
  text-align: center;
  color: #94A3B8;
  font-size: 0.8125rem;
}

.rop-gauge__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0.85rem;
}

.material-card {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: 0.85rem;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
}

.material-card--critical { border-color: #FCA5A5; background: #FEF2F2; }
.material-card--warning { border-color: #FCD34D; background: #FFFBEB; }
.material-card--safe { border-color: #E5E7EB; background: #FFFFFF; }

.material-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.material-card__name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #0F172A;
}

.severity-tag {
  font-size: 0.625rem;
  font-weight: 700;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
  flex-shrink: 0;
}

.severity-tag--critical { background: #FEE2E2; color: #B91C1C; }
.severity-tag--warning { background: #FEF3C7; color: #92400E; }
.severity-tag--safe { background: #DCFCE7; color: #15803D; }

.stock-bar__track {
  position: relative;
  height: 0.6rem;
  border-radius: 999px;
  background: #F1F5F9;
  overflow: hidden;
}

.stock-bar__zone {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
}

.stock-bar__zone--danger { background: #FEE2E2; z-index: 1; }
.stock-bar__zone--warn { background: #FEF3C7; z-index: 0; }

.stock-bar__fill {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  background: #2563EB;
  border-radius: 999px;
  z-index: 2;
  transition: width 0.4s ease;
}

.stock-bar__marker {
  position: absolute;
  top: -2px;
  width: 2px;
  height: calc(100% + 4px);
  background: #94A3B8;
  z-index: 3;
}

.stock-bar__marker--rop {
  background: #DC2626;
}

.material-card__figures {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.35rem 0.75rem;
  margin: 0;
  font-size: 0.6875rem;
}

.material-card__figures dt {
  color: #94A3B8;
}

.material-card__figures dd {
  margin: 0;
  font-weight: 600;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.btn {
  border-radius: 6px;
  border: 1px solid #E2E8F0;
  cursor: pointer;
  font-weight: 600;
}

.btn--small {
  padding: 0.3rem 0.65rem;
  font-size: 0.6875rem;
}

.btn--ghost {
  background: #FFFFFF;
  color: #334155;
}

.btn--ghost:hover:not(:disabled) { background: #F8FAFC; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>