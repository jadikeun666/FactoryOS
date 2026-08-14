<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="icon"
    :class="{ 'icon--spin': spin }"
    aria-hidden="true"
  >
    <template v-if="name === 'arrow-left'"><path d="M19 12H5M12 19l-7-7 7-7" /></template>
    <template v-else-if="name === 'x'"><path d="M18 6 6 18M6 6l12 12" /></template>
    <template v-else-if="name === 'alert-triangle'">
      <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
      <path d="M12 9v4" /><path d="M12 17h.01" />
    </template>
    <template v-else-if="name === 'check-circle'">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><path d="m9 11 3 3L22 4" />
    </template>
    <template v-else-if="name === 'download'">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M7 10l5 5 5-5" /><path d="M12 15V3" />
    </template>
    <template v-else-if="name === 'refresh-cw'">
      <path d="M21 2v6h-6" /><path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
      <path d="M3 22v-6h6" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
    </template>
    <template v-else-if="name === 'loader'">
      <path d="M12 2v4" /><path d="m16.2 7.8 2.9-2.9" /><path d="M18 12h4" />
      <path d="m16.2 16.2 2.9 2.9" /><path d="M12 18v4" /><path d="m4.9 19.1 2.9-2.9" />
      <path d="M2 12h4" /><path d="m4.9 4.9 2.9 2.9" />
    </template>
  </svg>
</template>

<script setup>
/**
 * Icon.vue — komponen ikon SVG minimal (gaya lucide, stroke-based),
 * pengganti emoji Unicode (⏳⬇↺↻✕⚠️✅←) di seluruh app (2026-08-09).
 * Warna otomatis ikut currentColor -- kontras terjaga di kedua mode tema
 * tanpa perlu prop warna terpisah.
 */
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 14 },
  spin: { type: Boolean, default: false },
})
</script>

<style scoped>
.icon {
  display: inline-block;
  vertical-align: -2px;
  flex-shrink: 0;
}

.icon--spin {
  animation: icon-spin 0.9s linear infinite;
}

@keyframes icon-spin {
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .icon--spin { animation: none; }
}
</style>
