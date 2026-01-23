<script setup>
import { onMounted, ref, computed } from 'vue'
import { useUserStore } from '../stores/user'
import axios from 'axios'
import { useRouter } from 'vue-router'

const userStore = useUserStore()
const router = useRouter()
const referralsCount = ref(0)
const myRefCode = ref('')
const referrals = ref([])
const page = ref(1)
const hasMore = ref(false)
const loadingReferrals = ref(false)

const toast = ref({ show: false, message: '', type: 'success' })

const showToast = (msg, type = 'success') => {
    toast.value = { show: true, message: msg, type }
    setTimeout(() => toast.value.show = false, 3000)
}

const user = computed(() => userStore.user)
const wallet = computed(() => userStore.wallet)
const totalBalance = computed(() => {
    if (!wallet.value) return '0.00'
    const total = parseFloat(wallet.value.locked_balance || 0) + parseFloat(wallet.value.withdrawable_balance || 0)
    return total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    return total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
})
const hasDeposited = computed(() => userStore.user?.has_deposited)
const refLink = computed(() => `${window.location.origin}/register?ref=${myRefCode.value}`)

const fetchReferrals = async (isLoadMore = false) => {
    if (loadingReferrals.value) return
    loadingReferrals.value = true
    try {
        const res = await axios.get('/api/referrals.php', {
            params: {
                page: page.value,
                limit: 10 // Load 10 at a time
            }
        })
        
        myRefCode.value = res.data.code
        referralsCount.value = res.data.count

        if (isLoadMore) {
            referrals.value = [...referrals.value, ...res.data.referrals]
        } else {
            referrals.value = res.data.referrals || []
        }
        
        hasMore.value = res.data.has_more
    } catch (e) {
        console.error(e)
    } finally {
        loadingReferrals.value = false
    }
}

const loadMore = () => {
    page.value++
    fetchReferrals(true)
}



function copyCode() {
    navigator.clipboard.writeText(myRefCode.value)
    showToast('Code copied to clipboard!', 'success')
}

function shareWhatsapp() {
    const text = `Join me on Pinnacle! Earn rewards by playing quizzes. Register here: ${refLink.value}`
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank')
}

function shareTelegram() {
    const text = `Join me on Pinnacle! Earn rewards by playing quizzes.`
    window.open(`https://t.me/share/url?url=${encodeURIComponent(refLink.value)}&text=${encodeURIComponent(text)}`, '_blank')
}


const currentSquadLevel = computed(() => {
    if (referrals.value.length < 3) return 1
    // Level 2 Goal: All 3 directs need 3 referrals each
    const qualifiedForLvl2 = referrals.value.filter(r => r.team_count >= 3).length
    if (qualifiedForLvl2 < 3) return 2
    return 3 // Max for current view logic
})

const squadProgress = computed(() => {
    if (referrals.value.length < 3) {
        return (referrals.value.length / 3) * 100
    }
    if (currentSquadLevel.value === 2) {
        const qualified = referrals.value.filter(r => r.team_count >= 3).length
        return (qualified / 3) * 100
    }
    return 100
})

const squadGoalText = computed(() => {
    if (currentSquadLevel.value === 1) return "Fill 3 slots to unlock withdrawals"
    if (currentSquadLevel.value === 2) return "Help your squad fill their 3 slots"
    return "All squad goals completed!"
})

// Tutorial Video Logic (Dashboard)
const showDashboardTutorial = ref(false)
const videoEnded = ref(false)
const videoRef = ref(null)
const remainingTime = ref(0)
const totalDuration = ref(0)
const isPlaying = ref(true)

// Dynamic Settings
const tutorialSettings = ref({
    video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
    title: 'Welcome to Pinnacle',
    desc: 'Watch this quick video to understand how everything works.',
    btn_text: 'Watch Full Video'
})

const siteSettings = ref({
    name: 'Pinnacle',
    logo: ''
})

