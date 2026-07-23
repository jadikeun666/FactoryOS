<template>
  <div class="mrp-dashboard">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 3 — Inventory Optimizer</p>
        <h1 class="page-title">Dashboard MRP</h1>
        <p class="page-subtitle">
          Reorder alert, status stok vs ROP, dan grid kebutuhan material
          per periode.
        </p>
      </div>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="isRunningMrp || !latestScheduleId"
        @click="runMrpAgain"
      >
        {{ isRunningMrp ? 'Menjalankan…' : '↺ Jalankan MRP Ulang' }}
      </button>
    </header>

    <AlertBanner :initial-alerts="initialAlerts" alerts-url="/mrp/alerts" />

    <RopGauge :initial-materials="[]" status-url="/inventory/status" ref="ropGaugeRef" />

    <MrpGrid
      :initial-mrp-run="initialMrpRun"
      :mrp-run-url="(id) => `/mrp/runs/${id}`"
    />
  </div>
</template>

<script setup>
/**
 * Mrp/Dashboard.vue — halaman gabungan Engine 3 (US-13, US-15 sebagian,
 * FR-06, FR-07, FR-08). Standalone, tanpa layout wrapper -- konsisten
 * dengan Schedules/Show.vue & Compare.vue yang juga tidak pakai layout
 * bersama (belum ada folder resources/js/Layouts/ di project ini).
 *
 * ASUMSI props (dari MrpController::dashboard()):
 *   initialAlerts: ReorderAlert[] (status='open' saja, AlertBanner fetch
 *                  ulang sendiri saat user ganti tab status)
 *   initialMrpRun: MrpRun|null (run terbaru, MrpGrid fetch ulang sendiri
 *                  via refresh button)
 *
 * RopGauge SENGAJA diberi initial-materials=[] (bukan prop dari server)
 * -- ia fetch sendiri saat mounted via GET /inventory/status. Ini beda
 * pola dari GanttChart/ParetoChart yang di-hydrate dari server-side props
 * pertama; keputusan ini diambil karena endpoint /inventory/status murni
 * read tanpa auth kompleks/params, jadi tidak ada keuntungan SSR di sini.
 * UTANG TEKNIS KECIL: RopGauge tidak auto-fetch saat mount kalau
 * initial-materials kosong -- lihat catatan di RopGauge.vue, tombol
 * "Refresh" perlu diklik manual sekali di kunjungan pertama. Perbaikan
 * (auto-fetch on mount jika initialMaterials kosong) bisa ditambahkan
 * di checkpoint berikutnya jika diinginkan.
 *
 * "Jalankan MRP Ulang" POST ke /mrp/run dengan schedule_id dari
 * latestMrpRun.schedule_id (kalau ada) -- tombol nonaktif jika belum ada
 * schedule sama sekali.
 */
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AlertBanner from '@/Components/AlertBanner.vue'
import RopGauge from '@/Components/RopGauge.vue'
import MrpGrid from '@/Components/MrpGrid.vue'

const props = defineProps({
  initialAlerts: { type: Array, default: () => [] },
  initialMrpRun: { type: Object, default: null },
})

const isRunningMrp = ref(false)
const ropGaugeRef = ref(null)

const latestScheduleId = computed(() => props.initialMrpRun?.schedule_id ?? props.initialMrpRun?.schedule?.id ?? null)

async function runMrpAgain() {
  if (!latestScheduleId.value) return
  isRunningMrp.value = true
  try {
    const response = await fetch('/mrp/run', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ schedule_id: latestScheduleId.value }),
    })
    if (!response.ok) throw new Error(`Gagal menjalankan MRP (${response.status})`)
    // Reload props halaman dari server (bukan navigasi Inertia dari respons
    // fetch tadi -- fetch tsb JSON murni, bukan Inertia response).
    router.reload({ only: ['initialAlerts', 'initialMrpRun'] })
  } catch (error) {
    console.error('Dashboard MRP: gagal jalankan MRP ulang', error)
  } finally {
    isRunningMrp.value = false
  }
}
</script>

<style scoped>
.mrp-dashboard {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 1200px;
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
  font-size: 0.8125rem;
  color: #64748B;
  margin: 0.35rem 0 0;
  max-width: 50ch;
}

.btn {
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  flex-shrink: 0;
}

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
</style>