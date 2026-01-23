<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useUserStore } from '../stores/user'

const amount = ref('')
const bankDetails = ref({
    holder_name: '',
    account_number: '',
    ifsc: ''
})
const loading = ref(false)
const message = ref('')
const error = ref('')
const userStore = useUserStore()

// Requirements info
const referralCount = ref(0)
const totalDeposits = ref(0) 
const reqData = ref({ l1_active: 0, l1_required: 3, l2_active: 0, l2_required: 9, current_level: 1, structure_met: false })
const loadingReq = ref(true)

onMounted(async () => {
    await userStore.fetchUser()
    try {
        const reqRes = await axios.get('/api/getWithdrawRequirements.php')
        reqData.value = reqRes.data
        loadingReq.value = false

        // Populate Bank Details if exist
        if (userStore.user) {
             if (userStore.user.bank_holder_name) bankDetails.value.holder_name = userStore.user.bank_holder_name
             if (userStore.user.bank_account_number) bankDetails.value.account_number = userStore.user.bank_account_number
             if (userStore.user.bank_ifsc_code) bankDetails.value.ifsc = userStore.user.bank_ifsc_code
        }
    } catch (e) {
        loadingReq.value = false
    }
})

async function requestWithdraw() {
    if (!amount.value || amount.value <= 0) {
        error.value = "कृपया एक सही राशि दर्ज करें"
        return
    }
    if (parseFloat(amount.value) > parseFloat(userStore.wallet?.withdrawable_balance)) {
        error.value = "अपर्याप्त निकासी योग्य बैलेंस (Insufficient Balance)"
        return
    }
    
    // Validate Bank Details
    if (!bankDetails.value.holder_name || !bankDetails.value.account_number || !bankDetails.value.ifsc) {
        error.value = "कृपया बैंक विवरण भरें (Enter Bank Details)"
        return
    }
    
    loading.value = true
    message.value = ''
    error.value = ''
    try {
        const res = await axios.post('/api/withdraw.php', { 
            amount: amount.value,
            ...bankDetails.value 
        })
        if (res.data.success) {
            message.value = res.data.message
            await userStore.fetchUser() // update balance
            amount.value = ''
        } else {
            error.value = res.data.error || 'Withdrawal failed'
        }
    } catch(e) {
        error.value = e.response?.data?.error || 'Withdrawal failed'
    } finally {
        loading.value = false
    }
}

import { useRouter } from 'vue-router'
const router = useRouter()
function handleLogout() {
    userStore.logout()
    router.push('/login')
}
</script>

<template>
  <div class="withdraw-page">



      <h2 class="page-title text-gold-gradient">वित्तीय निकासी (Withdraw)</h2>
      
      <!-- Balance Cards -->
      <div class="glass-card balance-card">
          <div class="balance-row">
              <div class="bal-item">
                  <span class="label">Withdrawable</span>
                  <span class="value text-green-400">₹{{ parseFloat(userStore.wallet?.withdrawable_balance || 0).toFixed(0) }}</span>
              </div>
              <div class="divider"></div>
              <div class="bal-item">
                  <span class="label">Locked</span>
                  <span class="value text-gold">₹{{ parseFloat(userStore.wallet?.locked_balance || 0).toFixed(0) }}</span>
              </div>
          </div>
      </div>

      <!-- Unlock Requirements -->
      <div class="glass-card requirements-card" v-if="parseFloat(userStore.wallet?.locked_balance) > 0">
          <div class="flex items-center gap-3 mb-4">
             <div class="p-2 bg-yellow-500/10 rounded-lg">
                <img src="https://img.icons8.com/3d-fluency/94/lock.png" width="24" />
             </div>
             <div>
                <h3 class="font-bold text-white text-base">Locked Balance</h3>
                <p class="text-[10px] text-gray-400">Refer friends to unlock funds</p>
             </div>
          </div>
          
          <div class="space-y-4" v-if="!loadingReq">
            <!-- Level 1 -->
            <div class="req-item group">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-bold text-gray-300">Level 1: Direct Referrals (Deposited)</span>
                    <span class="text-[10px] font-bold" :class="reqData.l1_active >= 3 ? 'text-green-400' : 'text-yellow-500'">{{ reqData.l1_active }}/3</span>
                </div>
                <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-yellow-500 to-green-500 rounded-full transition-all duration-500" :style="{width: Math.min((reqData.l1_active/3)*100, 100) + '%'}"></div>
                </div>
            </div>

            <!-- Level 2 (Only if Level is >= 2) -->
            <div class="req-item group" v-if="reqData.current_level >= 2">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-bold text-gray-300">Level 2: Squad Growth (Deposited)</span>
                    <span class="text-[10px] font-bold" :class="reqData.l2_active >= 9 ? 'text-green-400' : 'text-yellow-500'">{{ reqData.l2_active }}/9</span>
                </div>
                <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full transition-all duration-500" :style="{width: Math.min((reqData.l2_active/9)*100, 100) + '%'}"></div>
                </div>
                <p class="text-[9px] text-gray-500 mt-2 italic">At Level 2, your 3 directs must each have 3 active members.</p>
            </div>

            <p v-if="!reqData.structure_met" class="text-[10px] text-red-400 mt-2 bg-red-500/10 p-2 rounded-lg border border-red-500/20 text-center">
                Structure requirement not met for Level {{ reqData.current_level }}.
            </p>
          </div>
          <div v-else class="py-4 text-center">
              <div class="w-4 h-4 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          </div>
      </div>

      <!-- Withdraw Form -->
      <div class="glass-card form-box">
          <h3 class="input-label">Bank Details</h3>
          
          <div class="bank-inputs">
              <input v-model="bankDetails.holder_name" type="text" placeholder="Account Holder Name" class="std-input" />
              <input v-model="bankDetails.account_number" type="text" placeholder="Account Number" class="std-input" />
              <input v-model="bankDetails.ifsc" type="text" placeholder="IFSC Code" class="std-input" />
          </div>

          <label class="input-label mt-4">Withdraw Amount</label>
          <div class="input-wrapper">
              <span class="currency-symbol">₹</span>
              <input v-model="amount" type="number" placeholder="Enter amount" class="amount-input" />
          </div>
          
          <div class="restriction-msg" v-if="activeReferrals < 1">
              ⚠️ You must have at least 1 deposited referral to withdraw.
          </div>

          <button @click="requestWithdraw" :disabled="loading || !reqData.structure_met" class="btn-action">
              {{ loading ? 'Processing...' : (!reqData.structure_met ? 'LOCKED (Matrix Incomplete)' : 'WITHDRAW NOW') }}
          </button>
      </div>

      <div v-if="message" class="glass-card message success">{{ message }}</div>
      <div v-if="error" class="glass-card message error">{{ error }}</div>
  </div>
