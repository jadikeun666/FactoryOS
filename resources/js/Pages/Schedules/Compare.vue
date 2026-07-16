<template>
  <div class="compare-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 1 — Job Shop Scheduler</p>
        <h1 class="page-title">Perbandingan Algoritma Penjadwalan</h1>
        <p class="page-subtitle">
          Keempat algoritma dijalankan terhadap Work Order yang sama.
          Pilih salah satu untuk diterapkan ke jadwal produksi.
        </p>
      </div>
      <Link :href="indexUrl" class="btn btn--ghost">← Kembali</Link>
    </header>

    <section class="algo-grid">
      <button
        v-for="(result, index) in results"
        :key="result.algorithm"
        type="button"
        class="algo-card"
        :class="{
          'algo-card--selected': selectedAlgorithm === result.algorithm,
          'algo-card--winner': result.algorithm === recommendedAlgorithm,
        }"
        :style="{ '--delay': `${index * 90}ms` }"
        @click="selectAlgorithm(result.algorithm)"
      >
        <span v-if="result.algorithm === recommendedAlgorithm" class="algo-card__ribbon">
          Rekomendasi
        </span>

        <div class="algo-card__head">
          <span class="algo-card__name">{{ result.algorithm.toUpperCase() }}</span>
          <span class="algo-card__radio" :class="{ 'algo-card__radio--checked': selectedAlgorithm === result.algorithm }" />
        </div>
        <p class="algo-card__desc">{{ algorithmDescriptions[result.algorithm] }}</p>

        <dl class="algo-card__metrics">
          <div class="metric-row" v-for="metric in metricDefs" :key="metric.key">
            <dt>{{ metric.label }}</dt>
            <dd>
              <span class="metric-row__value">{{ formatMetric(result[metric.key], metric) }}</span>
              <span class="metric-row__bar-track">
                <span
                  class="metric-row__bar-fill"
                  :class="{ 'metric-row__bar-fill--best': isBest(result, metric) }"
                  :style="{ width: `${barWidth(result, metric)}%`, '--bar-delay': `${index * 90 + 150}ms` }"
                />
              </span>
            </dd>
          </div>
        </dl>
      </button>
    </section>

    <section class="detail-table-section">
      <h2 class="section-title">Detail Metrik</h2>
      <div class="detail-table-wrapper">
        <table class="detail-table">
          <thead>
            <tr>
              <th scope="col">Metrik</th>
              <th
                v-for="result in results"
                :key="result.algorithm"
                scope="col"
                :class="{ 'detail-table__col--selected': selectedAlgorithm === result.algorithm }"
              >
                {{ result.algorithm.toUpperCase() }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="metric in metricDefs" :key="metric.key">
              <th scope="row">{{ metric.label }}</th>
              <td
                v-for="result in results"
                :key="result.algorithm"
                :class="{
                  'detail-table__cell--best': isBest(result, metric),
                  'detail-table__col--selected': selectedAlgorithm === result.algorithm,
                }"
              >
                {{ formatMetric(result[metric.key], metric) }}
                <span v-if="isBest(result, metric)" class="detail-table__best-tag">terbaik</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <footer class="apply-bar" :class="{ 'apply-bar--visible': selectedAlgorithm !== null }">
      <p v-if="selectedAlgorithm">
        Jadwal <strong>{{ selectedAlgorithm.toUpperCase() }}</strong> akan diterapkan ke
        <code>wo_operations</code>.
      </p>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="!selectedAlgorithm || isApplying"
        @click="applySchedule"
      >
        {{ isApplying ? 'Menerapkan…' : 'Terapkan Jadwal' }}
      </button>
    </footer>
  </div>
</template>

