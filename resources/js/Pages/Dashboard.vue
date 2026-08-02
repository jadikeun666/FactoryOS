<template>
  <div class="dashboard-page">
    <header class="dashboard-page__header">
      <span class="dashboard-page__eyebrow">FactoryOS — Ruang Kontrol</span>
      <h1>Dashboard KPI</h1>
      <p class="dashboard-page__subtitle">Ringkasan lintas Scheduling, OEE, dan Inventory</p>
    </header>

    <section class="dashboard-section">
      <h2 class="dashboard-section__title">
        <span class="dashboard-section__index">01</span>Engine 1 — Penjadwalan
      </h2>
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

    <section class="dashboard-section">
      <h2 class="dashboard-section__title">
        <span class="dashboard-section__index">02</span>Engine 2 — OEE
      </h2>
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

    <section class="dashboard-section">
      <h2 class="dashboard-section__title">
        <span class="dashboard-section__index">03</span>Engine 3 — Inventory
      </h2>
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
  padding: 2rem 1.5rem 3rem;
  max-width: 1100px;
  margin: 0 auto;
  min-height: 100vh;
  background:
    radial-gradient(1200px 500px at 20% -10%, rgba(74, 155, 110, 0.06), transparent 60%),
    var(--surface-steel);
  transition: background-color 0.25s ease;
}

.dashboard-page__header {
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--hairline-strong);
  margin-bottom: 0.5rem;
}

.dashboard-page__eyebrow {
  display: block;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--signal-green);
  margin-bottom: 0.4rem;
}

.dashboard-page__header h1 {
  font-family: var(--font-body);
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--data-ink);
  margin: 0;
  letter-spacing: -0.01em;
}

.dashboard-page__subtitle {
  font-family: var(--font-body);
  color: var(--data-ink-muted);
  font-size: 0.875rem;
  margin-top: 0.35rem;
}

.dashboard-section {
  margin-top: 2.25rem;
}

.dashboard-section__title {
  display: flex;
  align-items: baseline;
  gap: 0.6rem;
  font-family: var(--font-body);
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--data-ink);
  margin-bottom: 0.85rem;
}

.dashboard-section__index {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--data-ink-muted);
  letter-spacing: 0.05em;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.85rem;
}

.dashboard-empty-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.25rem;
  padding: 0.85rem 1.15rem;
  background: repeating-linear-gradient(
    135deg,
    var(--hairline-soft),
    var(--hairline-soft) 8px,
    var(--hairline) 8px,
    var(--hairline) 16px
  );
  border: 1px dashed var(--hairline-strong);
  border-radius: 4px;
  color: var(--data-ink-muted);
  font-family: var(--font-body);
  font-size: 0.8125rem;
}

.dashboard-info-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 1rem 1.15rem;
  background: linear-gradient(180deg, var(--card-bg-start) 0%, var(--card-bg-end) 100%);
  border: 1px solid var(--hairline);
  border-left: 3px solid var(--signal-amber);
  border-radius: 4px;
}

.dashboard-info-card__label {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--data-ink-muted);
}

.dashboard-info-card__value {
  font-family: var(--font-body);
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--data-ink-inverse);
}

.dashboard-info-card__sub {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--signal-amber);
}
</style>