</template>

<style scoped>
@reference "../assets/main.css";

.withdraw-page {
    padding: 1rem;
    padding-bottom: 90px;
    max-width: 480px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
    @apply w-full min-h-screen bg-[#050505] text-white;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
    text-align: center;
    background: linear-gradient(to right, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase; 
    letter-spacing: 1px;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 0.5rem 0;
}
.logo-area { display: flex; align-items: center; gap: 8px; }
.logo-icon {
    width: 36px;
    height: 36px;
    background: rgba(251, 191, 36, 0.1);
    border-radius: 10px;
    padding: 6px;
    border: 1px solid rgba(251, 191, 36, 0.2);
}
.logo-icon img { width: 100%; height: 100%; object-fit: contain; }
.logo-text {
    font-weight: 900;
    font-size: 1.1rem;
    letter-spacing: 1px;
    color: #fff;
}
.logout-btn {
    width: 36px;
    height: 36px;
    background: #111;
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.logout-btn img { width: 20px; height: 20px; }
.logout-btn:active { transform: scale(0.95); }

.balance-card {
    padding: 1.5rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.balance-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bal-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
}
.divider {
    width: 1px;
    height: 40px;
    background: rgba(255,255,255,0.1);
}
.label {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-bottom: 0.3rem;
    text-transform: uppercase;
    font-weight: 600;
}
.value {
    font-size: 1.5rem;
    font-weight: 800;
}
.text-gold { color: #fbbf24; text-shadow: 0 0 10px rgba(251, 191, 36, 0.3); }
.text-green-400 { color: #4ade80; text-shadow: 0 0 10px rgba(74, 222, 128, 0.3); }

/* Requirements */
.requirements-card {
    padding: 1.2rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    margin-top: 1.5rem;
}
.req-title {
    font-size: 1.1rem;
    color: #fff;
    margin-bottom: 0.3rem;
    font-weight: 700;
}
.req-subtitle {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}
.req-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.req-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.req-info { flex: 1; }
.req-name { display: block; font-size: 0.9rem; font-weight: 600; color: #e2e8f0; margin-bottom: 0.3rem; }
.progress-bar-bg {
    width: 100%;
    height: 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.3rem;
}
.progress-bar-fill {
    height: 100%;
    background: #fbbf24;
    transition: width 0.3s ease;
}
.req-status { font-size: 0.75rem; color: #94a3b8; }
.text-xs { font-size: 0.75rem; }

/* Form */
.form-box {
    padding: 1.5rem;
    margin-top: 2rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
}
.input-label {
    display: block;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
}
.input-wrapper {
    position: relative;
    margin-bottom: 1.5rem;
}
.currency-symbol {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #fbbf24;
    font-weight: bold;
    font-size: 1.2rem;
}
.amount-input {
    width: 100%;
    padding: 1rem;
    padding-left: 2.5rem;
    background: #161616;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    outline: none;
    transition: all 0.2s;
}
.amount-input:focus { 
    border-color: #fbbf24;
    box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
}

.btn-action {
    width: 100%;
    background: #fbbf24;
    color: #000;
    border: none;
    padding: 15px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 1.5rem;
    transition: background-color 0.2s ease;
}
.btn-action:hover { 
    background: #f59e0b; 
}
.btn-action:active {
    background: #d97706;
}
.btn-action:disabled {
    opacity: 0.5;
    background: #334155;
    color: #94a3b8;
    cursor: not-allowed;
}

.message { padding: 1rem; text-align: center; font-weight: 600; background: #111; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; margin-top: 1rem; }
.success { border-color: rgba(74, 222, 128, 0.3); color: #4ade80; background: rgba(74, 222, 128, 0.1); }
.error { border-color: rgba(248, 113, 113, 0.3); color: #f87171; background: rgba(248, 113, 113, 0.1); }
/* Bank Form */
.bank-inputs {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 0.5rem;
}
.std-input {
    width: 100%;
    padding: 1rem;
    background: #161616;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    color: white;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s;
}
.std-input:focus { 
    border-color: #fbbf24; 
    box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
}

.restriction-msg {
    margin: 1rem 0;
    padding: 0.8rem;
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 8px;
    color: #fca5a5;
    font-size: 0.85rem;
    text-align: center;
    line-height: 1.4;
}

.mt-4 { margin-top: 1rem; }
</style>