<script setup>
/**
 * ASUMSI (belum ada spesifikasi eksplisit di docs/ untuk halaman ini):
 * 1. Controller merender halaman ini via Inertia dengan props:
 *      results:  array hasil JobShopSchedulerService::compareAll(), satu
 *                object per algoritma — { algorithm, schedule_id,
 *                makespan_minutes, total_tardiness_minutes,
 *                late_wo_count, mean_flow_time_minutes }
 *      indexUrl: url kembali ke daftar Work Order / jadwal (opsional)
 * 2. "Terapkan Jadwal" POST ke route bernama `schedules.apply` dengan
 *    payload { schedule_id }. Endpoint ini BELUM diimplementasikan di
 *    backend — scheduling.md menyebut alur "user pilih algoritma terbaik,
 *    lalu apply ke wo_operations" tapi tidak merinci kontrak endpoint-nya.
 *    Tombol ini sengaja dibuat agar UI siap begitu endpoint tsb dibuat;
 *    saat ini akan gagal dengan 404 jika route belum didaftarkan.
 * 3. Algoritma "rekomendasi" dipilih otomatis: total_tardiness_minutes
 *    terkecil, tie-break dengan makespan_minutes terkecil. Ini heuristik
 *    tampilan saja, bukan logic bisnis — keputusan akhir tetap di tangan
 *    user (docs/scheduling.md: "User memilih algoritma terbaik").
 */
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  results: { type: Array, required: true },
  indexUrl: { type: String, default: '/work-orders' },
})

const selectedAlgorithm = ref(null)
const isApplying = ref(false)

const algorithmDescriptions = {
  spt: 'Prioritaskan operasi dengan waktu proses tersingkat.',
  edd: 'Prioritaskan Work Order dengan due date paling awal.',
  cr:  'Seimbangkan due date & sisa beban kerja (Critical Ratio).',
  fifo: 'Urutkan berdasarkan waktu Work Order dibuat (baseline).',
}

const metricDefs = [
  { key: 'makespan_minutes', label: 'Makespan', unit: 'menit', lowerIsBetter: true },
  { key: 'total_tardiness_minutes', label: 'Total Tardiness', unit: 'menit', lowerIsBetter: true },
  { key: 'late_wo_count', label: 'WO Terlambat', unit: '', lowerIsBetter: true },
  { key: 'mean_flow_time_minutes', label: 'Mean Flow Time', unit: 'menit', lowerIsBetter: true },
]

function formatMetric(value, metric) {
  if (value === null || value === undefined) return '–'
  const rounded = metric.unit === '' ? value : Math.round(value)
  return metric.unit ? `${rounded} ${metric.unit}` : `${rounded}`
}

function maxForMetric(key) {
  return Math.max(...props.results.map((r) => Number(r[key]) || 0), 1)
}

function barWidth(result, metric) {
  const max = maxForMetric(metric.key)
  const value = Number(result[metric.key]) || 0
  return max === 0 ? 0 : Math.max((value / max) * 100, value === 0 ? 4 : 6)
}

function isBest(result, metric) {
  const values = props.results.map((r) => Number(r[metric.key]) || 0)
  const best = metric.lowerIsBetter ? Math.min(...values) : Math.max(...values)
  return Number(result[metric.key]) === best
}

const recommendedAlgorithm = computed(() => {
  if (!props.results.length) return null
  const sorted = [...props.results].sort((a, b) => {
    const tardinessDiff = (a.total_tardiness_minutes ?? 0) - (b.total_tardiness_minutes ?? 0)
    if (tardinessDiff !== 0) return tardinessDiff
    return (a.makespan_minutes ?? 0) - (b.makespan_minutes ?? 0)
  })
  return sorted[0]?.algorithm ?? null
})

function selectAlgorithm(algorithm) {
  selectedAlgorithm.value = selectedAlgorithm.value === algorithm ? null : algorithm
}

function applySchedule() {
  const target = props.results.find((r) => r.algorithm === selectedAlgorithm.value)
  if (!target) return

  isApplying.value = true
  router.post('/schedules/apply', { schedule_id: target.schedule_id }, {
    onFinish: () => { isApplying.value = false },
  })
}
</script>

<style scoped>
.compare-page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
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
  font-size: 0.875rem;
  color: #64748B;
  margin: 0.35rem 0 0;
  max-width: 46ch;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: transform 0.12s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}

.btn:active { transform: translateY(1px); }

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
  text-decoration: none;
}

