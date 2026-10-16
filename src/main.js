import { createApp } from 'vue'
import App from './App.vue'

const appEl = document.getElementById('sticky-crm-root')
if (appEl) {
    createApp(App).mount(appEl)
}