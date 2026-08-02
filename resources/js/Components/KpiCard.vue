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
  tone: { type: String, default: 'default' },
  decimals: { type: Number, default: 0 },
  delay: { type: Number, default: 0 },
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
    const eased = 1 - Math.pow(1 - progress, 3)
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
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 1rem 1.15rem;
  background: linear-gradient(180deg, var(--card-bg-start) 0%, var(--card-bg-end) 100%);
  border: 1px solid var(--hairline);
  border-left: 3px solid var(--signal-green);
  border-radius: 4px;
  overflow: hidden;
  animation: kpi-rise 0.5s ease both;
  animation-delay: var(--delay, 0ms);
  transition: background-color 0.25s ease, border-color 0.25s ease;
}


@keyframes kpi-rise {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
  .kpi-card { animation: none; }
  .kpi-card__value { transition: none !important; }
}

.kpi-card__label {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--data-ink-muted);
}
.kpi-card__value {
  font-family: var(--font-display);
  font-size: 1.625rem;
  font-weight: 700;
  color: var(--data-ink-inverse);
  font-variant-numeric: tabular-nums;
  transition: color 0.3s ease;
}
.kpi-card__suffix {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--data-ink-muted);
  margin-left: 0.2rem;
}
.kpi-card__hint {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  color: var(--data-ink-muted);
}

.kpi-card--default { border-left-color: var(--data-ink-muted); }

.kpi-card--warn { border-left-color: var(--signal-amber); }
.kpi-card--warn .kpi-card__value { color: var(--signal-amber); }

.kpi-card--danger { border-left-color: var(--signal-red); }
.kpi-card--danger .kpi-card__value { color: var(--signal-red); }
.kpi-card--danger {
  animation: kpi-rise 0.5s ease both, kpi-pulse 2.4s ease-in-out infinite 0.5s;
}
@keyframes kpi-pulse {
  0%, 100% { border-left-color: var(--signal-red); }
  50%      { border-left-color: rgba(214, 69, 69, 0.45); }
}
@media (prefers-reduced-motion: reduce) {
  .kpi-card--danger { animation: none; }
}

.kpi-card--success { border-left-color: var(--signal-green); }
.kpi-card--success .kpi-card__value { color: var(--signal-green); }
</style>