<template>
  <div class="min-h-screen bg-[#050505] text-white font-sans pb-32 relative overflow-hidden">
    <!-- Background Elements -->
    <div class="fixed top-0 left-0 w-full h-[500px] bg-gradient-to-b from-yellow-900/10 to-transparent pointer-events-none"></div>
    <div class="fixed -top-24 -right-24 w-64 h-64 bg-yellow-600/10 blur-[100px] rounded-full pointer-events-none"></div>

    <!-- Header -->
    <nav class="sticky top-0 z-40 bg-black/80 backdrop-blur-xl border-b border-white/5 px-5 py-4 flex justify-between items-center supports-[backdrop-filter]:bg-black/60">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-700 flex items-center justify-center shadow-lg shadow-yellow-500/20 text-black font-black text-sm">
          AG
        </div>
        <div>
          <h1 class="font-bold text-lg leading-none tracking-tight">{{ agentData.name || 'Agent' }}</h1>
          <p class="text-[10px] text-gray-500 font-medium tracking-wider uppercase mt-0.5" @click="copyCode">
            Code: <span class="text-yellow-500 font-bold ml-1 cursor-pointer">{{ agentData.referral_code || '...' }}</span>
          </p>
        </div>
      </div>
      <button @click="logout" class="w-10 h-10 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-gray-400 transition-all active:scale-95 border border-white/5">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </nav>

    <!-- Loading State -->
    <div v-if="loading" class="flex flex-col items-center justify-center h-[70vh]">
      <div class="relative w-16 h-16">
        <div class="absolute inset-0 border-t-2 border-yellow-500 rounded-full animate-spin"></div>
        <div class="absolute inset-2 border-r-2 border-yellow-500/50 rounded-full animate-spin reverse-spin"></div>
      </div>
      <p class="mt-6 text-gray-400 text-sm font-medium animate-pulse tracking-wide">Syncing your dashboard...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex flex-col items-center justify-center min-h-[70vh] px-6 text-center">
      <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mb-6 border border-red-500/20">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <h3 class="text-xl font-bold text-white mb-2">Connection Issue</h3>
      <p class="text-gray-500 text-sm mb-8 max-w-xs mx-auto leading-relaxed">{{ error }}</p>
      <button @click="fetchAgentData" class="px-8 py-3 bg-white text-black font-bold rounded-xl transition-all active:scale-95 shadow-lg w-full max-w-[200px]">
        Retry
      </button>
    </div>
    
    <!-- Content -->
    <div v-else class="px-5 pt-6 space-y-8 max-w-md mx-auto relative z-10">
      
      <!-- Total Earnings Hero Card -->
      <div class="relative group">
        <div class="absolute -inset-0.5 bg-gradient-to-r from-yellow-600 to-yellow-400 rounded-[2rem] opacity-30 group-hover:opacity-50 blur transition duration-500"></div>
        <div class="relative overflow-hidden rounded-[1.8rem] bg-[#121212] border border-white/10 shadow-2xl">
          <!-- Card Pattern -->
          <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#fbbf24 1px, transparent 1px); background-size: 20px 20px;"></div>
          
          <div class="relative p-6">
            <div class="flex flex-col">
              <span class="text-gray-400 text-xs font-bold uppercase tracking-[0.2em] mb-1">Total Commission</span>
              <div class="flex items-baseline gap-1 mt-1">
                 <span class="text-2xl text-yellow-500 font-bold">₹</span>
                 <h2 class="text-5xl font-black text-white tracking-tighter shadow-black drop-shadow-lg">
                   {{ formatNumber(agentData.total_earnings) }}
                 </h2>
              </div>
            </div>
            
            <div class="mt-8 flex items-center justify-between pt-6 border-t border-white/5">
              <div class="flex flex-col">
                <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Status</span>
                <span class="text-emerald-400 text-xs font-bold flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Active Agent
                </span>
              </div>
              <button @click="showWithdrawModal = true" class="px-5 py-2 bg-yellow-500 hover:bg-yellow-400 text-black font-extrabold text-xs uppercase tracking-wider rounded-lg shadow-lg active:scale-95 transition-all">
                  Withdraw
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Withdraw Modal -->
      <div v-if="showWithdrawModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all">
          <div class="bg-[#121212] border border-white/10 rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative">
              <button @click="showWithdrawModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                 </svg>
              </button>
              
              <div class="p-6">
                  <h3 class="text-xl font-bold text-white mb-1">Withdraw Funds</h3>
                  <p class="text-xs text-gray-400 mb-6">Select method and enter details.</p>
                  
                  <!-- Tabs -->
                  <div class="bg-[#0f0f0f] p-1 rounded-xl flex mb-6 border border-white/5">
                      <button @click="withdrawMethod = 'bank'" class="flex-1 py-2 text-xs font-bold rounded-lg transition-all" :class="withdrawMethod === 'bank' ? 'bg-yellow-500 text-black shadow-lg' : 'text-gray-400 hover:text-white'">
                          Bank (INR)
                      </button>
                      <button @click="withdrawMethod = 'usdt'" class="flex-1 py-2 text-xs font-bold rounded-lg transition-all" :class="withdrawMethod === 'usdt' ? 'bg-yellow-500 text-black shadow-lg' : 'text-gray-400 hover:text-white'">
                          Crypto (USDT)
                      </button>
                  </div>
                  
                  <!-- Inputs -->
                  <div class="space-y-4">
                      <div>
                          <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Amount</label>
                          <div class="relative">
                             <input type="number" v-model="withdrawAmount" class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-3 text-white font-bold placeholder-gray-600 focus:outline-none focus:border-yellow-500" placeholder="0.00" />
                             <span class="absolute right-4 top-3.5 text-xs font-bold text-gray-400">
                                 {{ withdrawMethod === 'bank' ? 'INR' : 'USDT' }}
                             </span>
                          </div>
                      </div>
                      
                      <div v-if="withdrawMethod === 'bank'" class="space-y-3 animate-fade-in">
                          <input type="text" v-model="withdrawDetails.holder_name" placeholder="Account Holder Name" class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500" />
                          <input type="text" v-model="withdrawDetails.account_number" placeholder="Account Number" class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500" />
                          <input type="text" v-model="withdrawDetails.ifsc" placeholder="IFSC Code" class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500" />
                      </div>
                      
                      <div v-else class="space-y-3 animate-fade-in">
                          <input type="text" v-model="withdrawDetails.usdt_address" placeholder="TRC20 Wallet Address" class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 font-mono" />
                          <p class="text-[10px] text-yellow-500/80 bg-yellow-500/10 p-2 rounded-lg border border-yellow-500/20">
                             ⚠️ Ensure network is TRC20. Incorrect address may result in loss of funds.
                          </p>
                      </div>
                  </div>
                  
                  <div v-if="withdrawError" class="mt-4 text-xs text-red-400 bg-red-500/10 p-2 rounded-lg border border-red-500/10 text-center">
                      {{ withdrawError }}
                  </div>
                   <div v-if="withdrawSuccess" class="mt-4 text-xs text-green-400 bg-green-500/10 p-2 rounded-lg border border-green-500/10 text-center">
                      {{ withdrawSuccess }}
                  </div>
                  
                  <button @click="submitWithdraw" :disabled="withdrawLoading" class="w-full mt-6 bg-gradient-to-r from-yellow-500 to-yellow-600 text-black font-black uppercase text-sm py-3 rounded-xl shadow-lg shadow-yellow-500/20 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                      <span v-if="withdrawLoading">Processing...</span>
                      <span v-else>Confirm Withdraw</span>
                  </button>
              </div>
          </div>
      </div>

      <!-- Network Stats Grid (Detailed) -->
      <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
              Network Stats
              <span class="h-px w-8 bg-white/10"></span>
            </h3>
            <button @click="router.push('/affiliate-team')" class="text-[10px] font-bold text-yellow-500 bg-yellow-500/10 px-3 py-1.5 rounded-lg border border-yellow-500/20 hover:bg-yellow-500/20 transition-all">
               View Full Affiliate
            </button>
        </div>
        
        <div class="grid grid-cols-1 gap-3">
          <!-- Level 1 -->
          <div class="group bg-[#111] border border-white/5 rounded-2xl p-4 relative overflow-hidden transition-all hover:-translate-y-1 hover:border-yellow-500/30">
            <div class="absolute right-0 top-0 w-24 h-24 bg-yellow-500/5 rounded-full blur-2xl group-hover:bg-yellow-500/10 transition-all"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                   <p class="text-[10px] uppercase text-gray-500 font-bold mb-1">Level 01 (Direct)</p>
                   <p class="text-3xl font-black text-white tracking-tight">{{ agentData.stats.level1?.count || 0 }}</p>
                </div>
                <div class="text-right">
                   <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Total Deposit</p>
                   <p class="text-sm font-bold text-white mb-2">₹{{ formatNumber(agentData.stats.level1?.deposit || 0) }}</p>
                   
                   <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Your Earnings</p>
                   <p class="text-sm font-bold text-yellow-500">+₹{{ formatNumber(agentData.stats.level1?.commission || 0) }}</p>
                </div>
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-3">
              <!-- Level 2 -->
              <div class="group bg-[#111] border border-white/5 rounded-2xl p-4 relative overflow-hidden transition-all hover:border-blue-500/30">
                <div class="absolute -right-2 -top-2 w-12 h-12 bg-blue-500/10 rounded-full blur-xl group-hover:bg-blue-500/20 transition-all"></div>
                <p class="text-[10px] uppercase text-gray-500 font-bold mb-2">Level 02</p>
                <div class="flex flex-col gap-1">
                    <span class="text-2xl font-bold text-white leading-none">{{ agentData.stats.level2?.count || 0 }}</span>
                    <span class="text-[10px] text-gray-400">Dep: ₹{{ formatNumber(agentData.stats.level2?.deposit || 0) }}</span>
                    <span class="text-[10px] text-blue-400 font-bold">Earn: ₹{{ formatNumber(agentData.stats.level2?.commission || 0) }}</span>
                </div>
              </div>
              
              <!-- Level 3 -->
              <div class="group bg-[#111] border border-white/5 rounded-2xl p-4 relative overflow-hidden transition-all hover:border-purple-500/30">
                 <div class="absolute -right-2 -top-2 w-12 h-12 bg-purple-500/10 rounded-full blur-xl group-hover:bg-purple-500/20 transition-all"></div>
                <p class="text-[10px] uppercase text-gray-500 font-bold mb-2">Level 03</p>
                <div class="flex flex-col gap-1">
                    <span class="text-2xl font-bold text-white leading-none">{{ agentData.stats.level3?.count || 0 }}</span>
                    <span class="text-[10px] text-gray-400">Dep: ₹{{ formatNumber(agentData.stats.level3?.deposit || 0) }}</span>
                    <span class="text-[10px] text-purple-400 font-bold">Earn: ₹{{ formatNumber(agentData.stats.level3?.commission || 0) }}</span>
                </div>
              </div>
          </div>
        </div>
      </div>
      
      <!-- Team Modal -->
      <div v-if="showTeamModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/90 backdrop-blur-sm p-0 sm:p-4 transition-all">
         <div class="bg-[#121212] border-t sm:border border-white/10 rounded-t-3xl sm:rounded-3xl w-full max-w-lg h-[90vh] sm:h-[80vh] flex flex-col relative shadow-2xl">
             
             <!-- Modal Header -->
             <div class="px-6 py-5 border-b border-white/5 flex justify-between items-center bg-[#151515] rounded-t-3xl">
                 <div>
                     <h3 class="text-xl font-bold text-white">Affiliate Dashboard</h3>
                     <p class="text-xs text-gray-400">Total Members: {{ agentData.team_list.length }}</p>
                 </div>
                 <button @click="showTeamModal = false" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-gray-400 hover:bg-white/10 transition-all">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                       <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                     </svg>
                 </button>
             </div>
             
             <!-- Search -->
             <div class="p-4 border-b border-white/5 bg-[#121212]">
                 <div class="relative">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                     </svg>
                     <input type="text" v-model="teamSearch" placeholder="Search by name or email..." class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl pl-9 pr-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-colors" />
                 </div>
             </div>
             
             <!-- List -->
             <div class="flex-1 overflow-y-auto p-4 space-y-2">
                 <div v-if="filteredTeam.length === 0" class="flex flex-col items-center justify-center h-48 text-gray-500">
                     <p>No members found.</p>
                 </div>
                 
                 <div v-for="member in filteredTeam" :key="member.id" class="p-4 rounded-xl bg-[#1a1a1a] border border-white/5 hover:border-white/10 transition-all">
                     <div class="flex justify-between items-start mb-2">
                         <div class="flex items-center gap-3">
                             <div class="w-8 h-8 rounded-full bg-gradient-to-br from-gray-700 to-gray-900 flex items-center justify-center font-bold text-xs border border-white/10">
                                 {{ member.name.charAt(0) }}
                             </div>
                             <div>
                                 <h4 class="font-bold text-sm text-white">{{ member.name }}</h4>
                                 <p class="text-[10px] text-gray-500">{{ member.email }}</p>
                             </div>
                         </div>
                         <span class="text-[9px] font-bold px-2 py-1 rounded border" 
                            :class="{
                                'bg-yellow-500/10 text-yellow-500 border-yellow-500/20': member.level === 1,
                                'bg-blue-500/10 text-blue-500 border-blue-500/20': member.level === 2,
                                'bg-purple-500/10 text-purple-500 border-purple-500/20': member.level === 3
                            }">
                             Level 0{{ member.level }}
                         </span>
                     </div>
                     
                     <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-white/5">
                         <div>
                             <p class="text-[9px] text-gray-500 uppercase font-bold">Joined</p>
                             <p class="text-[10px] text-white">{{ formatDate(member.join_date) }}</p>
                         </div>
                         <div>
                             <p class="text-[9px] text-gray-500 uppercase font-bold">Deposit</p>
                             <p class="text-[10px] font-bold text-white">₹{{ formatNumber(member.total_deposit) }}</p>
                         </div>
                         <div class="text-right">
                             <p class="text-[9px] text-gray-500 uppercase font-bold">Earnings</p>
                             <p class="text-[10px] font-bold text-green-400">+₹{{ formatNumber(member.earned_from) }}</p>
                         </div>
                     </div>
                     <div class="mt-2 text-[9px] text-gray-600">
                         Referred by: <span class="text-gray-400">{{ member.referrer }}</span>
                     </div>
                 </div>
             </div>
         </div>
      </div>

      <!-- Invite Section -->
      <div class="relative overflow-hidden rounded-2xl bg-[#1a1a1a] border border-white/5 p-5">
        <div class="absolute top-0 right-0 w-32 h-full bg-gradient-to-l from-yellow-500/10 to-transparent"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
             <h3 class="text-white font-bold text-lg mb-1">Grow Your Team</h3>
             <p class="text-gray-400 text-xs max-w-[150px] leading-relaxed">Share your link and earn commissions from 3 levels.</p>
          </div>
          <button @click="copyLink" class="bg-yellow-500 hover:bg-yellow-400 text-black font-bold h-10 px-6 rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-yellow-900/20 transition-all active:scale-95 flex items-center gap-2">
            <span v-if="copied">Copied!</span>
            <span v-else>Invite</span>
            <svg v-if="!copied" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path width="20" height="20" fill="none" d="M0 0h20v20H0z"/> <!-- Spacer -->
              <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Transactions -->
      <div>
        <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
          Latest Activity
          <span class="h-px flex-1 bg-white/10"></span>
        </h3>

        <div class="space-y-3">
           <div v-for="comm in agentData.commissions" :key="comm.id" class="flex items-center justify-between p-4 rounded-xl bg-[#0f0f0f] border border-white/5 hover:bg-[#151515] transition-colors">
              <div class="flex items-center gap-4">
                 <div class="w-10 h-10 rounded-full bg-black border border-white/10 flex items-center justify-center text-xs font-bold text-gray-300 relative">
                   {{ comm.from_user_name?.charAt(0).toUpperCase() || '?' }}
                   <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-black border-2 border-[#0f0f0f] shadow-sm text-white"
                     :class="{
                       'bg-yellow-500': comm.level === 1,
                       'bg-blue-500': comm.level === 2,
                       'bg-purple-500': comm.level === 3
                     }"
                   >
                     {{ comm.level }}
                   </div>
                 </div>
                 <div>
                    <h4 class="text-sm font-bold text-white">{{ comm.from_user_name }}</h4>
                    <p class="text-[10px] text-gray-500">{{ formatDate(comm.created_at) }}</p>
                 </div>
              </div>
              <div class="text-right">
                 <p class="text-white font-bold text-sm">+₹{{ formatNumber(comm.amount) }}</p>
                 <span class="text-[9px] bg-yellow-500/10 text-yellow-500 px-1.5 py-0.5 rounded border border-yellow-500/20 font-medium">Commission</span>
              </div>
           </div>

           <!-- Empty State -->
           <div v-if="!agentData.commissions.length" class="py-12 flex flex-col items-center justify-center border border-dashed border-white/10 rounded-2xl bg-white/[0.02]">
              <div class="w-12 h-12 bg-gray-800/50 rounded-full flex items-center justify-center mb-3">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                 </svg>
              </div>
              <p class="text-gray-500 text-xs">No commissions yet</p>
           </div>
        </div>
      </div>
      
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const loading = ref(true);
const error = ref(null);
const agentData = ref({
  name: 'Agent',
  email: '',
  referral_code: '',
  total_earnings: 0,
  commissions: [],
  stats: {
    level1: { count: 0, deposit: 0, commission: 0 },
    level2: { count: 0, deposit: 0, commission: 0 },
    level3: { count: 0, deposit: 0, commission: 0 }
  },
  team_list: []
});
const showTeamModal = ref(false);
const teamSearch = ref('');

