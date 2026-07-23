<template>
  <div class="edit-page">
    <header class="page-header">
      <div>
        <p class="page-eyebrow">Master Data</p>
        <h1 class="page-title">{{ product.name }}</h1>
        <p class="page-subtitle">{{ product.sku }} · {{ product.unit }}</p>
      </div>
      <Link href="/products" class="btn btn--ghost">← Kembali</Link>
    </header>

    <div v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</div>
    <div v-if="flashError" class="flash flash--error">{{ flashError }}</div>

    <!-- Detail Produk -->
    <section class="panel">
      <h2 class="panel-title">Detail Produk</h2>
      <form class="detail-form" @submit.prevent="submitDetail">
        <label class="field">
          <span>Nama Produk</span>
          <input v-model="detailForm.name" type="text" class="input" required maxlength="150" />
          <span v-if="detailForm.errors.name" class="field-error">{{ detailForm.errors.name }}</span>
        </label>
        <label class="field">
          <span>SKU</span>
          <input v-model="detailForm.sku" type="text" class="input" required maxlength="50" />
          <span v-if="detailForm.errors.sku" class="field-error">{{ detailForm.errors.sku }}</span>
        </label>
        <label class="field">
          <span>Unit</span>
          <input v-model="detailForm.unit" type="text" class="input" required maxlength="20" />
        </label>
        <label class="field field--wide">
          <span>Deskripsi</span>
          <textarea v-model="detailForm.description" class="input" rows="2" maxlength="2000"></textarea>
        </label>
        <div class="form-actions form-actions--inline">
          <button type="submit" class="btn btn--primary btn--small" :disabled="detailForm.processing">
            {{ detailForm.processing ? 'Menyimpan…' : 'Simpan Detail' }}
          </button>
        </div>
      </form>
    </section>

    <!-- BOM Editor -->
    <section class="panel">
      <h2 class="panel-title">Bill of Materials (BOM)</h2>

      <table v-if="product.bill_of_materials.length > 0" class="editor-table">
        <thead>
          <tr>
            <th>Material</th>
            <th class="num">Qty per Unit</th>
            <th>Unit</th>
            <th>Catatan</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="bom in product.bill_of_materials" :key="bom.id">
            <template v-if="editingBomId === bom.id">
              <td>{{ bom.material?.name }}</td>
              <td class="num"><input v-model.number="bomEditForm.qty_per_unit" type="number" step="0.000001" min="0" class="input input--cell" /></td>
              <td><input v-model="bomEditForm.unit" type="text" class="input input--cell" maxlength="20" /></td>
              <td><input v-model="bomEditForm.notes" type="text" class="input input--cell" maxlength="2000" /></td>
              <td class="row-actions">
                <button type="button" class="link-action" :disabled="bomEditForm.processing" @click="submitBomEdit(bom)">Simpan</button>
                <button type="button" class="link-action link-action--muted" @click="cancelBomEdit">Batal</button>
              </td>
            </template>
            <template v-else>
              <td>{{ bom.material?.name }} <span class="muted">({{ bom.material?.sku }})</span></td>
              <td class="num">{{ formatNumber(bom.qty_per_unit, 6) }}</td>
              <td>{{ bom.unit }}</td>
              <td class="muted">{{ bom.notes || '–' }}</td>
              <td class="row-actions">
                <button type="button" class="link-action" @click="startBomEdit(bom)">Edit</button>
                <button type="button" class="link-action link-action--danger" @click="confirmDeleteBom(bom)">Hapus</button>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
      <p v-else class="empty-note">Belum ada item BOM.</p>

      <form class="add-row-form" @submit.prevent="submitBomAdd">
        <select v-model="bomAddForm.material_id" class="input" required>
          <option value="" disabled>Pilih Material…</option>
          <option v-for="m in availableMaterials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.sku }})</option>
        </select>
        <input v-model.number="bomAddForm.qty_per_unit" type="number" step="0.000001" min="0" placeholder="Qty per unit" class="input input--narrow" required />
        <input v-model="bomAddForm.unit" type="text" placeholder="Unit" class="input input--narrow" maxlength="20" required />
        <input v-model="bomAddForm.notes" type="text" placeholder="Catatan (opsional)" class="input" maxlength="2000" />
        <button type="submit" class="btn btn--primary btn--small" :disabled="bomAddForm.processing">+ Tambah</button>
      </form>
      <span v-if="bomAddForm.errors.material_id || bomAddForm.errors.qty_per_unit" class="field-error">
        {{ bomAddForm.errors.material_id || bomAddForm.errors.qty_per_unit }}
      </span>
    </section>

    <!-- Routing Editor -->
    <section class="panel">
      <h2 class="panel-title">Routing (Urutan Operasi)</h2>

      <table v-if="product.routings.length > 0" class="editor-table">
        <thead>
          <tr>
            <th class="num">Urutan</th>
            <th>Mesin</th>
            <th class="num">Waktu Proses (menit)</th>
            <th class="num">Setup (menit)</th>
            <th>Catatan</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in product.routings" :key="r.id">
            <template v-if="editingRoutingId === r.id">
              <td class="num"><input v-model.number="routingEditForm.sequence" type="number" min="1" class="input input--cell input--tiny" /></td>
              <td>
                <select v-model.number="routingEditForm.work_center_id" class="input input--cell">
                  <option v-for="wc in workCenters" :key="wc.id" :value="wc.id">{{ wc.name }}</option>
                </select>
              </td>
              <td class="num"><input v-model.number="routingEditForm.std_process_time_minutes" type="number" step="0.0001" min="0" class="input input--cell" /></td>
              <td class="num"><input v-model.number="routingEditForm.setup_time_minutes" type="number" step="0.0001" min="0" class="input input--cell" /></td>
              <td><input v-model="routingEditForm.notes" type="text" class="input input--cell" maxlength="2000" /></td>
              <td class="row-actions">
                <button type="button" class="link-action" :disabled="routingEditForm.processing" @click="submitRoutingEdit(r)">Simpan</button>
                <button type="button" class="link-action link-action--muted" @click="cancelRoutingEdit">Batal</button>
              </td>
            </template>
            <template v-else>
              <td class="num">{{ r.sequence }}</td>
              <td>{{ r.work_center?.name }} <span class="muted">({{ r.work_center?.code }})</span></td>
              <td class="num">{{ formatNumber(r.std_process_time_minutes, 4) }}</td>
              <td class="num">{{ formatNumber(r.setup_time_minutes, 4) }}</td>
              <td class="muted">{{ r.notes || '–' }}</td>
              <td class="row-actions">
                <button type="button" class="link-action" @click="startRoutingEdit(r)">Edit</button>
                <button type="button" class="link-action link-action--danger" @click="confirmDeleteRouting(r)">Hapus</button>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
      <p v-else class="empty-note">Belum ada operasi routing.</p>

      <form class="add-row-form" @submit.prevent="submitRoutingAdd">
        <input v-model.number="routingAddForm.sequence" type="number" min="1" placeholder="Urutan" class="input input--narrow input--tiny" required />
        <select v-model.number="routingAddForm.work_center_id" class="input" required>
          <option value="" disabled>Pilih Mesin…</option>
          <option v-for="wc in workCenters" :key="wc.id" :value="wc.id">{{ wc.name }} ({{ wc.code }})</option>
        </select>
        <input v-model.number="routingAddForm.std_process_time_minutes" type="number" step="0.0001" min="0" placeholder="Waktu proses" class="input input--narrow" required />
        <input v-model.number="routingAddForm.setup_time_minutes" type="number" step="0.0001" min="0" placeholder="Setup" class="input input--narrow" />
        <input v-model="routingAddForm.notes" type="text" placeholder="Catatan (opsional)" class="input" maxlength="2000" />
        <button type="submit" class="btn btn--primary btn--small" :disabled="routingAddForm.processing">+ Tambah</button>
      </form>
      <span v-if="routingAddForm.errors.sequence || routingAddForm.errors.work_center_id" class="field-error">
        {{ routingAddForm.errors.sequence || routingAddForm.errors.work_center_id }}
      </span>
    </section>
  </div>
