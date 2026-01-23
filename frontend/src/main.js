import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import axios from 'axios'
import { useLoadingStore } from './stores/loading'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Initialize loading store (must be after pinia is active)
const loadingStore = useLoadingStore()

// Axios Interceptors
axios.interceptors.request.use(config => {
    loadingStore.startLoading()
    return config
}, error => {
    loadingStore.stopLoading()
    return Promise.reject(error)
})

axios.interceptors.response.use(response => {
    loadingStore.stopLoading()
    return response
}, error => {
    loadingStore.stopLoading()
    return Promise.reject(error)
})

app.mount('#app')
