
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useLoadingStore = defineStore('loading', () => {
    const isLoading = ref(false)
    const requestCount = ref(0)

    function startLoading() {
        if (requestCount.value === 0) {
            isLoading.value = true
        }
        requestCount.value++
    }

    function stopLoading() {
        requestCount.value--
        if (requestCount.value <= 0) {
            requestCount.value = 0
            // Add a small delay for smoother UX
            setTimeout(() => {
                if (requestCount.value === 0) {
                    isLoading.value = false
                }
            }, 300)
        }
    }

    return { isLoading, startLoading, stopLoading }
})
