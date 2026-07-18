<template>
  <div class="test-page">
    <h1>Dev Test — OeeGauge.vue</h1>
    <p class="hint">Halaman ini sementara untuk verifikasi visual. Hapus setelah selesai.</p>

    <div class="grid">
      <div>
        <h2>World class (OEE tinggi)</h2>
        <OeeGauge
          work-center-id="1"
          work-center-name="Mesin Bubut 01"
          :initial-snapshot="{
            work_center_id: 1,
            log_date: '2026-07-17',
            shift_id: 1,
            availability: '0.920000',
            performance: '0.960000',
            quality: '0.999900',
            oee: '0.882675',
            computed_at: '2026-07-17T09:00:00+00:00',
          }"
        />
      </div>

      <div>
        <h2>Typical (OEE ~77%, contoh manual docs)</h2>
        <OeeGauge
          work-center-id="2"
          work-center-name="Mesin Las 25"
          :initial-snapshot="{
            work_center_id: 2,
            log_date: '2026-07-17',
            shift_id: 1,
            availability: '0.875000',
            performance: '0.904762',
            quality: '0.973684',
            oee: '0.770833',
            computed_at: '2026-07-17T08:30:00+00:00',
          }"
        />
      </div>

      <div>
        <h2>Low (perlu investigasi)</h2>
        <OeeGauge
          work-center-id="3"
          work-center-name="Mesin CNC 03"
          :initial-snapshot="{
            work_center_id: 3,
            log_date: '2026-07-17',
            shift_id: 2,
            availability: '0.600000',
            performance: '0.550000',
            quality: '0.900000',
            oee: '0.297000',
            computed_at: '2026-07-17T07:15:00+00:00',
          }"
        />
      </div>

      <div>
        <h2>Belum ada data (snapshot null)</h2>
        <OeeGauge
          work-center-id="4"
          work-center-name="Mesin Baru 04"
          :initial-snapshot="null"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import OeeGauge from '@/Components/OeeGauge.vue'
</script>

<style scoped>
.test-page {
  padding: 2rem;
  max-width: 900px;
  margin: 0 auto;
}
.hint {
  color: #94A3B8;
  font-size: 0.8125rem;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}
h2 {
  font-size: 0.8125rem;
  color: #475569;
  margin-bottom: 0.5rem;
}
</style>