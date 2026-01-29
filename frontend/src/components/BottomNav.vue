<script setup>
import { RouterLink, useRoute } from 'vue-router'
import { computed } from 'vue'

const route = useRoute()
const currentPath = computed(() => route.path)
</script>

<template>
  <div class="bottom-nav-wrapper">
    <div class="glass-dock">
        <!-- Left Side -->
        <router-link to="/dashboard" class="nav-item" :class="{active: currentPath === '/dashboard'}">
            <div class="icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </div>
            <span class="label">Home</span>
        </router-link>
        
        <router-link to="/wallet" class="nav-item" :class="{active: currentPath === '/wallet'}">
             <div class="icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            </div>
            <span class="label">Wallet</span>
        </router-link>

        <!-- Center FAB (Play) -->
        <div class="fab-container">
            <router-link to="/question" class="play-fab" :class="{active: currentPath === '/question'}">
                 <div class="play-icon-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black fill-current translate-x-0.5" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z" />
                    </svg>
                 </div>
            </router-link>
            <span class="label mt-1 text-yellow-500 font-bold">PLAY</span>
        </div>
        
        <!-- Right Side -->
        <router-link to="/affiliate-team" class="nav-item" :class="{active: currentPath === '/affiliate-team'}">
             <div class="icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <span class="label">Squad</span>
        </router-link>

        <router-link to="/referrals" class="nav-item" :class="{active: currentPath === '/referrals'}">
             <div class="icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                   <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <span class="label">Profile</span>
        </router-link>
    </div>
  </div>
</template>

<style scoped>
.bottom-nav-wrapper {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    z-index: 1000;
}

.glass-dock {
    width: 100%;
    max-width: 480px;
    height: 60px; /* Reduced height */
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-evenly;
    align-items: center;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.5);
    padding-bottom: 5px;
    padding-left: 5px;
    padding-right: 5px;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #64748b;
    transition: all 0.3s;
    position: relative;
    width: 60px;
}

.icon-box {
    width: 24px;
    height: 24px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}

.label {
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* FAB Styles */
.fab-container {
    position: relative;
    top: -18px; /* Adjusted for smaller height */
    display: flex;
    flex-direction: column;
    align-items: center;
}

.play-fab {
    width: 52px; /* Reduced FAB size */
    height: 52px;
    background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(251, 191, 36, 0.4);
    border: 3px solid #0f172a;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.play-fab:active {
    transform: scale(0.9);
}

.play-fab.active {
    box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.2), 0 8px 25px rgba(251, 191, 36, 0.5);
}

/* Active State for normal items */
.nav-item.active {
    color: #fbbf24;
}
.nav-item.active .icon-box {
    transform: translateY(-2px);
}
.nav-item.active svg {
    stroke-width: 2.5;
    filter: drop-shadow(0 0 5px rgba(251, 191, 36, 0.5));
}
</style>