async function fetchSettings() {
    try {
        const res = await axios.get('/api/getSettings.php')
        if (res.data) {
            siteSettings.value = {
                name: res.data.site_name || 'Pinnacle',
                logo: res.data.site_logo || ''
            }
            
            tutorialSettings.value = {
                video_url: res.data.tutorial_video_url || tutorialSettings.value.video_url,
                title: res.data.tutorial_title || `Welcome to ${siteSettings.value.name}`,
                desc: res.data.tutorial_desc || tutorialSettings.value.desc,
                btn_text: res.data.tutorial_btn_text || tutorialSettings.value.btn_text,
                allow_skip: res.data.allow_skip === 1 || res.data.allow_skip === '1'
            }
        }
    } catch (e) {
        console.error("Failed to load settings", e)
    }
}

function checkDashboardTutorial() {
    // Only show if user HAS deposited
    if (!hasDeposited.value) return

    const key = `tutorial_watched_${userStore.user.id}`
    const hasWatched = localStorage.getItem(key)
    
    if (!hasWatched) {
        showDashboardTutorial.value = true
    }
}

function onVideoEnded() {
    videoEnded.value = true
    remainingTime.value = 0
}

function onTimeUpdate() {
    if (videoRef.value) {
        remainingTime.value = Math.ceil(videoRef.value.duration - videoRef.value.currentTime)
    }
}

function onLoadedMetadata() {
    if (videoRef.value) {
        totalDuration.value = Math.ceil(videoRef.value.duration)
        remainingTime.value = totalDuration.value
    }
}

function togglePlay() {
    if (videoRef.value) {
        if (videoRef.value.paused) {
            videoRef.value.play()
            isPlaying.value = true
        } else {
            videoRef.value.pause()
            isPlaying.value = false
        }
    }
}

function finishDashboardTutorial() {
    // Mark as watched
    const key = `tutorial_watched_${userStore.user.id}`
    localStorage.setItem(key, 'true')
    showDashboardTutorial.value = false
}

// Watch for user load or deposit status change
onMounted(async () => {
    // Parallel fetch settings & user
    await Promise.all([userStore.fetchUser(), fetchSettings()])
    
    fetchReferrals()
    
    // Check after user data is ready
    if(userStore.user) {
        checkDashboardTutorial()
    }
})
</script>

