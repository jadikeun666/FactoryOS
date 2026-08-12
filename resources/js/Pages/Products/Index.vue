<template>
  <div class="wc-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Master Data</p>
        <h1 class="page-title">Produk</h1>
        <p class="page-subtitle">Daftar produk beserta kelengkapan BOM dan Routing.</p>
      </div>
      <Link v-if="canManage" href="/products/create" class="btn btn--primary">+ Tambah Produk</Link>
    </header>

    <div v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</div>
    <div v-if="flashError" class="flash flash--error">{{ flashError }}</div>

    <div class="table-wrapper">
      <table class="wc-table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Nama</th>
            <th>Unit</th>
            <th class="num">BOM</th>
            <th class="num">Routing</th>
            <th></th>
            <th v-if="canManage"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id">
            <td class="wc-code">{{ p.sku }}</td>
            <td>{{ p.name }}</td>
            <td>{{ p.unit }}</td>
            <td class="num">
              <span class="count-badge" :class="{ 'count-badge--empty': p.bill_of_materials_count === 0 }">
                {{ p.bill_of_materials_count }}
              </span>
            </td>
            <td class="num">
              <span class="count-badge" :class="{ 'count-badge--empty': p.routings_count === 0 }">
                {{ p.routings_count }}
              </span>
            </td>
            <td>
              <span v-if="p.bill_of_materials_count === 0 || p.routings_count === 0" class="incomplete-tag">
                ⚠️ Belum lengkap
              </span>
            </td>
            <td v-if="canManage" class="actions-col">
              <Link :href="`/products/${p.id}/edit`" class="link-action">Edit / BOM / Routing</Link>
              <button type="button" class="link-action link-action--danger" @click="confirmDelete(p)">Hapus</button>
            </td>
          </tr>
          <tr v-if="products.length === 0">
            <td :colspan="canManage ? 7 : 6" class="empty-row">Belum ada Produk.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

defineProps({
  products: { type: Array, required: true },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)
const canManage = computed(() => page.props.auth?.user?.role === 'admin')

function confirmDelete(product) {
  if (!window.confirm(`Hapus Produk "${product.name}"? Aksi ini tidak bisa dibatalkan.`)) return
  router.delete(`/products/${product.id}`, { preserveScroll: true })
}
</script>

<style scoped>
.wc-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
  font-family: var(--font-body);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
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

.btn {
  display: inline-flex;
  align-items: center;
  padding: 0.55rem 1.1rem;
  font-family: var(--font-display);
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
}

.btn--primary {
  background: var(--signal-amber);
  color: #1C1F26;
}

.btn--primary:hover {
  filter: brightness(1.08);
}

.flash {
  padding: 0.65rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
}

.flash--success {
  background: rgba(74, 155, 110, 0.12);
  border: 1px solid rgba(74, 155, 110, 0.4);
  color: var(--signal-green);
}

.flash--error {
  background: rgba(214, 69, 69, 0.12);
  border: 1px solid rgba(214, 69, 69, 0.4);
  color: var(--signal-red);
}

.table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--hairline-border);
  border-radius: 10px;
}

.wc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.wc-table th,
.wc-table td {
  padding: 0.6rem 0.9rem;
  text-align: left;
  border-bottom: 1px solid var(--hairline-soft);
}

.wc-table thead th {
  background: var(--surface-steel);
  color: var(--data-ink-muted);
  font-weight: 600;
  font-size: 0.75rem;
}

.wc-table th.num,
.wc-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.wc-table td {
  color: var(--data-ink);
}

.wc-code {
  font-family: var(--font-display);
  font-weight: 600;
  color: var(--data-ink);
}

.count-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  background: var(--hairline-soft);
  color: var(--data-ink);
  font-weight: 600;
}

.count-badge--empty {
  background: rgba(214, 69, 69, 0.18);
  color: var(--signal-red);
}

.incomplete-tag {
  font-size: 0.6875rem;
  color: var(--signal-amber);
  font-weight: 600;
  white-space: nowrap;
}

.actions-col {
  display: flex;
  gap: 0.6rem;
  white-space: nowrap;
}

.link-action {
  font-size: 0.75rem;
  font-weight: 600;
  color: #5B8DEF;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  text-decoration: none;
}

.link-action:hover {
  text-decoration: underline;
}

.link-action--danger {
  color: var(--signal-red);
}

.empty-row {
  text-align: center;
  color: var(--data-ink-muted);
  padding: 2rem;
}
</style>