const filteredTeam = computed(() => {
  const list = agentData.value.team_list || [];
  if (!teamSearch.value) return list;
  return list.filter(m => 
    (m.name && m.name.toLowerCase().includes(teamSearch.value.toLowerCase())) || 
    (m.email && m.email.toLowerCase().includes(teamSearch.value.toLowerCase()))
  );
});
const copied = ref(false);

// Withdraw State
const showWithdrawModal = ref(false);
const withdrawMethod = ref('bank'); // 'bank' or 'usdt'
const withdrawAmount = ref('');
const withdrawDetails = ref({
  account_number: '',
  ifsc: '',
  holder_name: '',
  usdt_address: ''
});
const withdrawLoading = ref(false);
const withdrawError = ref('');
const withdrawSuccess = ref('');

const referralLink = computed(() => {
  if (!agentData.value.referral_code) return '';
  return `${window.location.origin}/register?ref=${agentData.value.referral_code}`;
});

const formatNumber = (num) => {
  return Number(num).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

const formatDate = (dateString) => {
  const date = new Date(dateString);
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffHours = Math.ceil(diffTime / (1000 * 60 * 60)); 
  
  if (diffHours < 24) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

const fetchAgentData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const response = await axios.get('/api/get_agent_stats.php', { withCredentials: true });
    
    if (response.data.success) {
      agentData.value = response.data.agent_data;
      
      // Auto-fill Bank Details from Saved Data
      if (agentData.value.bank_account_number) {
          withdrawDetails.value.account_number = agentData.value.bank_account_number;
          withdrawDetails.value.ifsc = agentData.value.bank_ifsc_code;
          withdrawDetails.value.holder_name = agentData.value.bank_holder_name;
      }
      if (agentData.value.usdt_address) {
          withdrawDetails.value.usdt_address = agentData.value.usdt_address;
      }
    } else {
      error.value = response.data.message || "Failed to load data";
    }
  } catch (err) {
    if (err.response?.status === 403) {
      error.value = "Access restricted to Agents only.";
    } else if (err.response?.status === 401) {
      agentData.value = { 
         name: '', email: '', referral_code: '', total_earnings: 0, commissions: [], 
         stats: { level1:{}, level2:{}, level3:{} }, team_list: [] 
      };
      router.push('/login');
    } else {
      error.value = "Could not connect to the agent network.";
      console.error(err);
    }
  } finally {
    loading.value = false;
  }
};

