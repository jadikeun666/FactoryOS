<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Engine 2 — OEE &amp; Downtime</p>
        <h1 class="page-title">Log Produksi Baru</h1>
        <p class="page-subtitle">Isi log per shift dalam satu form (US-06 &amp; US-07).</p>
      </div>
      <Link href="/production-logs" class="btn btn--ghost">← Kembali</Link>
    </header>

    <form class="form-card" @submit.prevent="submit">
      <div class="form-grid">
        <label class="field">
          <span class="field-label">Mesin *</span>
          <select v-model="form.work_center_id" class="input" :class="{ 'input--error': form.errors.work_center_id }">
            <option value="" disabled>Pilih mesin</option>
            <option v-for="wc in workCenters" :key="wc.id" :value="wc.id">
              {{ wc.name }} ({{ wc.code }})
            </option>
          </select>
          <span v-if="form.errors.work_center_id" class="field-error">{{ form.errors.work_center_id }}</span>
        </label>

        <label class="field">
          <span class="field-label">Shift *</span>
          <select v-model="form.shift_id" class="input" :class="{ 'input--error': form.errors.shift_id }">
            <option value="" disabled>Pilih shift</option>
            <option v-for="s in shifts" :key="s.id" :value="s.id">
              {{ s.name }} ({{ s.start_time }}–{{ s.end_time }})
            </option>
          </select>
          <span v-if="form.errors.shift_id" class="field-error">{{ form.errors.shift_id }}</span>
        </label>

        <label class="field">
          <span class="field-label">Tanggal Log *</span>
          <input v-model="form.log_date" type="date" class="input" :class="{ 'input--error': form.errors.log_date }" />
          <span v-if="form.errors.log_date" class="field-error">{{ form.errors.log_date }}</span>
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

      <section class="downtime-section">
        <div class="downtime-header">
          <h2 class="section-title">Downtime Events</h2>
          <button type="button" class="btn btn--ghost btn--sm" @click="addDowntimeEvent">+ Tambah Downtime</button>
        </div>

        <p v-if="form.downtime_events.length === 0" class="downtime-empty">
          Belum ada downtime dicatat untuk shift ini.
        </p>

        <div v-for="(event, idx) in form.downtime_events" :key="idx" class="downtime-row">
          <label class="field field--compact">
            <span class="field-label">Kategori *</span>
            <select v-model="event.reason_category" class="input">
              <option value="" disabled>Pilih kategori</option>
              <option value="breakdown">Breakdown</option>
              <option value="setup">Setup</option>
              <option value="material">Material</option>
              <option value="operator">Operator</option>
              <option value="other">Lainnya</option>
            </select>
          </label>

          <label class="field field--compact">
            <span class="field-label">Durasi (menit) *</span>
            <input v-model.number="event.duration_minutes" type="number" step="0.01" min="0.01" class="input" />
          </label>

          <label class="field field--compact">
            <span class="field-label">Mulai *</span>
            <input v-model="event.started_at" type="datetime-local" class="input" />
          </label>

          <label class="field field--grow">
            <span class="field-label">Keterangan</span>
            <input v-model="event.reason_detail" type="text" maxlength="255" class="input" placeholder="opsional" />
          </label>

          <button type="button" class="icon-btn" title="Hapus baris ini" @click="removeDowntimeEvent(idx)">✕</button>
        </div>

        <p v-if="form.errors['downtime_events']" class="field-error">{{ form.errors['downtime_events'] }}</p>
      </section>

      <footer class="form-footer">
        <Link href="/production-logs" class="btn btn--ghost">Batal</Link>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Log' }}
        </button>
      </footer>
    </form>
  </div>
</template>

<script setup>
/**
 * ASUMSI: ProductionLogController@create merender props:
 *   workCenters: [{ id, name, code }]
 *   shifts:      [{ id, name, start_time, end_time }]
 *
 * Submit ke POST /production-logs (StoreProductionLogRequest), termasuk
 * nested downtime_events dalam satu request sesuai US-07.
 */
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  workCenters: { type: Array, default: () => [] },
  shifts: { type: Array, default: () => [] },
})

const form = useForm({
  work_center_id: '',
  shift_id: '',
  log_date: new Date().toISOString().slice(0, 10),
  planned_minutes: null,
  downtime_minutes: 0,
  actual_output: null,
  good_output: null,
  ideal_cycle_time_minutes: null,
  downtime_events: [],
})

function addDowntimeEvent() {
  form.downtime_events.push({
    reason_category: '',
    reason_detail: '',
    duration_minutes: null,
    started_at: '',
  })
}

function removeDowntimeEvent(idx) {
  form.downtime_events.splice(idx, 1)
}

function submit() {
  form.post('/production-logs')
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
  font-size: 1.5rem;
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
  gap: 1.5rem;
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

.field--compact {
  min-width: 140px;
}

.field--grow {
  flex: 1 1 200px;
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

.input--error {
  border-color: #EF4444;
}

.field-error {
  font-size: 0.6875rem;
  color: #DC2626;
}

.downtime-section {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  border-top: 1px solid #F1F5F9;
  padding-top: 1.25rem;
}

.downtime-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.downtime-empty {
  font-size: 0.8125rem;
  color: #94A3B8;
}

.downtime-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.65rem;
  padding: 0.85rem;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 6px;
  border: 1px solid #FCA5A5;
  background: #FEF2F2;
  color: #DC2626;
  cursor: pointer;
  font-size: 0.75rem;
}

.icon-btn:hover {
  background: #FEE2E2;
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

.btn--sm {
  padding: 0.3rem 0.65rem;
  font-size: 0.75rem;
}

.btn--primary {
  background: #0F172A;
  color: #F8FAFC;
}

.btn--primary:hover:not(:disabled) { background: #1E293B; }

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
}

.btn--ghost:hover { background: #F1F5F9; }
</style>