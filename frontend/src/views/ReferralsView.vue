<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const referrals = ref([])
const myCode = ref('')
const count = ref(0)
const loading = ref(true)
const showQr = ref(false)

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

function getReferralLink() {
    if (!myCode.value) return ''
    return `${window.location.protocol}//${window.location.host}/register?ref=${myCode.value}`
}

function copyCode() {
    navigator.clipboard.writeText(myCode.value)
    alert('Code Copied!')
}

function copyLink() {
    navigator.clipboard.writeText(getReferralLink())
    alert('Link Copied!')
}

function shareLink() {
    if (navigator.share) {
        navigator.share({
            title: 'Join My Team',
            text: 'Use my referral code to join and earn rewards!',
            url: getReferralLink()
        })
    } else {
        copyLink()
    }
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
<img src="https://img.icons8.com/3d-fluency/94/documents.png" width="36" class="copy-icon" />
          </div>
          <p class="tap-hint">Tap to Copy Code</p>
      </div>

      <!-- Share Actions -->
      <div class="share-grid">
          <button @click="shareLink" class="share-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
              <span>Share Link</span>
          </button>
          <button @click="showQr = !showQr" class="share-btn">
              <img src="https://img.icons8.com/3d-fluency/94/qr-code.png" width="32" />
              <span>QR Code</span>
          </button>
      </div>

      <!-- QR Expandable -->
      <div v-if="showQr" class="glass-card qr-section">
          <h3 class="qr-title">Scan to Join</h3>
          <div class="qr-wrapper">
              <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&color=fbbf24&bgcolor=111111&margin=10&data=${encodeURIComponent(getReferralLink())}`" alt="QR Code" class="qr-img" />
          </div>
          <p class="qr-link">{{ getReferralLink() }}</p>
          <button @click="copyLink" class="btn-copy-link">Copy Full Link</button>
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
    text-transform: uppercase; 
    letter-spacing: 1px;
}

.code-section {
    padding: 2rem;
    text-align: center;
    margin-bottom: 1.5rem;
    cursor: pointer;
    transition: transform 0.2s;
    background: #111;
    border: 1px solid rgba(251, 191, 36, 0.2);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.code-section:active { transform: scale(0.98); background: #161616; }
.section-label { color: #94a3b8; font-size: 0.8rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; }
.code-box {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}
.code-text { font-size: 2.2rem; font-weight: 800; color: #fbbf24; letter-spacing: 2px; text-shadow: 0 0 10px rgba(251, 191, 36, 0.3); }
.copy-icon { width: 28px; height: 28px; opacity: 0.8; color: #fbbf24; }
.tap-hint { color: #64748b; font-size: 0.8rem; }

/* Share Grid */
.share-grid {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.share-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 1rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.share-btn:hover { background: #161616; border-color: rgba(255,255,255,0.2); }
.share-btn:active { transform: scale(0.98); }

/* QR Section */
.qr-section {
    padding: 1.5rem;
    text-align: center;
    background: #111;
    border: 1px solid rgba(251, 191, 36, 0.2);
    border-radius: 16px;
    margin-bottom: 1.5rem;
    animation: fadeIn 0.3s ease;
}
.qr-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #fbbf24; }
.qr-wrapper {
    background: #000;
    padding: 10px;
    border-radius: 12px;
    display: inline-block;
    margin-bottom: 1rem;
    border: 1px solid rgba(255,255,255,0.1);
}
.qr-img { width: 140px; height: 140px; border-radius: 4px; }
.qr-link { font-size: 0.75rem; color: #64748b; margin-bottom: 1rem; word-break: break-all; }
.btn-copy-link {
    background: #fbbf24;
    color: #000;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    font-size: 0.9rem;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

.stats-row {
    display: flex;
    justify-content: space-around;
    padding: 1.5rem;
    margin-bottom: 2rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
}
.stat-box { display: flex; flex-direction: column; align-items: center; }
.stat-value { font-size: 1.4rem; font-weight: 800; color: white; margin-bottom: 0.2rem; }
.stat-value.text-green { color: #4ade80; }
.stat-label { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; }

.list-title { color: #e2e8f0; font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-left: 4px solid #fbbf24; padding-left: 10px; text-transform: uppercase; letter-spacing: 1px; }

.referral-list { display: flex; flex-direction: column; gap: 0.8rem; }
.ref-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    gap: 1rem;
    transition: transform 0.2s;
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
}
.ref-item:hover { transform: translateX(5px); background: #161616; border-color: rgba(255,255,255,0.2); }
.ref-avatar { width: 40px; height: 40px; }
.ref-details { flex: 1; display: flex; flex-direction: column; }
.ref-name { color: white; font-size: 0.95rem; font-weight: 700; }
.ref-date { color: #64748b; font-size: 0.8rem; }
.badge-active { background: rgba(74, 222, 128, 0.1); color: #4ade80; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(74, 222, 128, 0.2); }

.empty-state { text-align: center; padding: 3rem; color: #94a3b8; border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; margin-top: 2rem; }
.empty-icon { width: 64px; margin: 0 auto 1rem auto; opacity: 0.5; }
</style>