</template>

<script setup>
/**
 * Products/Edit.vue — halaman edit Product + nested BOM editor + Routing
 * editor (docs/architecture.md: satu ProductController untuk keduanya).
 *
 * ASUMSI props dari ProductController::edit():
 *   product:     { id, name, sku, unit, description,
 *                  bill_of_materials: [{ id, material_id, qty_per_unit,
 *                    unit, notes, material: {id,name,sku,unit} }],
 *                  routings: [{ id, sequence, work_center_id,
 *                    std_process_time_minutes, setup_time_minutes, notes,
 *                    work_center: {id,name,code} }] (sudah urut sequence) }
 *   materials:   Material[] (semua, untuk dropdown BOM)
 *   workCenters: WorkCenter[] (hanya is_active=true, untuk dropdown Routing)
 *
 * Setiap baris BOM/Routing pakai inline-edit (klik "Edit" -> baris jadi
 * input, "Simpan"/"Batal") -- bukan modal terpisah, supaya tetap
 * konsisten dengan filosofi "thin, tabular" yang sudah dipakai MrpGrid.vue.
 *
 * availableMaterials: filter material yang BELUM ada di BOM produk ini,
 * supaya dropdown "Tambah" tidak menawarkan duplikat (constraint
 * UNIQUE(product_id, material_id) di database.md) -- validasi utama tetap
 * di backend (storeBom()), ini murni UX preventif.
 */
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  product: { type: Object, required: true },
  materials: { type: Array, required: true },
  workCenters: { type: Array, required: true },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)

const availableMaterials = computed(() => {
  const usedIds = new Set(props.product.bill_of_materials.map((b) => b.material_id))
  return props.materials.filter((m) => !usedIds.has(m.id))
})

function formatNumber(value, decimals) {
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: decimals })
}

// ── Detail Produk ──────────────────────────────────────────────
const detailForm = useForm({
  name: props.product.name,
  sku: props.product.sku,
  unit: props.product.unit,
  description: props.product.description,
})

