<template>
  <div class="kpi-card" :class="`kpi-card--${tone}`" :style="{ '--delay': `${delay}ms` }">
    <span class="kpi-card__label">{{ label }}</span>
    <span class="kpi-card__value">
      {{ displayValue }}<span v-if="suffix" class="kpi-card__suffix">{{ suffix }}</span>
    </span>
    <span v-if="hint" class="kpi-card__hint">{{ hint }}</span>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: Number, default: 0 },
  suffix: { type: String, default: '' },
  hint: { type: String, default: '' },
  tone: { type: String, default: 'default' }, // default | warn | danger | success
  decimals: { type: Number, default: 0 },
  delay: { type: Number, default: 0 }, // ms, untuk stagger entrance antar kartu
})

const displayValue = ref(formatValue(0))
const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

function formatValue(n) {
  return props.decimals > 0
    ? n.toFixed(props.decimals)
    : Math.round(n).toLocaleString('id-ID')
}

function animateTo(target) {
  if (prefersReducedMotion) {
    displayValue.value = formatValue(target)
    return
  }

  const duration = 700
  const start = performance.now()
  const from = 0

  function tick(now) {
    const progress = Math.min((now - start) / duration, 1)
    const eased = 1 - Math.pow(1 - progress, 3) // ease-out-cubic
    displayValue.value = formatValue(from + (target - from) * eased)
    if (progress < 1) requestAnimationFrame(tick)
  }

  requestAnimationFrame(tick)
}

onMounted(() => {
  window.setTimeout(() => animateTo(props.value ?? 0), props.delay)
})

watch(() => props.value, (newVal) => animateTo(newVal ?? 0))
</script>

<style scoped>
.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 1rem;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  animation: kpi-rise 0.5s ease both;
  animation-delay: var(--delay, 0ms);
}

@keyframes kpi-rise {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
  .kpi-card { animation: none; }
}

.kpi-card__label {
  font-size: 0.75rem;
  color: #64748B;
}

.kpi-card__value {
  font-size: 1.375rem;
  font-weight: 700;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
}

.kpi-card__suffix {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #94A3B8;
  margin-left: 0.15rem;
}

.kpi-card__hint {
  font-size: 0.6875rem;
  color: #94A3B8;
}

.kpi-card--warn .kpi-card__value { color: #B45309; }
.kpi-card--danger .kpi-card__value { color: #DC2626; }
.kpi-card--success .kpi-card__value { color: #15803D; }
</style>