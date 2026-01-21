<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const referrals = ref([])
const myCode = ref('')
const count = ref(0)
const loading = ref(true)

onMounted(async () => {
    try {
        const res = await axios.get('/api/referrals.php')
        referrals.value = res.data.referrals
        myCode.value = res.data.code
        count.value = res.data.count
    } catch(e) {
        // error
    } finally {
        loading.value = false
    }
})

function copyCode() {
    navigator.clipboard.writeText(myCode.value)
    alert('Code Copied!')
}
</script>

<template>
  <div class="page-container">
      <h2 class="page-title text-gold-gradient">My Referral Team</h2>
      
      <!-- Code Card -->
      <div class="glass-card code-section" @click="copyCode">
          <p class="section-label">YOUR INVITE CODE</p>
          <div class="code-box">
              <span class="code-text">{{ myCode || '...' }}</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
          </div>
          <p class="tap-hint">Tap to Copy Code</p>
      </div>

      <!-- Stats -->
      <div class="glass-card stats-row">
          <div class="stat-box">
              <span class="stat-value">{{ count }} / 3</span>
              <span class="stat-label">Filled Slots</span>
          </div>
          <div class="stat-box">
              <span class="stat-value text-green">Active</span>
              <span class="stat-label">Status</span>
          </div>
      </div>

      <!-- List -->
      <div class="list-section">
          <h3 class="list-title">Member History</h3>
          
          <div v-if="loading" class="text-center py-4 text-gray-400">Loading team...</div>
          
          <div v-else-if="referrals.length > 0" class="referral-list">
              <div v-for="ref in referrals" :key="ref.email" class="glass-card ref-item">
                  <div class="ref-icon-box">
                       <img src="https://img.icons8.com/3d-fluency/94/user-male-circle.png" class="ref-avatar" />
                  </div>
                  <div class="ref-details">
                      <strong class="ref-name">{{ ref.name }}</strong>
                      <span class="ref-date">{{ new Date(ref.created_at).toLocaleDateString() }}</span>
                  </div>
                  <div class="ref-status">
                      <span class="badge-active">Active</span>
                  </div>
              </div>
          </div>
          
          <div v-else class="empty-state">
              <svg xmlns="http://www.w3.org/2000/svg" class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
              </svg>
              <p>No referrals yet.</p>
              <p class="text-sm text-gray-500">Share your code to earn rewards!</p>
          </div>
      </div>
  </div>
</template>

<style scoped>
.page-container {
    padding: 1rem;
    padding-bottom: 90px;
    max-width: 480px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
    text-align: center;
}

.code-section {
    padding: 2rem;
    text-align: center;
    margin-bottom: 1.5rem;
    cursor: pointer;
    transition: transform 0.2s;
    border: 1px solid rgba(251, 191, 36, 0.3);
    background: rgba(251, 191, 36, 0.05);
}
.code-section:active { transform: scale(0.98); background: rgba(251, 191, 36, 0.1); }
.section-label { color: #94a3b8; font-size: 0.8rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; }
.code-box {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}
.code-text { font-size: 2.2rem; font-weight: 800; color: #fbbf24; letter-spacing: 2px; text-shadow: 0 0 10px rgba(251, 191, 36, 0.3); }
.copy-icon { width: 28px; height: 28px; opacity: 0.8; }
.tap-hint { color: #64748b; font-size: 0.8rem; }

.stats-row {
    display: flex;
    justify-content: space-around;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.stat-box { display: flex; flex-direction: column; align-items: center; }
.stat-value { font-size: 1.4rem; font-weight: 800; color: white; margin-bottom: 0.2rem; }
.stat-value.text-green { color: #4ade80; }
.stat-label { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; }

.list-title { color: #e2e8f0; font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-left: 4px solid #fbbf24; padding-left: 10px; }

.referral-list { display: flex; flex-direction: column; gap: 0.8rem; }
.ref-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    gap: 1rem;
    transition: transform 0.2s;
}
.ref-item:hover { transform: translateX(5px); border-color: rgba(255,255,255,0.2); }
.ref-avatar { width: 40px; height: 40px; }
.ref-details { flex: 1; display: flex; flex-direction: column; }
.ref-name { color: white; font-size: 0.95rem; }
.ref-date { color: #64748b; font-size: 0.8rem; }
.badge-active { background: rgba(74, 222, 128, 0.1); color: #4ade80; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(74, 222, 128, 0.2); }

.empty-state { text-align: center; padding: 3rem; color: #94a3b8; }
.empty-icon { width: 64px; margin-bottom: 1rem; opacity: 0.5; }
</style>
