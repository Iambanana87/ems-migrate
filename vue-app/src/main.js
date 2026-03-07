import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'

// Global CSS (Tailwind + Animations only)
import './assets/css/global.css'
import './assets/css/legacy.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