function submitDetail() {
  detailForm.put(`/products/${props.product.id}`, { preserveScroll: true })
}

// ── BOM: tambah ────────────────────────────────────────────────
const bomAddForm = useForm({
  material_id: '',
  qty_per_unit: null,
  unit: '',
  notes: '',
})

function submitBomAdd() {
  bomAddForm.post(`/products/${props.product.id}/bom`, {
    preserveScroll: true,
    onSuccess: () => bomAddForm.reset(),
  })
}

// ── BOM: edit inline ───────────────────────────────────────────
const editingBomId = ref(null)
const bomEditForm = useForm({ qty_per_unit: null, unit: '', notes: '' })

function startBomEdit(bom) {
  editingBomId.value = bom.id
  bomEditForm.qty_per_unit = Number(bom.qty_per_unit)
  bomEditForm.unit = bom.unit
  bomEditForm.notes = bom.notes
}

function cancelBomEdit() {
  editingBomId.value = null
}

function submitBomEdit(bom) {
  bomEditForm.put(`/products/${props.product.id}/bom/${bom.id}`, {
    preserveScroll: true,
    onSuccess: () => { editingBomId.value = null },
  })
}

function confirmDeleteBom(bom) {
  if (!window.confirm(`Hapus item BOM "${bom.material?.name}"?`)) return
  router.delete(`/products/${props.product.id}/bom/${bom.id}`, { preserveScroll: true })
}

// ── Routing: tambah ────────────────────────────────────────────
const routingAddForm = useForm({
  sequence: (props.product.routings.at(-1)?.sequence ?? 0) + 1,
  work_center_id: '',
  std_process_time_minutes: null,
  setup_time_minutes: 0,
  notes: '',
})

function submitRoutingAdd() {
  routingAddForm.post(`/products/${props.product.id}/routings`, {
    preserveScroll: true,
    onSuccess: () => {
      const nextSeq = (props.product.routings.at(-1)?.sequence ?? 0) + 1
      routingAddForm.reset()
      routingAddForm.sequence = nextSeq
      routingAddForm.setup_time_minutes = 0
    },
  })
}

// ── Routing: edit inline ───────────────────────────────────────
const editingRoutingId = ref(null)
const routingEditForm = useForm({
  sequence: null, work_center_id: null, std_process_time_minutes: null,
  setup_time_minutes: null, notes: '',
})

function startRoutingEdit(r) {
  editingRoutingId.value = r.id
  routingEditForm.sequence = r.sequence
  routingEditForm.work_center_id = r.work_center_id
  routingEditForm.std_process_time_minutes = Number(r.std_process_time_minutes)
  routingEditForm.setup_time_minutes = Number(r.setup_time_minutes)
  routingEditForm.notes = r.notes
}

function cancelRoutingEdit() {
  editingRoutingId.value = null
}

function submitRoutingEdit(r) {
  routingEditForm.put(`/products/${props.product.id}/routings/${r.id}`, {
    preserveScroll: true,
    onSuccess: () => { editingRoutingId.value = null },
  })
}

function confirmDeleteRouting(r) {
  if (!window.confirm(`Hapus operasi routing urutan #${r.sequence}?`)) return
  router.delete(`/products/${props.product.id}/routings/${r.id}`, { preserveScroll: true })
}
</script>

<style scoped>
.edit-page {
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
  font-family: monospace;
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

.panel {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.25rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
}

.panel-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}

.detail-form {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.85rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.75rem;
  color: #334155;
  font-weight: 600;
}

.field--wide {
  grid-column: 1 / -1;
}

.input {
  padding: 0.45rem 0.6rem;
  font-size: 0.8125rem;
  font-weight: 400;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  font-family: inherit;
}

.input--cell {
  padding: 0.3rem 0.45rem;
  font-size: 0.75rem;
  width: 100%;
}

.input--narrow {
  width: 8rem;
  flex-shrink: 0;
}

.input--tiny {
  width: 4.5rem;
}

.field-error {
  font-size: 0.6875rem;
  font-weight: 500;
  color: #DC2626;
}

.form-actions--inline {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
}

.editor-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.editor-table th,
.editor-table td {
  padding: 0.5rem 0.6rem;
  text-align: left;
  border-bottom: 1px solid #F1F5F9;
  vertical-align: middle;
}

.editor-table thead th {
  color: #64748B;
  font-weight: 600;
  font-size: 0.6875rem;
  text-transform: uppercase;
}

.editor-table th.num,
.editor-table td.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.muted {
  color: #94A3B8;
  font-size: 0.75rem;
}

.row-actions {
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
}

.link-action:hover {
  text-decoration: underline;
}

.link-action--danger {
  color: #DC2626;
}

.link-action--muted {
  color: #94A3B8;
}

.link-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.empty-note {
  font-size: 0.8125rem;
  color: #94A3B8;
  margin: 0;
}

.add-row-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px dashed #E2E8F0;
}

.add-row-form .input {
  flex: 1;
  min-width: 8rem;
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
  flex-shrink: 0;
}

.btn--small {
  padding: 0.4rem 0.85rem;
  font-size: 0.75rem;
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