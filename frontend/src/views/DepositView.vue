<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

import { useUserStore } from '../stores/user'

const userStore = useUserStore()
const loading = ref(false)
const message = ref('')
const error = ref('')
const router = useRouter()
const amount = ref(100)
const isAlreadyDeposited = ref(false)

async function initialize() {
    loading.value = true
    await userStore.fetchUser()
    if (userStore.user) {
        amount.value = userStore.user.next_deposit_required || 100
        
        // Check if current locked balance is already >= required amount
        const lockedVal = parseFloat(userStore.wallet?.locked_balance || 0)
        if (lockedVal >= amount.value) {
            isAlreadyDeposited.value = true
        }
    }
    loading.value = false
}

initialize()

async function makeDeposit() {
    loading.value = true
    message.value = ''
    error.value = ''
    try {
        const res = await axios.post('/api/deposit.php')
        if (res.data.success) {
            message.value = res.data.message
            // Pre-fetch user to get new balance and level?
            // Actually level only updates on withdraw.
            await userStore.fetchUser()
            setTimeout(() => router.push('/dashboard'), 2000)
        } else {
            error.value = res.data.error || 'Deposit failed'
        }
    } catch(e) {
        error.value = e.response?.data?.error || 'Failed to process deposit'
    } finally {
        loading.value = false
    }
}
</script>

<template>
  <div class="page-wrapper">
      <h2 class="page-title text-gold-gradient">Add Funds</h2>
      
      <div class="glass-card deposit-card">
          <div class="icon-container">
               <img src="https://img.icons8.com/3d-fluency/94/wallet.png" width="64" />
          </div>
          <p class="label">Deposit Amount</p>
           <p class="amount">₹{{ amount }}</p>
          <p class="info-text">Activates your account for withdrawals</p>
      </div>
      
      <button @click="makeDeposit" :disabled="loading || isAlreadyDeposited" class="btn-action">
          <span v-if="loading">Processing...</span>
          <span v-else-if="isAlreadyDeposited">FUNDS ALREADY ADDED</span>
          <span v-else>PAY ₹{{ amount }} NOW</span>
      </button>
      
       <div v-if="isAlreadyDeposited" class="glass-card mt-4 p-4 border-yellow-500/20 bg-yellow-500/10">
           <p class="text-yellow-400 text-sm font-bold">⚠️ You already have the required funds in your wallet. You can proceed to play the quiz.</p>
           <button @click="router.push('/dashboard')" class="mt-2 text-xs underline text-white">Go to Dashboard</button>
       </div>

       <div v-if="message" class="glass-card message success">{{ message }}</div>
       <div v-if="error" class="glass-card message error">{{ error }}</div>
  </div>
</template>

<style scoped>
@reference "../assets/main.css";

.page-wrapper {
    padding: 1rem;
    padding-bottom: 90px;
    max-width: 480px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
    text-align: center;
    @apply w-full min-h-screen bg-[#050505] text-white;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
    background: linear-gradient(to right, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase; 
    letter-spacing: 1px;
}

.deposit-card {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.icon-container {
    margin-bottom: 1rem;
    filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.3));
}
.label {
    color: #94a3b8;
    font-size: 0.9rem;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.amount {
    font-size: 3.5rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 0 30px rgba(255,255,255,0.2);
    margin-bottom: 0.5rem;
}
.info-text {
    color: #fbbf24;
    font-size: 0.8rem;
    background: rgba(251, 191, 36, 0.1);
    padding: 0.4rem 1rem;
    border-radius: 20px;
    border: 1px solid rgba(251, 191, 36, 0.2);
}

.btn-action {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    font-size: 1.1rem;
    font-weight: 800;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
    transition: transform 0.2s;
    margin-top: 1.5rem;
}
.btn-action:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }
.btn-action:disabled { background: #1f2937; box-shadow: none; cursor: not-allowed; color: #64748b; }

.message { margin-top: 1.5rem; padding: 1rem; font-weight: 600; background: #111; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; }
.success { color: #4ade80; border-color: rgba(74, 222, 128, 0.3); background: rgba(74, 222, 128, 0.1); }
.error { color: #f87171; border-color: rgba(248, 113, 113, 0.3); background: rgba(248, 113, 113, 0.1); }
</style>
