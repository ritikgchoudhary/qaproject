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

// Fetch latest amount
if (userStore.user?.next_deposit_required) {
    amount.value = userStore.user.next_deposit_required
} else {
    // Fallback fetch
    userStore.fetchUser().then(() => {
        if (userStore.user?.next_deposit_required) {
            amount.value = userStore.user.next_deposit_required
        }
    })
}

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
      
      <button @click="makeDeposit" :disabled="loading" class="btn-action">
          {{ loading ? 'Processing...' : 'PAY ₹' + amount + ' NOW' }}
      </button>
      
       <div v-if="message" class="glass-card message success">{{ message }}</div>
       <div v-if="error" class="glass-card message error">{{ error }}</div>
  </div>
</template>

<style scoped>
.page-wrapper {
    padding: 1rem;
    padding-bottom: 90px;
    max-width: 480px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
    text-align: center;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
}

.deposit-card {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
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
    font-size: 3rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 0 20px rgba(255,255,255,0.1);
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
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    transition: transform 0.2s;
    margin-top: 1rem;
}
.btn-action:hover:not(:disabled) { transform: translateY(-2px); }
.btn-action:disabled { background: #475569; box-shadow: none; cursor: not-allowed; }

.message { margin-top: 1.5rem; padding: 1rem; font-weight: 600; }
.success { color: #4ade80; border-color: rgba(74, 222, 128, 0.3); }
.error { color: #f87171; border-color: rgba(248, 113, 113, 0.3); }
</style>
