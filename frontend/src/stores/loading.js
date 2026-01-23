
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useLoadingStore = defineStore('loading', () => {
    const isLoading = ref(false)
    const isGlobal = ref(false) // Whether to show the full screen overlay
    const requestCount = ref(0)

    function startLoading(global = true) {
        if (requestCount.value === 0) {
            isLoading.value = true
            isGlobal.value = global
        }
        requestCount.value++
    }

    function stopLoading() {
        requestCount.value--
        if (requestCount.value <= 0) {
            requestCount.value = 0
            // Very small delay to prevent flickering on fast requests
            setTimeout(() => {
                if (requestCount.value === 0) {
                    isLoading.value = false
                    isGlobal.value = false
                }
            }, 100)
        }
    }

    return { isLoading, isGlobal, startLoading, stopLoading }
})
