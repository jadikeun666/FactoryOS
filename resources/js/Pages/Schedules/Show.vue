<template>
  <div class="show-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 1 — Job Shop Scheduler</p>
        <h1 class="page-title">
          Jadwal #{{ scheduleId }}
          <span class="algo-badge">{{ algorithm.toUpperCase() }}</span>
        </h1>
        <p class="page-subtitle">Dibuat {{ formattedCreatedAt }}</p>
      </div>
      <Link :href="compareUrl" class="btn btn--ghost">↺ Bandingkan Ulang</Link>
    </header>

    <div class="gantt-shell">
      <GanttChart
        :initial-data="initialData"
        :schedule-ids="scheduleIds"
        :gantt-data-url="ganttDataUrl"
      />
    </div>
  </div>
</template>

<script setup>
/**
 * ASUMSI: controller merender halaman ini via Inertia dengan props:
 *   initialData:  hasil GanttBuilderService::build($schedule) (format docs/gantt.md)
 *   scheduleIds:  { spt: 1, edd: 2, cr: 3, fifo: 4 } — dipakai GanttChart
 *                 untuk toggle antar algoritma tanpa reload halaman
 *   compareUrl:   url kembali ke halaman Compare.vue (opsional)
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import GanttChart from '@/Components/GanttChart.vue'

const props = defineProps({
  initialData: { type: Object, required: true },
  scheduleIds: { type: Object, required: true },
  compareUrl: { type: String, default: '/schedules/compare' },
})

const scheduleId = computed(() => props.initialData?.schedule?.id)
const algorithm = computed(() => props.initialData?.schedule?.algorithm ?? '–')

const formattedCreatedAt = computed(() => {
  const raw = props.initialData?.schedule?.scheduled_from
  if (!raw) return '–'
  return new Date(raw).toLocaleString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
})

function ganttDataUrl(id) {
  return `/api/schedules/${id}/gantt-data`
}
</script>

<style scoped>
.show-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
  animation: page-fade 0.35s ease both;
}

@keyframes page-fade {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
  .show-page { animation: none; }
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
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 1.5rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.algo-badge {
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #0F172A;
  color: #F8FAFC;
  vertical-align: middle;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: #64748B;
  margin: 0.35rem 0 0;
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
  text-decoration: none;
  transition: background-color 0.15s ease, transform 0.12s ease;
}

.btn:active { transform: translateY(1px); }

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
}

.btn--ghost:hover { background: #F8FAFC; }

.gantt-shell {
  animation: page-fade 0.45s ease both;
  animation-delay: 80ms;
}

@media (prefers-reduced-motion: reduce) {
  .gantt-shell { animation: none; }
}
</style>