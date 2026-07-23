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

.btn {
  display: inline-flex;
  align-items: center;
  padding: 0.55rem 1.1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 8px;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
}

.btn--primary {
  background: #0F172A;
  color: #F8FAFC;
}

.btn--primary:hover {
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.25);
}

.flash {
  padding: 0.65rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
}

.flash--success {
  background: #F0FDF4;
  border: 1px solid #BBF7D0;
  color: #15803D;
}

.flash--error {
  background: #FEF2F2;
  border: 1px solid #FECACA;
  color: #B91C1C;
}

.table-wrapper {
  overflow-x: auto;
  border: 1px solid #E5E7EB;
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
  border-bottom: 1px solid #F1F5F9;
}

.wc-table thead th {
  background: #F8FAFC;
  color: #64748B;
  font-weight: 600;
  font-size: 0.75rem;
}

.wc-table th.num,
.wc-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.wc-code {
  font-family: monospace;
  font-weight: 600;
  color: #334155;
}

.count-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #F1F5F9;
  color: #334155;
  font-weight: 600;
}

.count-badge--empty {
  background: #FEE2E2;
  color: #B91C1C;
}

.incomplete-tag {
  font-size: 0.6875rem;
  color: #B45309;
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
  color: #2563EB;
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
  color: #DC2626;
}

.empty-row {
  text-align: center;
  color: #94A3B8;
  padding: 2rem;
}
</style>