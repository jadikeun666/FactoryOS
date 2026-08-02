import { ref, watch } from 'vue'

const STORAGE_KEY = 'factoryos-theme'

function getSystemPreference() {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function getStoredTheme() {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme)
}

const theme = ref(getStoredTheme() ?? getSystemPreference())
applyTheme(theme.value)

if (!getStoredTheme()) {
  window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (!getStoredTheme()) {
      theme.value = e.matches ? 'dark' : 'light'
    }
  })
}

watch(theme, (newTheme) => {
  applyTheme(newTheme)
  try {
    localStorage.setItem(STORAGE_KEY, newTheme)
  } catch {
    // localStorage tidak tersedia — tema tetap berfungsi untuk sesi berjalan
  }
})

export function useTheme() {
  function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }
  return { theme, toggleTheme }
}
