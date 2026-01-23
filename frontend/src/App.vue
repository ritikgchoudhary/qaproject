<script setup>
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router'
import { useUserStore } from './stores/user'
import { computed, ref, onMounted } from 'vue'
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

async function fetchGlobalSettings() {
    try {
        const res = await axios.get('/api/getSettings.php')
        if (res.data && res.data.site_logo) {
            siteLogo.value = res.data.site_logo
        }
    } catch (e) {
        console.error("Global settings fetch failed", e)
    }
}

async function logout() {
  await userStore.logout()
  router.push('/')
}

onMounted(() => {
    if (isLoggedIn.value) {
        fetchGlobalSettings()
    }
})
</script>

<template>
  <div class="app-container">
    <header class="main-header" v-if="isLoggedIn && !isAgentPage">
      <div class="logo-area">
          <img :src="siteLogo" alt="Site Logo" class="brand-logo-img" />
      </div>
      
      <button @click="logout" class="icon-btn-logout">
        <img src="https://img.icons8.com/3d-fluency/94/shutdown.png" />
      </button>
    </header>

    <main :class="{ 'with-nav': isLoggedIn && !isAgentPage }">
      <RouterView />
    </main>

    <BottomNav v-if="isLoggedIn && !isAgentPage" />
    
    <!-- Global Loader Overlay -->
    <div v-if="loadingStore.isLoading" class="fixed inset-0 z-[9999] bg-black/40 backdrop-blur-md flex flex-col items-center justify-center transition-opacity duration-300">
        <div class="relative w-24 h-24">
            <!-- Pulsing Rings -->
            <div class="absolute inset-0 rounded-full border-4 border-yellow-500/20 animate-ping"></div>
            <div class="absolute inset-2 rounded-full border-4 border-yellow-500/40 animate-pulse"></div>
            
            <!-- Rotating Spinner -->
            <div class="absolute inset-0 rounded-full border-4 border-t-yellow-500 border-r-transparent border-b-transparent border-l-transparent animate-spin"></div>
            
            <!-- Center Icon -->
             <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-3xl animate-pulse">⚡</span>
             </div>
        </div>
        <p class="mt-8 text-yellow-500 font-bold tracking-[0.3em] uppercase text-xs animate-pulse">Processing...</p>
    </div>
  </div>
</template>

<style>
/* Global Styles */
/* Handled in main.css */
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
.main-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.8rem 1.2rem;
  
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
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
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
    height: 48px; /* Adjusted for better visibility */
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
    padding-top: 80px; /* Space for fixed header */
    padding-bottom: 80px; /* Space for bottom nav */
}
</style>
