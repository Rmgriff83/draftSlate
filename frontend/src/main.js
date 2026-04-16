import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import { useThemeStore } from './stores/theme'

import './assets/css/main.css'
import './assets/css/animations.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Initialize theme before mount so dark class is applied before first paint
const theme = useThemeStore()
theme.init()

app.mount('#app')
