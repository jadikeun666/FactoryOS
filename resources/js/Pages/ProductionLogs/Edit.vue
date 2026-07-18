<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 2 — OEE &amp; Downtime</p>
        <h1 class="page-title">Edit Log #{{ productionLog.id }}</h1>
        <p class="page-subtitle">
          {{ productionLog.work_center?.name ?? '–' }} · {{ productionLog.shift?.name ?? '–' }}
        </p>
      </div>
      <Link :href="showUrl" class="btn btn--ghost">← Kembali ke Detail</Link>
    </header>

    <form class="form-card" @submit.prevent="submit">
      <div class="form-grid">
        <label class="field">
          <span class="field-label">Tanggal Log *</span>
          <input v-model="form.log_date" type="date" class="input" :class="{ 'input--error': form.errors.log_date }" />
          <span v-if="form.errors.log_date" class="field-error">{{ form.errors.log_date }}</span>
        </label>

        <label class="field">
          <span class="field-label">Shift *</span>
          <select v-model="form.shift_id" class="input" :class="{ 'input--error': form.errors.shift_id }">
            <option v-for="s in shifts" :key="s.id" :value="s.id">
              {{ s.name }} ({{ s.start_time }}–{{ s.end_time }})
            </option>
          </select>
          <span v-if="form.errors.shift_id" class="field-error">{{ form.errors.shift_id }}</span>
        </label>

        <label class="field">
          <span class="field-label">Planned Minutes *</span>
          <input v-model.number="form.planned_minutes" type="number" step="0.01" min="0.01" class="input" :class="{ 'input--error': form.errors.planned_minutes }" />
          <span v-if="form.errors.planned_minutes" class="field-error">{{ form.errors.planned_minutes }}</span>
        </label>

        <label class="field">
          <span class="field-label">Downtime Minutes</span>
          <input v-model.number="form.downtime_minutes" type="number" step="0.01" min="0" class="input" :class="{ 'input--error': form.errors.downtime_minutes }" />
          <span v-if="form.errors.downtime_minutes" class="field-error">{{ form.errors.downtime_minutes }}</span>
        </label>

        <label class="field">
          <span class="field-label">Actual Output *</span>
          <input v-model.number="form.actual_output" type="number" step="0.0001" min="0.0001" class="input" :class="{ 'input--error': form.errors.actual_output }" />
          <span v-if="form.errors.actual_output" class="field-error">{{ form.errors.actual_output }}</span>
        </label>

        <label class="field">
          <span class="field-label">Good Output *</span>
          <input v-model.number="form.good_output" type="number" step="0.0001" min="0" class="input" :class="{ 'input--error': form.errors.good_output }" />
          <span v-if="form.errors.good_output" class="field-error">{{ form.errors.good_output }}</span>
        </label>

        <label class="field">
          <span class="field-label">Ideal Cycle Time (menit/unit) *</span>
          <input v-model.number="form.ideal_cycle_time_minutes" type="number" step="0.000001" min="0.000001" class="input" :class="{ 'input--error': form.errors.ideal_cycle_time_minutes }" />
          <span v-if="form.errors.ideal_cycle_time_minutes" class="field-error">{{ form.errors.ideal_cycle_time_minutes }}</span>
        </label>
      </div>

      <p class="hint-text">
        Downtime events dikelola terpisah di halaman detail log, bukan di form ini.
      </p>

      <footer class="form-footer">
        <Link :href="showUrl" class="btn btn--ghost">Batal</Link>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
        </button>
      </footer>
    </form>
  </div>
</template>

<script setup>
/**
 * ASUMSI: ProductionLogController@edit merender:
 *   productionLog: model (belum tentu ter-load downtimeEvents secara lengkap
 *                  di controller saat ini — form ini hanya butuh field utama)
 *
 * NOTE: controller saat ini tidak mengirim daftar shifts ke halaman edit;
 * untuk sementara dropdown shift memakai productionLog.shift sebagai satu-satunya
 * opsi kalau prop shifts kosong, supaya value tetap valid saat render pertama.
 * Kalau Anda ingin dropdown shift lengkap di Edit, controller perlu ditambah
 * mengirim `shifts` sama seperti di create() — beri tahu saya kalau mau,
 * saya siapkan diff-nya (tidak saya ubah sendiri tanpa konfirmasi).
 */
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  productionLog: { type: Object, required: true },
  shifts: { type: Array, default: () => [] },
})

const showUrl = computed(() => `/production-logs/${props.productionLog.id}`)

const shifts = computed(() => {
  if (props.shifts.length > 0) return props.shifts
  return props.productionLog.shift ? [props.productionLog.shift] : []
})

const form = useForm({
  log_date: props.productionLog.log_date?.slice(0, 10) ?? '',
  shift_id: props.productionLog.shift_id ?? props.productionLog.shift?.id ?? '',
  planned_minutes: props.productionLog.planned_minutes,
  downtime_minutes: props.productionLog.downtime_minutes,
  actual_output: props.productionLog.actual_output,
  good_output: props.productionLog.good_output,
  ideal_cycle_time_minutes: props.productionLog.ideal_cycle_time_minutes,
})

function submit() {
  form.patch(`/production-logs/${props.productionLog.id}`)
}
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 900px;
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
  font-size: 1.375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: #64748B;
  margin: 0.35rem 0 0;
}

.form-card {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.field-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
}

.input {
  padding: 0.5rem 0.65rem;
  font-size: 0.8125rem;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  color: #0F172A;
  background: #FFFFFF;
}

.input:focus {
  outline: 2px solid #F59E0B;
  outline-offset: 1px;
}

.input--error { border-color: #EF4444; }

.field-error {
  font-size: 0.6875rem;
  color: #DC2626;
}

.hint-text {
  font-size: 0.75rem;
  color: #94A3B8;
  margin: 0;
}

.form-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  border-top: 1px solid #F1F5F9;
  padding-top: 1.25rem;
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
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn--primary { background: #0F172A; color: #F8FAFC; }
.btn--primary:hover:not(:disabled) { background: #1E293B; }

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
}
.btn--ghost:hover { background: #F1F5F9; }
</style>