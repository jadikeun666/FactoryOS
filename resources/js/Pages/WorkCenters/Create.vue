<template>
  <div class="form-page">
    <header class="page-header">
      <p class="page-eyebrow">Master Data</p>
      <h1 class="page-title">Tambah Work Center</h1>
    </header>

    <form class="wc-form" @submit.prevent="submit">
      <label class="field">
        <span>Nama Mesin</span>
        <input v-model="form.name" type="text" class="input" required maxlength="100" />
        <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
      </label>

      <label class="field">
        <span>Kode Mesin</span>
        <input v-model="form.code" type="text" class="input" required maxlength="20" placeholder="mis. M06" />
        <span v-if="form.errors.code" class="field-error">{{ form.errors.code }}</span>
      </label>

      <div class="field-row">
        <label class="field">
          <span>Kapasitas per Shift (menit)</span>
          <input v-model.number="form.capacity_per_shift_minutes" type="number" step="0.01" min="0" class="input" />
          <span v-if="form.errors.capacity_per_shift_minutes" class="field-error">{{ form.errors.capacity_per_shift_minutes }}</span>
        </label>

        <label class="field">
          <span>Setup Time (menit)</span>
          <input v-model.number="form.setup_time_minutes" type="number" step="0.01" min="0" class="input" />
          <span v-if="form.errors.setup_time_minutes" class="field-error">{{ form.errors.setup_time_minutes }}</span>
        </label>
      </div>

      <label class="field field--checkbox">
        <input v-model="form.is_active" type="checkbox" />
        <span>Aktif</span>
      </label>

      <label class="field">
        <span>Deskripsi</span>
        <textarea v-model="form.description" class="input" rows="3" maxlength="2000"></textarea>
      </label>

      <div class="form-actions">
        <Link href="/work-centers" class="btn btn--ghost">Batal</Link>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  code: '',
  capacity_per_shift_minutes: 480,
  setup_time_minutes: 0,
  is_active: true,
  description: '',
})

function submit() {
  form.post('/work-centers')
}
</script>

<style scoped>
.form-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 560px;
  margin: 0 auto;
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

.wc-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.5rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.8125rem;
  color: #334155;
  font-weight: 600;
  flex: 1;
}

.field-row {
  display: flex;
  gap: 1rem;
}

.field--checkbox {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

.input {
  padding: 0.5rem 0.7rem;
  font-size: 0.8125rem;
  font-weight: 400;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  font-family: inherit;
}

.field-error {
  font-size: 0.6875rem;
  font-weight: 500;
  color: #DC2626;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  margin-top: 0.5rem;
}

.btn {
  padding: 0.55rem 1.1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}

.btn--primary {
  background: #0F172A;
  color: #F8FAFC;
}

.btn--primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn--ghost {
  background: #FFFFFF;
  border-color: #E2E8F0;
  color: #334155;
}
</style>