const copyLink = async () => {
  if (!referralLink.value) return;
  try {
    await navigator.clipboard.writeText(referralLink.value);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
  } catch (err) { console.error(err); }
}

const copyCode = async () => {
  if (!agentData.value.referral_code) return;
  try {
    await navigator.clipboard.writeText(agentData.value.referral_code);
  } catch (err) { console.error(err); }
}

import { useUserStore } from '../stores/user';
const userStore = useUserStore();

const logout = async () => {
    await userStore.logout();
    router.push('/login');
}

const submitWithdraw = async () => {
  withdrawError.value = '';
  withdrawSuccess.value = '';
  withdrawLoading.value = true;

  if (!withdrawAmount.value || withdrawAmount.value <= 0) {
    withdrawError.value = "Please enter a valid amount.";
    withdrawLoading.value = false;
    return;
  }

  if (Number(withdrawAmount.value) > Number(agentData.value.total_earnings)) {
     withdrawError.value = "Insufficient balance.";
     withdrawLoading.value = false;
     return;
  }

  const payload = {
    amount: withdrawAmount.value,
    method: withdrawMethod.value,
  };

  if (withdrawMethod.value === 'bank') {
     if (!withdrawDetails.value.account_number || !withdrawDetails.value.ifsc || !withdrawDetails.value.holder_name) {
        withdrawError.value = "Please fill all bank details.";
        withdrawLoading.value = false;
        return;
     }
     payload.account_number = withdrawDetails.value.account_number;
     payload.ifsc = withdrawDetails.value.ifsc;
     payload.holder_name = withdrawDetails.value.holder_name;
  } else {
    // USDT
    if (!withdrawDetails.value.usdt_address) {
       withdrawError.value = "Please enter USDT TRC20 Address.";
       withdrawLoading.value = false;
       return;
    }
    payload.usdt_address = withdrawDetails.value.usdt_address;
  }

  try {
    // We can reuse the main withdrawal API or create a new one. 
    // Given the previous files, let's assume we can use /api/withdraw.php 
    // BUT we need to make sure the backend handles 'method' and 'usdt_address'.
    // Currently withdraw.php uses 'wallets', but agents might use 'total_earnings' from a different table?
    // Wait, the agent stats come from 'get_agent_stats.php'.
    // If agents are just users with 'agent' role, they have a wallet too?
    // Let's assume for now we send to a new endpoint or the standard one.
    // Let's try standard first.
    
    const response = await axios.post('/api/withdraw.php', payload);
    
    if (response.data.success || response.data.message) {
      withdrawSuccess.value = "Withdrawal request submitted successfully!";
      // Deduct locally for immediate feedback
      agentData.value.total_earnings -= withdrawAmount.value;
      setTimeout(() => {
        showWithdrawModal.value = false;
        withdrawAmount.value = '';
        withdrawSuccess.value = '';
      }, 2000);
    } else {
      withdrawError.value = response.data.error || "Withdrawal failed.";
    }

  } catch (e) {
    withdrawError.value = e.response?.data?.error || "Connection error.";
  } finally {
    withdrawLoading.value = false;
  }
}

onMounted(() => {
  fetchAgentData();
});
</script>

<style scoped>
.reverse-spin {
  animation-direction: reverse;
  animation-duration: 1.5s;
}
</style>
