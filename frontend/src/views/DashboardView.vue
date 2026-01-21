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
})
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

onMounted(async () => {
    await userStore.fetchUser()
    fetchReferrals()
})

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
</script>

<template>
  <div class="dashboard-wrapper">
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
              <span class="text-gray-600 font-black text-xs tracking-widest opacity-50">PINNACLE</span>
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
        <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 via-orange-500 to-purple-600 animate-gradient-x opacity-70"></div>
        <div class="relative bg-[#0f172a] rounded-[2rem] p-6 flex items-center justify-between h-full overflow-hidden">
            <!-- Background Decoration -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-yellow-500/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
            
            <div class="relative z-10 flex-1">
              <div class="flex items-center gap-2 mb-1">
                 <span class="bg-yellow-500/20 text-yellow-400 text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-wider border border-yellow-500/20">Daily Quest</span>
              </div>
              <h3 class="font-black text-white text-xl tracking-wide italic">QUIZ BATTLE</h3>
              <p class="text-[10px] text-gray-400 mt-0.5 font-medium max-w-[120px]">Win rewards daily!</p>
            </div>

            <div class="relative z-10">
               <button class="relative w-14 h-14 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow-lg shadow-orange-500/40 group-active:scale-95 transition-all duration-300">
                  <div class="absolute inset-0 bg-white/30 rounded-full animate-ping opacity-20"></div>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-black fill-current translate-x-0.5" viewBox="0 0 24 24">
                     <path d="M8 5v14l11-7z" />
                  </svg>
               </button>
            </div>
        </div>
      </div>




      <!-- Referral Team Section -->
      <div class="referral-section mt-6">
         <!-- Code Card -->
         <div class="glass-card code-section mb-6 group cursor-pointer" @click="copyCode">
             <div class="absolute inset-0 bg-yellow-500/5 group-hover:bg-yellow-500/10 transition-colors"></div>
             <p class="text-[10px] font-bold text-gray-400 tracking-widest uppercase mb-2 relative z-10">Your Invite Code</p>
             <div class="flex items-center justify-center gap-4 relative z-10">
                 <span class="text-3xl font-black text-yellow-500 tracking-widest drop-shadow-lg">{{ myRefCode || '...' }}</span>
                 <button class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-colors">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                     </svg>
                 </button>
             </div>
             <p class="text-[9px] text-gray-500 text-center mt-2 relative z-10">Tap to copy & share</p>
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
    @apply w-full min-h-screen relative overflow-hidden;
    max-width: 480px;
    margin: 0 auto;
}

/* Header */
.top-header {
    @apply flex justify-between items-center px-5 pt-8 pb-4 sticky top-0 z-30 bg-[#0f172a]/80 backdrop-blur-xl border-b border-white/5;
}

.user-info { @apply flex items-center gap-3; }

.avatar-circle {
    @apply w-12 h-12 rounded-full p-[2px] bg-gradient-to-tr from-yellow-400 to-yellow-600 shadow-lg shadow-yellow-500/20;
}
.avatar-circle img {
    @apply w-full h-full rounded-full object-cover border-2 border-[#0f172a] bg-black;
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
</style>
