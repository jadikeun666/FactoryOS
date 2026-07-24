<template>
  <div class="dashboard-page">
    <header class="dashboard-page__header">
      <h1>Dashboard KPI</h1>
      <p class="dashboard-page__subtitle">Ringkasan lintas Scheduling, OEE, dan Inventory</p>
    </header>

    <!-- ENGINE 1 — Job Shop Scheduler -->
    <section class="dashboard-section">
      <h2 class="dashboard-section__title">Engine 1 — Penjadwalan</h2>
      <div class="dashboard-grid">
        <KpiCard
          label="WO Aktif"
          :value="engine1.wo_active_count"
          hint="draft, scheduled, in_progress"
          :delay="0"
        />
        <KpiCard
          label="WO Terlambat"
          :value="engine1.wo_late_count"
          :tone="engine1.wo_late_count > 0 ? 'danger' : 'success'"
          hint="due date terlewat, belum selesai"
          :delay="80"
        />
        <KpiCard
          v-if="engine1.active_schedule"
          label="Makespan (Jadwal Terbaru)"
          :value="Number(engine1.active_schedule.makespan_minutes)"
          suffix=" mnt"
          :hint="`Algoritma ${engine1.active_schedule.algorithm.toUpperCase()}`"
          :delay="160"
        />
        <div v-else class="dashboard-empty-card">
          Belum ada schedule yang dijalankan.
        </div>
      </div>
    </section>

    <!-- ENGINE 2 — OEE & Downtime -->
    <section class="dashboard-section">
      <h2 class="dashboard-section__title">Engine 2 — OEE</h2>
      <div class="dashboard-grid">
        <KpiCard
          v-if="engine2.avg_oee_today !== null"
          label="Rata-rata OEE Hari Ini"
          :value="Number(engine2.avg_oee_today) * 100"
          suffix="%"
          :decimals="1"
          :tone="oeeTone(engine2.avg_oee_today)"
          :delay="0"
        />
        <div v-else class="dashboard-empty-card">
          Belum ada log produksi hari ini.
        </div>

        <div v-if="engine2.lowest_oee_work_center" class="dashboard-info-card">
          <span class="dashboard-info-card__label">Mesin OEE Terendah Hari Ini</span>
          <span class="dashboard-info-card__value">{{ engine2.lowest_oee_work_center.name }}</span>
          <span class="dashboard-info-card__sub">
            {{ (Number(engine2.lowest_oee_work_center.oee) * 100).toFixed(1) }}%
          </span>
        </div>
      </div>
    </section>

    <!-- ENGINE 3 — Inventory Optimizer -->
    <section class="dashboard-section">
      <h2 class="dashboard-section__title">Engine 3 — Inventory</h2>
      <div class="dashboard-grid">
        <KpiCard
          label="Reorder Alert Terbuka"
          :value="engine3.open_alert_count"
          :tone="engine3.open_alert_count > 0 ? 'warn' : 'success'"
          hint="status: open"
          :delay="0"
        />
        <KpiCard
          label="Material Stok Kritis"
          :value="engine3.critical_stock_count"
          :tone="engine3.critical_stock_count > 0 ? 'danger' : 'success'"
          hint="qty on-hand + on-order ≤ ROP"
          :delay="80"
        />
      </div>
    </section>
  </div>
</template>

<script setup>
import KpiCard from '@/Components/KpiCard.vue'

defineProps({
  engine1: { type: Object, required: true },
  engine2: { type: Object, required: true },
  engine3: { type: Object, required: true },
})

function oeeTone(oee) {
  const val = Number(oee)
  if (val >= 0.85) return 'success'
  if (val >= 0.6) return 'warn'
  return 'danger'
}
</script>

<style scoped>
.dashboard-page {
  padding: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
}

.dashboard-page__header h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.dashboard-page__subtitle {
  color: #64748B;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.dashboard-section {
  margin-top: 2rem;
}

.dashboard-section__title {
  font-size: 1rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 0.75rem;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.75rem;
}

.dashboard-empty-card,
.dashboard-info-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 1rem;
  background: #F8FAFC;
  border: 1px dashed #CBD5E1;
  border-radius: 10px;
  color: #64748B;
  font-size: 0.8125rem;
}

.dashboard-info-card {
  border-style: solid;
  background: #FFFFFF;
  border-color: #E5E7EB;
}

.dashboard-info-card__label {
  font-size: 0.75rem;
  color: #64748B;
}

.dashboard-info-card__value {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0F172A;
}

.dashboard-info-card__sub {
  font-size: 0.75rem;
  color: #94A3B8;
}
</style>