<template>
  <div class="dashboard-wrapper">
    <!-- Tutorial Modal (Unskippable) -->
    <div v-if="showDashboardTutorial" class="tutorial-overlay">
        <div class="tutorial-card">
            <h2 class="tutorial-title">{{ tutorialSettings.title }}</h2>
            <p class="tutorial-desc">{{ tutorialSettings.desc }}</p>
            
            <div class="video-wrapper" @click="togglePlay">
                <video 
                    ref="videoRef"
                    :src="tutorialSettings.video_url" 
                    autoplay 
                    playsinline
                    @ended="onVideoEnded"
                    @timeupdate="onTimeUpdate"
                    @loadedmetadata="onLoadedMetadata"
                    class="main-video"
                    @contextmenu.prevent
                ></video>
                
                <!-- Play/Pause Overlay -->
                <div v-if="!isPlaying" class="absolute inset-0 flex items-center justify-center bg-black/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-white opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <!-- Timer Overlay -->
                <div v-if="!videoEnded" class="absolute top-3 right-3 flex items-center gap-2">
                     <button v-if="tutorialSettings.allow_skip" @click="finishDashboardTutorial" class="bg-white/20 hover:bg-white/30 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold text-white border border-white/10 transition-colors">
                        Skip
                    </button>
                    <div class="bg-black/60 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold text-white border border-white/10">
                        {{ remainingTime }}s remaining
                    </div>
                </div>
            </div>

            <button 
                v-if="videoEnded"
                @click="finishDashboardTutorial" 
                class="btn-gold mt-6 w-full uppercase font-bold py-3 rounded-xl transition-all bg-yellow-500 hover:bg-yellow-400 text-black" 
            >
                Get Started
            </button>
        </div>
    </div>
    <!-- Top Header -->
    <div class="top-header">
      <div class="user-info">
        <div class="avatar-circle">
          <img src="https://img.icons8.com/3d-fluency/94/user-male-circle.png" alt="User" />
        </div>
        <div class="user-text">
          <p class="greeting">Hello, Champion 👋</p>
          <div class="name-row">
            <h2 class="username">{{ user?.name?.split(' ')[0] || 'User' }}</h2>
            <span class="level-badge">LVL 01</span>
          </div>
        </div>
      </div>

      <!-- Center Logo -->
      <div v-if="siteSettings.logo" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50">
          <img :src="siteSettings.logo" class="h-8 max-w-[100px] object-contain" :alt="siteSettings.name" />
      </div>

      <button class="notification-btn">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
      </button>
    </div>

    <!-- Main Content -->
    <div class="content-scroll">
      
      <!-- Total Balance Card -->
      <div class="earning-card">
        <div class="card-glow"></div>
        <div class="relative z-10 w-full">
           <div class="flex justify-between items-start mb-2">
              <span class="text-xs font-bold text-gray-400 tracking-[0.2em] uppercase">Total Assets</span>
              <img src="https://img.icons8.com/color/48/chip.png" class="w-8 opacity-80" />
           </div>
           <h1 class="text-4xl font-black text-white tracking-tighter mb-4">
             <span class="text-yellow-500 text-2xl align-top">₹</span>{{ totalBalance }}
           </h1>
           <div class="flex justify-between items-end border-t border-white/10 pt-4">
              <div class="flex items-center gap-2">
                 <span class="bg-emerald-500/20 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-1">
                   <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                   +12.5%
                 </span>
                 <span class="text-gray-500 text-[10px]">this week</span>
              </div>
              <span class="text-gray-600 font-black text-xs tracking-widest opacity-50">{{ siteSettings.name }}</span>
            </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="grid grid-cols-2 gap-4">
        <router-link to="/deposit" class="group relative overflow-hidden bg-[#111] border border-white/10 rounded-xl p-4 flex flex-col items-center justify-center gap-2 transition-all active:scale-95 hover:bg-[#161616]">
           <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
           <div class="w-10 h-10 bg-green-500/10 rounded-full flex items-center justify-center border border-green-500/20 group-hover:bg-green-500/20 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
              </svg>
           </div>
           <span class="font-bold text-sm text-gray-200">Deposit</span>
        </router-link>

        <router-link to="/withdraw" class="group relative overflow-hidden bg-[#111] border border-white/10 rounded-xl p-4 flex flex-col items-center justify-center gap-2 transition-all active:scale-95 hover:bg-[#161616]">
           <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
           <div class="w-10 h-10 bg-red-500/10 rounded-full flex items-center justify-center border border-red-500/20 group-hover:bg-red-500/20 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
           </div>
           <span class="font-bold text-sm text-gray-200">Withdraw</span>
        </router-link>
      </div>

      <!-- Play Zone -->
      <div class="relative overflow-hidden rounded-[2rem] p-[1px] group cursor-pointer" @click="$router.push('/question')">
        <!-- Animated Border -->
        <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 via-orange-500 to-purple-600 animate-gradient-x opacity-70"></div>
        
        <div class="relative bg-[#111] rounded-[2rem] p-6 flex items-center justify-between h-full overflow-hidden border border-white/5">
            <!-- Background Decoration -->
            <div class="absolute top-0 right-0 w-40 h-40 bg-purple-500/10 rounded-full blur-[50px] -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-32 h-32 bg-yellow-500/5 rounded-full blur-[40px] translate-y-1/2 -translate-x-1/2"></div>
            
            <div class="relative z-10 flex-1">
              <div class="flex items-center gap-2 mb-2">
                 <span class="bg-gradient-to-r from-yellow-500/20 to-orange-500/20 text-yellow-400 text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-wider border border-yellow-500/20">Daily Quest</span>
                 <span class="animate-pulse w-2 h-2 rounded-full bg-green-500"></span>
              </div>
              <h3 class="font-black text-white text-2xl tracking-tighter italic bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent">QUIZ BATTLE</h3>
              <p class="text-[10px] text-gray-400 mt-1 font-medium max-w-[140px] leading-relaxed">
                 Challenge yourself & <span class="text-yellow-500 font-bold">Earn Rewards!</span>
              </p>
            </div>

            <div class="relative z-10 pl-4">
               <button class="relative w-16 h-16 rounded-full bg-gradient-to-br from-yellow-400 via-orange-500 to-red-500 flex items-center justify-center shadow-lg shadow-orange-500/30 group-active:scale-95 transition-all duration-300 border-4 border-[#111]">
                  <div class="absolute inset-0 bg-white/20 rounded-full animate-ping opacity-20"></div>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-black fill-current translate-x-0.5 drop-shadow-sm" viewBox="0 0 24 24">
                     <path d="M8 5v14l11-7z" />
                  </svg>
               </button>
            </div>
        </div>
      </div>




      <!-- Referral Team Section -->
      <div class="referral-section mt-6">
         <!-- Code Card -->
         <div class="glass-card code-section mb-6 relative overflow-hidden group cursor-pointer bg-[#111] border border-yellow-500/20 rounded-2xl p-6" @click="copyCode">
             <div class="absolute top-0 right-0 w-24 h-24 bg-yellow-500/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
             
             <div class="text-center relative z-10">
                 <p class="text-[10px] font-bold text-gray-400 tracking-[0.2em] uppercase mb-4">Your Referral ID</p>
                 
                 <div class="inline-flex items-center gap-4 bg-[#050505] border border-dashed border-white/20 px-6 py-3 rounded-xl hover:border-yellow-500/50 transition-colors group-hover:bg-[#161616]">
                     <span class="text-2xl font-black text-yellow-500 tracking-widest">{{ myRefCode || '...' }}</span>
                     <div class="w-px h-6 bg-white/10"></div>
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                     </svg>
                 </div>
                 
                 <p class="text-[10px] text-gray-500 mt-4 flex items-center justify-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                    <span class="w-1 h-1 rounded-full bg-yellow-500"></span>
                    Tap card to copy code
                 </p>
             </div>
         </div>

         <!-- Member List -->
         <div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-black text-white uppercase tracking-widest flex items-center gap-2 border-l-4 border-yellow-500 pl-3">
                    Team Members
                    <span class="bg-white/10 text-white text-[9px] px-1.5 py-0.5 rounded font-bold">{{ referralsCount }}</span>
                </h3>
                <router-link to="/affiliate-team" class="text-[10px] font-bold text-yellow-500 bg-yellow-500/10 px-3 py-1.5 rounded-full border border-yellow-500/20 hover:bg-yellow-500/20 transition-colors flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                     Tree View
                </router-link>
            </div>
            
            <div v-if="referrals.length === 0" class="text-center py-8 border border-white/5 rounded-2xl bg-[#111]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <p class="text-xs text-gray-500">No members yet.</p>
                <p class="text-[10px] text-gray-600">Share your code to earn!</p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="ref in referrals" :key="ref.created_at + ref.name" class="flex items-center gap-3 p-3 bg-[#111] border border-white/10 rounded-xl hover:bg-[#161616] transition-colors">
                    <div class="w-10 h-10 rounded-full p-[1px] bg-gradient-to-b from-yellow-500 to-orange-600">
                         <img src="https://img.icons8.com/3d-fluency/94/user-male-circle.png" class="w-full h-full rounded-full bg-black/50" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                             <h4 class="font-bold text-sm text-white truncate">{{ ref.name }}</h4>
                             <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-green-500/10 text-green-500 border border-green-500/20">Active</span>
                        </div>
                        <div class="flex justify-between items-center mt-0.5">
                             <p class="text-[10px] text-gray-500 truncate">{{ new Date(ref.created_at).toLocaleDateString() }}</p>
                             <div class="flex items-center gap-1 text-[9px] text-gray-400">
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                 {{ ref.team_count || 0 }} Sub-team
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Load More Button -->
                <div v-if="hasMore" class="text-center pt-2">
                    <button 
                        @click="loadMore" 
                        :disabled="loadingReferrals"
                        class="text-[10px] font-bold text-yellow-500 hover:text-yellow-400 bg-yellow-500/10 hover:bg-yellow-500/20 px-4 py-2 rounded-full transition-all flex items-center gap-2 mx-auto disabled:opacity-50"
                    >
                        <span v-if="loadingReferrals" class="w-3 h-3 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin"></span>
                        {{ loadingReferrals ? 'Loading...' : 'Load More' }}
                    </button>
                </div>
                

            </div>
         </div>
      </div>



    </div>
    <!-- Custom Toast -->
    <transition name="toast-slide">
      <div v-if="toast.show" class="toast-notification">
          <div class="toast-icon">
             <img v-if="toast.type === 'success'" src="https://img.icons8.com/color/48/checked-checkbox.png" class="w-5 h-5"/>
             <img v-else src="https://img.icons8.com/color/48/error.png" class="w-5 h-5"/>
          </div>
          <span class="toast-text">{{ toast.message }}</span>
      </div>
    </transition>
  </div>
