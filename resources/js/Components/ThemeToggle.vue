<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="theme === 'dark' ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
    :title="theme === 'dark' ? 'Mode Terang' : 'Mode Gelap'"
    @click="toggleTheme"
  >
    <!-- Matahari (tampil di mode dark, mengajak pindah ke light) -->
    <svg v-if="theme === 'dark'" viewBox="0 0 24 24" class="theme-toggle__icon" fill="none" stroke="currentColor" stroke-width="1.8">
      <circle cx="12" cy="12" r="4.5" />
      <path d="M12 2.5v2.5M12 19v2.5M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2.5 12H5M19 12h2.5M4.2 19.8L6 18M18 6l1.8-1.8" stroke-linecap="round" />
    </svg>
    <!-- Bulan (tampil di mode light, mengajak pindah ke dark) -->
    <svg v-else viewBox="0 0 24 24" class="theme-toggle__icon" fill="none" stroke="currentColor" stroke-width="1.8">
      <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" stroke-linejoin="round" />
    </svg>
  </button>
</template>

<script setup>
import { useTheme } from '@/composables/useTheme'

const { theme, toggleTheme } = useTheme()
</script>

<style scoped>
.theme-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border-radius: 999px;
  border: 1px solid var(--hairline-border, rgba(0,0,0,0.1));
  background: var(--panel-graphite-raised, #23272F);
  color: var(--data-ink-inverse, #E8EAED);
  cursor: pointer;
  box-shadow: 0 2px 10px rgba(0,0,0,0.18);
  transition: transform 0.2s ease, background-color 0.3s ease, border-color 0.3s ease;
}

.theme-toggle:hover {
  transform: scale(1.06);
}

.theme-toggle:active {
  transform: scale(0.96);
}

.theme-toggle__icon {
  width: 1.15rem;
  height: 1.15rem;
  transition: transform 0.4s ease;
}

.theme-toggle:hover .theme-toggle__icon {
  transform: rotate(20deg);
}

@media (prefers-reduced-motion: reduce) {
  .theme-toggle,
  .theme-toggle__icon {
    transition: none;
  }
  .theme-toggle:hover {
    transform: none;
  }
  .theme-toggle:hover .theme-toggle__icon {
    transform: none;
  }
}
</style>