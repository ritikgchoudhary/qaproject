<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useUserStore } from '../stores/user'

const userStore = useUserStore()
const transactions = ref([])
const loading = ref(true)

onMounted(async () => {
    await userStore.fetchUser()
    try {
        const res = await axios.get('/api/getTransactions.php')
        transactions.value = res.data.transactions
    } catch(e) {
        console.error(e)
    } finally {
        loading.value = false
    }
})
</script>

<template>
  <div class="page-container">
      <h2 class="page-title text-gold-gradient">My Asset Log</h2>
      
      <!-- Balance Grid -->
      <div class="balance-grid">
          <!-- Locked -->
          <div class="glass-card balance-card">
              <div class="card-icon-bg bg-orange">
                  <img src="https://img.icons8.com/3d-fluency/94/lock.png" class="hd-icon" />
              </div>
              <div class="card-info">
                  <span class="card-label">Locked</span>
                  <span class="card-amount text-orange">₹{{ userStore.wallet?.locked_balance || '0.00' }}</span>
              </div>
          </div>

          <!-- Withdrawable -->
          <div class="glass-card balance-card">
              <div class="card-icon-bg bg-green">
                  <img src="https://img.icons8.com/3d-fluency/94/money-box.png" class="hd-icon" />
              </div>
              <div class="card-info">
                  <span class="card-label">Withdrawable</span>
                  <span class="card-amount text-green">₹{{ userStore.wallet?.withdrawable_balance || '0.00' }}</span>
              </div>
          </div>

          <!-- Total Withdrawn -->
          <div class="glass-card balance-card full-width">
              <div class="card-icon-bg bg-purple">
                  <img src="https://img.icons8.com/3d-fluency/94/cash-in-hand.png" class="hd-icon" />
              </div>
              <div class="card-info">
                  <span class="card-label">Total Withdrawn</span>
                  <span class="card-amount text-purple">₹{{ userStore.wallet?.total_withdrawn || '0.00' }}</span>
              </div>
          </div>
      </div>

      <!-- Actions -->
      <div class="action-grid">
          <router-link to="/deposit" class="glass-card action-item">
              <img src="https://img.icons8.com/3d-fluency/94/money-bag.png" class="action-icon" />
              <span>Deposit</span>
          </router-link>
          <router-link to="/withdraw" class="glass-card action-item">
              <img src="https://img.icons8.com/3d-fluency/94/bank-building.png" class="action-icon" />
              <span>Withdraw</span>
          </router-link>
      </div>

      <!-- History -->
      <div class="history-section">
          <h3 class="section-title">Transaction History</h3>
          
          <div v-if="loading" class="text-center text-gray-400 py-4">Loading history...</div>
          
          <div v-else-if="transactions.length > 0" class="transaction-list">
              <div v-for="t in transactions" :key="t.type + t.id" class="glass-card t-item">
                  <div class="t-left">
                      <div class="t-icon-box" :class="t.type">
                          <span v-if="t.type === 'deposit'">↓</span>
                          <span v-else>↑</span>
                      </div>
                      <div class="t-meta">
                          <strong class="t-type capitalize">{{ t.type }}</strong>
                          <span class="t-date">{{ new Date(t.created_at).toLocaleDateString() }}</span>
                      </div>
                  </div>
                  <div class="t-right">
                      <span class="t-amount" :class="t.type === 'deposit' ? 'text-green' : 'text-red'">
                          {{ t.type === 'deposit' ? '+' : '-' }}₹{{ t.amount }}
                      </span>
                      <span class="t-status" :class="t.status">{{ t.status }}</span>
                  </div>
              </div>
          </div>
          
          <div v-else class="empty-view">
              <img src="https://img.icons8.com/3d-fluency/94/documents.png" class="empty-icon" />
              <p>No transactions yet</p>
          </div>
      </div>
  </div>
</template>

@reference "../assets/main.css";

.page-container {
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
}

/* Balance Grid */
.balance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.balance-card {
    padding: 1rem;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: transform 0.2s;
}
.balance-card:hover { transform: translateY(-2px); }

.full-width { grid-column: span 2; flex-direction: row; text-align: left; padding: 1rem 1.5rem; }
.full-width .card-info { align-items: flex-start; margin-left: 1rem; }

.card-icon-bg {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 0.5rem;
}
.hd-icon { width: 32px; height: 32px; }

.bg-orange { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); }
.bg-green { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
.bg-purple { background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); }

.card-info { display: flex; flex-direction: column; gap: 2px; }
.card-label { font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
.card-amount { font-size: 1.2rem; font-weight: 800; }

.text-orange { color: #fbbf24; }
.text-green { color: #4ade80; }
.text-purple { color: #a78bfa; }
.text-red { color: #f87171; }

/* Actions */
.action-grid {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}
.action-item {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 1rem;
    border-radius: 12px;
    text-decoration: none;
    color: white;
    font-weight: 700;
    transition: all 0.2s;
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.action-item:active { transform: scale(0.98); }
.action-item:hover { background: #161616; border-color: rgba(255,255,255,0.2); }
.action-icon { width: 24px; height: 24px; }

/* History */
.section-title { font-size: 0.9rem; font-weight: 800; color: #e2e8f0; margin-bottom: 1rem; border-left: 4px solid #fbbf24; padding-left: 10px; text-transform: uppercase; letter-spacing: 1px; }

.transaction-list { display: flex; flex-direction: column; gap: 0.8rem; }
.t-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.05); 
    border-radius: 12px;
}
.t-left { display: flex; align-items: center; gap: 12px; }
.t-icon-box {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
}
.t-icon-box.deposit { background: rgba(16, 185, 129, 0.1); color: #4ade80; border: 1px solid rgba(16, 185, 129, 0.2); }
.t-icon-box.withdraw { background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.2); }

.t-meta { display: flex; flex-direction: column; }
.t-type { color: white; font-size: 0.9rem; font-weight: 700; }
.t-date { color: #64748b; font-size: 0.70rem; }
.capitalize { text-transform: capitalize; }

.t-right { display: flex; flex-direction: column; align-items: flex-end; }
.t-amount { font-weight: 700; font-size: 0.95rem; }
.t-status { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; margin-top: 2px; padding: 2px 6px; border-radius: 4px; background: rgba(255,255,255,0.05); }
.t-status.success { color: #4ade80; background: rgba(74, 222, 128, 0.1); }
.t-status.pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.t-status.failed { color: #f87171; background: rgba(248, 113, 113, 0.1); }

.empty-view { text-align: center; padding: 2rem; color: #64748b; border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; margin-top: 1rem; }
.empty-icon { width: 48px; opacity: 0.4; margin-bottom: 1rem; }
