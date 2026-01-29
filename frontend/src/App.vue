<script setup>
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router'
import { useUserStore } from './stores/user'
import { computed, ref, onMounted, watch } from 'vue'
import BottomNav from './components/BottomNav.vue'
import { useLoadingStore } from './stores/loading'
import axios from 'axios'

const userStore = useUserStore()
const loadingStore = useLoadingStore()
const router = useRouter()
const route = useRoute()
const isLoggedIn = computed(() => !!userStore.user)
const isAgentPage = computed(() => route.name === 'agent_dashboard')

const siteLogo = ref('/digiearn_logo_new.png')
const logoError = ref(false)

async function fetchGlobalSettings() {
    try {
        const res = await axios.get('/api/getSettings.php')
        if (res.data && res.data.site_logo) {
            // Use the logo URL directly from API
            siteLogo.value = res.data.site_logo
            logoError.value = false
            console.log("Logo loaded from API:", siteLogo.value)
        } else {
            console.warn("No site_logo in API response, using default")
        }
    } catch (e) {
        console.error("Global settings fetch failed", e)
    }
}

function onLogoError() {
    console.error("Logo failed to load:", siteLogo.value)
    logoError.value = true
    // Only fallback to default if it's not already the default
    if (siteLogo.value !== '/digiearn_logo_new.png') {
        console.log("Falling back to default logo")
        siteLogo.value = '/digiearn_logo_new.png'
    }
}

async function logout() {
  await userStore.logout()
  router.push('/')
}

onMounted(() => {
    // Always fetch settings, logo might be needed even when not logged in
    fetchGlobalSettings()
})

// Also fetch when user logs in
watch(isLoggedIn, (newVal) => {
    if (newVal) {
        fetchGlobalSettings()
    }
})
</script>

<template>
  <div class="app-container">
    <header class="main-header" v-if="isLoggedIn && !isAgentPage">
      <div class="logo-area">
          <img :src="siteLogo" alt="Site Logo" class="brand-logo-img" @error="onLogoError" />
      </div>
      
      <button @click="logout" class="icon-btn-logout">
        <img src="https://img.icons8.com/3d-fluency/94/shutdown.png" />
      </button>
    </header>

    <main :class="{ 'with-nav': isLoggedIn && !isAgentPage }">
      <RouterView />
    </main>

    <BottomNav v-if="isLoggedIn && !isAgentPage" />
    
    <!-- Global Loading System -->
    <div class="loading-system">
        <!-- Full Screen Loader (Only if isGlobal is true) -->
        <Transition name="fade">
            <div v-if="loadingStore.isLoading && loadingStore.isGlobal" class="loader-overlay">
                <div class="relative flex flex-col items-center">
                    <!-- Clock Loader -->
                    <div class="clock-loader"></div>
                    
                    <div class="mt-6 flex flex-col items-center gap-1">
                        <p class="text-yellow-500 font-extrabold tracking-[0.4em] uppercase text-[10px] animate-pulse">Processing</p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
  </div>
</template>

<style>
/* Global Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Clock Animation */
.clock-loader {
  width: 44px;
  height: 44px;
  border: 2.5px solid #fbbf24;
  border-radius: 50%;
  position: relative;
  background: transparent;
  box-shadow: 0 0 15px rgba(251, 191, 36, 0.2);
}
.clock-loader::after, .clock-loader::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  background: #fbbf24;
  border-radius: 10px;
  transform-origin: center top;
}
.clock-loader::after {
  width: 2px;
  height: 16px;
  animation: clock-spin 2s linear infinite;
}
.clock-loader::before {
  width: 2px;
  height: 12px;
  animation: clock-spin 10s linear infinite;
}

@keyframes clock-spin {
  0% { transform: translate(-50%, 0%) rotate(0deg); }
  100% { transform: translate(-50%, 0%) rotate(360deg); }
}
</style>

<style scoped>
.app-container {
    max-width: 480px; /* Mobile width focus */
    margin: 0 auto;
    background: #0f172a;
    min-height: 100vh;
    box-shadow: 0 0 50px rgba(0,0,0,0.5);
    position: relative;
    overflow-x: hidden;
}

/* Loading Bar removed */

/* Loader Overlay */

/* Loader Overlay */
.loader-overlay {
    position: fixed;
    inset: 0;
    z-index: 9998;
    background: rgba(0, 0, 0, 0.15); /* High transparency */
    backdrop-filter: blur(12px); /* Premium Blur */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.main-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 1rem; /* Compact padding */
  
  /* Dark Theme */
  background: rgba(15, 23, 42, 0.95);
  backdrop-filter: blur(12px);
  position: fixed; 
  top: 0; 
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 480px;
  z-index: 50; /* Higher z-index */
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.logo-area { display: flex; align-items: center; gap: 10px; }
.logo-icon {
    width: 32px;
    height: 32px;
    background: rgba(251, 191, 36, 0.1);
    border-radius: 8px;
    padding: 5px;
    border: 1px solid rgba(251, 191, 36, 0.2);
    display: flex; 
    align-items: center; 
    justify-content: center;
}
.logo-icon img { width: 100%; height: 100%; object-fit: contain; }
.logo-text {
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: 1px;
    color: #fff;
    font-family: 'Outfit', sans-serif;
}

.brand-logo-img {
    height: 36px; /* Reduced for compact feel */
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.icon-btn-logout {
    width: 36px;
    height: 36px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.icon-btn-logout img { width: 18px; height: 18px; }
.icon-btn-logout:active { transform: scale(0.95); background: rgba(239, 68, 68, 0.2); }
main {
    width: 100%;
    padding: 0;
}
main.with-nav {
    padding-top: 60px; /* Reduced space for fixed header */
    padding-bottom: 70px; /* Reduced space for bottom nav */
}
</style>
