<template>
  <div class="form-page">
    <header class="page-header">
      <p class="page-eyebrow">Master Data</p>
      <h1 class="page-title">Edit Material</h1>
    </header>

    <form class="wc-form" @submit.prevent="submit">
      <label class="field">
        <span>Nama Material</span>
        <input v-model="form.name" type="text" class="input" required maxlength="150" />
        <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
      </label>

      <label class="field">
        <span>SKU</span>
        <input v-model="form.sku" type="text" class="input" required maxlength="50" />
        <span v-if="form.errors.sku" class="field-error">{{ form.errors.sku }}</span>
      </label>

      <div class="field-row">
        <label class="field">
          <span>Unit</span>
          <input v-model="form.unit" type="text" class="input" required maxlength="20" />
          <span v-if="form.errors.unit" class="field-error">{{ form.errors.unit }}</span>
        </label>

        <label class="field">
          <span>Biaya Satuan (Rp)</span>
          <input v-model.number="form.unit_cost" type="number" step="0.01" min="0" class="input" />
          <span v-if="form.errors.unit_cost" class="field-error">{{ form.errors.unit_cost }}</span>
        </label>
      </div>

      <label class="field">
        <span>Deskripsi</span>
        <textarea v-model="form.description" class="input" rows="3" maxlength="2000"></textarea>
      </label>

      <div class="form-actions">
        <Link href="/materials" class="btn btn--ghost">Batal</Link>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  material: { type: Object, required: true },
})

const form = useForm({
  name: props.material.name,
  sku: props.material.sku,
  unit: props.material.unit,
  unit_cost: Number(props.material.unit_cost),
  description: props.material.description,
})

function submit() {
  form.put(`/materials/${props.material.id}`)
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