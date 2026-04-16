import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  const isDark = ref(true)

  function init() {
    const saved = localStorage.getItem('ds-theme')
    if (saved === 'light') {
      isDark.value = false
    } else {
      isDark.value = true
    }
    applyTheme()
  }

  function toggle() {
    isDark.value = !isDark.value
    localStorage.setItem('ds-theme', isDark.value ? 'dark' : 'light')
    applyTheme()
  }

  function applyTheme() {
    if (isDark.value) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  return { isDark, init, toggle }
})
