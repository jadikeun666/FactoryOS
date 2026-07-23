<template>
  <div class="form-page">
    <header class="page-header">
      <p class="page-eyebrow">Master Data</p>
      <h1 class="page-title">Tambah Produk</h1>
    </header>

    <form class="wc-form" @submit.prevent="submit">
      <label class="field">
        <span>Nama Produk</span>
        <input v-model="form.name" type="text" class="input" required maxlength="150" />
        <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
      </label>

      <label class="field">
        <span>SKU</span>
        <input v-model="form.sku" type="text" class="input" required maxlength="50" placeholder="mis. PRD-001" />
        <span v-if="form.errors.sku" class="field-error">{{ form.errors.sku }}</span>
      </label>

      <label class="field">
        <span>Unit</span>
        <input v-model="form.unit" type="text" class="input" maxlength="20" placeholder="pcs" />
        <span v-if="form.errors.unit" class="field-error">{{ form.errors.unit }}</span>
      </label>

      <label class="field">
        <span>Deskripsi</span>
        <textarea v-model="form.description" class="input" rows="3" maxlength="2000"></textarea>
      </label>

      <p class="hint">Setelah produk dibuat, Anda akan diarahkan ke halaman edit untuk melengkapi BOM dan Routing.</p>

      <div class="form-actions">
        <Link href="/products" class="btn btn--ghost">Batal</Link>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan…' : 'Simpan & Lanjut' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  sku: '',
  unit: 'pcs',
  description: '',
})

function submit() {
  form.post('/products')
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

.hint {
  font-size: 0.75rem;
  color: #94A3B8;
  margin: 0;
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