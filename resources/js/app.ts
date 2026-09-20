import './bootstrap'
import '../css/brand.css'
import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { RouterView } from 'vue-router'
import { router } from './router'
import { setApiResponseHandler } from './lib/api'
import { useAuthStore } from './stores/auth'

const pinia = createPinia()
setApiResponseHandler(response => { useAuthStore(pinia).handleUnauthorized(response) })
createApp({ render: () => h(RouterView) }).use(pinia).use(router).mount('#app')
if ('serviceWorker' in navigator && import.meta.env.PROD) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js?v=7', { scope: '/' }))