</template>

<style scoped>
@reference "../assets/main.css";

/* Page Layout */
.dashboard-wrapper {
    @apply w-full min-h-screen relative overflow-hidden bg-[#050505];
    max-width: 480px;
    margin: 0 auto;
}

/* Header */
.top-header {
    @apply flex justify-between items-center px-5 pt-8 pb-4 sticky top-0 z-30 bg-black/80 backdrop-blur-xl border-b border-white/5;
}

.user-info { @apply flex items-center gap-3; }

.avatar-circle {
    @apply w-12 h-12 rounded-full p-[2px] bg-gradient-to-tr from-yellow-400 to-yellow-600 shadow-lg shadow-yellow-500/20;
}
.avatar-circle img {
    @apply w-full h-full rounded-full object-cover border-2 border-[#111] bg-black;
}

.user-text { @apply flex flex-col justify-center; }
.greeting { @apply text-[10px] font-medium text-gray-400 uppercase tracking-wider; margin: 0; }
.name-row { @apply flex items-center gap-2; }
.username { 
    @apply text-lg font-black text-white m-0 leading-none tracking-tight;
}
.level-badge {
    @apply text-[9px] font-bold bg-yellow-500/10 text-yellow-500 px-1.5 py-0.5 rounded border border-yellow-500/20;
}

.notification-btn {
    @apply w-10 h-10 rounded-full bg-white/5 border border-white/5 flex items-center justify-center backdrop-blur-md active:scale-95 transition-all;
}

/* Content */
.content-scroll {
    @apply px-5 pt-4 pb-24 flex flex-col gap-6;
}

/* Earning Card */
.earning-card {
    @apply relative overflow-hidden rounded-[2rem] bg-[#121212] border border-white/10 shadow-2xl p-6;
    background-image: radial-gradient(circle at top right, rgba(251, 191, 36, 0.05), transparent 40%);
}
.card-glow {
    @apply absolute -top-24 -right-24 w-48 h-48 bg-yellow-500/10 rounded-full blur-[60px];
}

/* Toast */
.toast-notification {
    @apply fixed bottom-24 left-1/2 -translate-x-1/2 bg-[#0f172a]/90 backdrop-blur-xl text-white px-5 py-3 rounded-2xl border border-white/10 shadow-2xl flex items-center gap-3 z-50 min-w-[220px];
}
.toast-slide-enter-active, .toast-slide-leave-active { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translate(-50%, 20px) scale(0.9); }
.toast-text { @apply text-xs font-bold tracking-wide; }

@keyframes gradient-x {
    0%, 100% {
        background-size: 200% 200%;
        background-position: left center;
    }
    50% {
        background-size: 200% 200%;
        background-position: right center;
    }
}
.animate-gradient-x {
    animation: gradient-x 3s ease infinite;
}

/* Tutorial Overlay */
.tutorial-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.95);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(10px);
}
.tutorial-card {
    background: #111;
    border: 1px solid #fbbf24;
    border-radius: 20px;
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 0 50px rgba(251, 191, 36, 0.2);
}
.tutorial-title {
    color: #fbbf24;
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
}
.tutorial-desc {
    color: #9ca3af;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}
.video-wrapper {
    background: black;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255,255,255,0.1);
    aspect-ratio: 16/9;
}
.main-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