.btn--ghost:hover { background: #F8FAFC; }

.btn--primary {
  background: #0F172A;
  color: #F8FAFC;
}

.btn--primary:hover:not(:disabled) {
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.25);
}

.btn--primary:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Grid Kartu Algoritma */
.algo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 1rem;
}

.algo-card {
  position: relative;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1.1rem;
  background: #FFFFFF;
  border: 1.5px solid #E5E7EB;
  border-radius: 12px;
  cursor: pointer;
  font: inherit;
  animation: card-rise 0.45s ease both;
  animation-delay: var(--delay, 0ms);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

@keyframes card-rise {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
  .algo-card, .kpi-card { animation: none; }
}

.algo-card:hover {
  border-color: #CBD5E1;
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.algo-card--selected {
  border-color: #F59E0B;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}

.algo-card--winner {
  border-color: #16A34A;
}

.algo-card__ribbon {
  position: absolute;
  top: -0.6rem;
  right: 0.9rem;
  background: #16A34A;
  color: #F0FDF4;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  animation: ribbon-pulse 2.4s ease-in-out infinite;
}

@keyframes ribbon-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.35); }
  50%      { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
}

@media (prefers-reduced-motion: reduce) {
  .algo-card__ribbon { animation: none; }
}

.algo-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.algo-card__name {
  font-size: 1rem;
  font-weight: 700;
  color: #0F172A;
  letter-spacing: 0.02em;
}

.algo-card__radio {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 999px;
  border: 2px solid #CBD5E1;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.algo-card__radio--checked {
  border-color: #F59E0B;
  background: radial-gradient(circle, #F59E0B 0 40%, transparent 42%);
}

.algo-card__desc {
  font-size: 0.75rem;
  color: #64748B;
  margin: 0;
  min-height: 2.1em;
}

.algo-card__metrics {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0;
}

.metric-row dt {
  font-size: 0.6875rem;
  color: #94A3B8;
  margin-bottom: 0.2rem;
}

.metric-row dd {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.metric-row__value {
  font-size: 0.75rem;
  font-weight: 600;
  color: #334155;
  width: 5.5rem;
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}

.metric-row__bar-track {
  flex: 1;
  height: 6px;
  background: #F1F5F9;
  border-radius: 999px;
  overflow: hidden;
}

.metric-row__bar-fill {
  display: block;
  height: 100%;
  background: #94A3B8;
  border-radius: 999px;
  width: 0;
  animation: bar-fill 0.6s ease both;
  animation-delay: var(--bar-delay, 0ms);
}

@keyframes bar-fill {
  from { width: 0 !important; }
}

@media (prefers-reduced-motion: reduce) {
  .metric-row__bar-fill { animation: none; }
}

.metric-row__bar-fill--best {
  background: #16A34A;
}

/* Tabel Detail */
.section-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0 0 0.6rem;
}

.detail-table-wrapper {
  overflow-x: auto;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.detail-table th,
.detail-table td {
  padding: 0.6rem 0.9rem;
  text-align: left;
  border-bottom: 1px solid #F1F5F9;
  white-space: nowrap;
}

.detail-table thead th {
  color: #64748B;
  font-weight: 600;
  background: #F8FAFC;
}

.detail-table tbody th {
  color: #334155;
  font-weight: 500;
}

.detail-table__col--selected {
  background: rgba(245, 158, 11, 0.06);
}

.detail-table__cell--best {
  color: #15803D;
  font-weight: 700;
}

.detail-table__best-tag {
  margin-left: 0.35rem;
  font-size: 0.625rem;
  font-weight: 600;
  color: #16A34A;
}

/* Apply bar */
.apply-bar {
  position: sticky;
  bottom: 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1.1rem;
  background: #0F172A;
  color: #E2E8F0;
  border-radius: 12px;
  font-size: 0.8125rem;
  opacity: 0;
  transform: translateY(8px);
  pointer-events: none;
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.apply-bar--visible {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
}

.apply-bar code {
  background: rgba(255, 255, 255, 0.1);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}
</style>