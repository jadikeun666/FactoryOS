<template>
  <div class="form-page">
    <header class="page-header">
      <p class="page-eyebrow">Engine 1 — Job Shop Scheduler</p>
      <h1 class="page-title">Tambah Work Order</h1>
      <p class="page-subtitle">Hanya produk dengan Routing lengkap yang bisa dipilih.</p>
    </header>

    <form class="wo-form" @submit.prevent="submit">
      <label class="field">
        <span>Produk *</span>
        <select v-model="form.product_id" class="input" required>
          <option value="" disabled>Pilih produk…</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
        </select>
        <span v-if="form.errors.product_id" class="field-error">{{ form.errors.product_id }}</span>
        <span v-if="products.length === 0" class="field-hint">
          Belum ada produk dengan Routing lengkap. Lengkapi dulu di halaman Produk.
        </span>
      </label>

      <div class="field-row">
        <label class="field">
          <span>Qty *</span>
          <input v-model.number="form.qty" type="number" step="0.0001" min="0.0001" class="input" required />
          <span v-if="form.errors.qty" class="field-error">{{ form.errors.qty }}</span>
        </label>

        <label class="field">
          <span>Prioritas (1=tertinggi, 10=terendah)</span>
          <input v-model.number="form.priority" type="number" min="1" max="10" class="input" />
          <span v-if="form.errors.priority" class="field-error">{{ form.errors.priority }}</span>
        </label>
      </div>

      <div class="field-row">
        <label class="field">
          <span>Release Date</span>
          <input v-model="form.release_date" type="date" class="input" />
          <span v-if="form.errors.release_date" class="field-error">{{ form.errors.release_date }}</span>
        </label>

        <label class="field">
          <span>Due Date *</span>
          <input v-model="form.due_date" type="date" class="input" required />
          <span v-if="form.errors.due_date" class="field-error">{{ form.errors.due_date }}</span>
        </label>
      </div>

      <label class="field">
        <span>Catatan</span>
        <textarea v-model="form.notes" class="input" rows="3" maxlength="2000"></textarea>
        <span v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</span>
      </label>

      <p class="hint">
        Setelah disimpan, sistem otomatis men-generate urutan operasi (wo_operations)
        dari Routing produk yang dipilih.
      </p>

      <div class="form-actions">
        <Link href="/work-orders" class="btn btn--ghost">Batal</Link>
        <button type="submit" class="btn btn--primary" :disabled="form.processing || products.length === 0">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
/**
 * WorkOrders/Create.vue — form pembuatan Work Order baru (US-01, FR-02).
 * @see app/Http/Controllers/WorkOrderController.php@create,@store
 * @see app/Http/Requests/StoreWorkOrderRequest.php
 *
 * Props: products (Product::has('routings')->get(['id','name','sku'])) --
 * HANYA produk dengan Routing lengkap ditawarkan, supaya generate
 * wo_operations tidak gagal setelah WO dibuat (lihat komentar controller).
 *
 * priority & release_date sengaja dibiarkan kosong (null) di form --
 * backend WorkOrderController@store mengisi default (priority=5,
 * release_date=hari ini) jika tidak diisi, sesuai StoreWorkOrderRequest
 * (keduanya 'nullable').
 */
import { Link, useForm } from '@inertiajs/vue3'

defineProps({
  products: { type: Array, default: () => [] },
})

const form = useForm({
  product_id: '',
  qty: null,
  priority: null,
  release_date: '',
  due_date: '',
  notes: '',
})

function submit() {
  form.post('/work-orders')
}
</script>

<style scoped>
.form-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 640px;
  margin: 0 auto;
  font-family: var(--font-body);
}

.page-eyebrow {
  font-family: var(--font-display);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--signal-amber);
  margin: 0 0 0.25rem;
}

.page-title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--data-ink);
  margin: 0;
}

.page-subtitle {
  font-size: 0.8125rem;
  color: var(--data-ink-muted);
  margin: 0.35rem 0 0;
}

.wo-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.5rem;
  background: var(--panel-graphite);
  border: 1px solid var(--hairline-border);
  border-radius: 10px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--data-ink-muted);
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
  border: 1px solid var(--hairline-border);
  border-radius: 6px;
  font-family: var(--font-body);
  color: var(--data-ink);
  background: var(--surface-steel);
}

.input:focus {
  outline: 2px solid var(--signal-amber);
  outline-offset: 1px;
}

.field-error {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--signal-red);
}

.field-hint {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--signal-amber);
}

.hint {
  font-size: 0.75rem;
  color: var(--data-ink-muted);
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
  font-family: var(--font-display);
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
  background: var(--signal-amber);
  color: #1C1F26;
}

.btn--primary:hover:not(:disabled) { filter: brightness(1.08); }

.btn--primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn--ghost {
  background: var(--panel-graphite);
  border-color: var(--hairline-border);
  color: var(--data-ink-muted);
}

.btn--ghost:hover { background: var(--surface-steel); }
</style>
