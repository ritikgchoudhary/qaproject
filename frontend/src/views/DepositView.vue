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
const mustWithdrawFirst = ref(false)
const selectedChannel = ref('')
const paymentUrl = ref('')
const showManualRedirect = ref(false)
const channelErrors = ref({})
const channelStatus = ref({}) // 'idle', 'loading', 'success', 'error'
const channels = [
    { id: 'WATCHPAY_1', name: 'WatchPay Channel 1', icon: '💳' },
    { id: 'WATCHPAY_2', name: 'WatchPay Channel 2', icon: '💳' },
    { id: 'WATCHPAY_3', name: 'WatchPay Channel 3', icon: '💳' },
    { id: 'WATCHPAY_4', name: 'WatchPay Channel 4', icon: '💳' }
]
const depositHistory = ref([])
const loadingHistory = ref(false)
const showDisputeModal = ref(false)
const selectedDeposit = ref(null)
const disputeScreenshot = ref(null)
const disputeMessage = ref('')
const submittingDispute = ref(false)

async function initialize() {
    loading.value = true
    await userStore.fetchUser()
    if (userStore.user) {
        amount.value = userStore.user.next_deposit_required || 100
        
        // Check if current balance is already >= required amount
        const withdrawableVal = parseFloat(userStore.wallet?.withdrawable_balance || 0)
        const currentLevel = parseInt(userStore.user.current_level || userStore.user.level || 1, 10)
        
        // FEATURE 1: Block deposit if previous level funds are sufficient
        if (currentLevel > 1 && withdrawableVal >= amount.value) {
            mustWithdrawFirst.value = true
        }
        if (withdrawableVal >= amount.value) {
            isAlreadyDeposited.value = true
        }
    }
    loading.value = false
    await fetchDepositHistory()
}

async function fetchDepositHistory() {
    loadingHistory.value = true
    try {
        const res = await axios.get('/api/getDepositHistory.php')
        if (res.data.deposits) {
            depositHistory.value = res.data.deposits
        }
    } catch (e) {
        console.error('Error fetching deposit history:', e)
    }
    loadingHistory.value = false
}

function openDisputeModal(deposit) {
    selectedDeposit.value = deposit
    disputeScreenshot.value = null
    disputeMessage.value = ''
    showDisputeModal.value = true
}

function closeDisputeModal() {
    showDisputeModal.value = false
    selectedDeposit.value = null
    disputeScreenshot.value = null
    disputeMessage.value = ''
}

function handleScreenshotSelect(event) {
    const file = event.target.files[0]
    if (file) {
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            error.value = 'Screenshot size should be less than 5MB'
            return
        }
        disputeScreenshot.value = file
    }
}

async function submitDispute() {
    if (!selectedDeposit.value) return
    
    submittingDispute.value = true
    error.value = ''
    message.value = ''
    
    try {
        const formData = new FormData()
        formData.append('deposit_id', selectedDeposit.value.id)
        formData.append('message', disputeMessage.value)
        if (disputeScreenshot.value) {
            formData.append('screenshot', disputeScreenshot.value)
        }
        
        const res = await axios.post('/api/submitPaymentDispute.php', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        
        if (res.data.success) {
            message.value = res.data.message || 'Dispute submitted successfully!'
            closeDisputeModal()
            await fetchDepositHistory() // Refresh history
        } else {
            error.value = res.data.error || 'Failed to submit dispute'
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Network error. Please try again.'
    }
    
    submittingDispute.value = false
}

function formatDate(dateString) {
    const date = new Date(dateString)
    return date.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

initialize()

async function tryChannel(channel) {
    try {
        console.log('Trying channel:', channel)
        const res = await axios.post('/api/deposit.php', {
            channel: channel
        }, {
            timeout: 10000
        })
        
        console.log('API Response:', res.data)
        
        if (res.data.success && res.data.payment_url) {
            console.log('Payment URL received:', res.data.payment_url)
            return {
                success: true,
                payment_url: res.data.payment_url,
                channel: channel
            }
        } else {
            const errorMsg = res.data.error || 'Payment gateway error'
            console.error('API Error:', errorMsg)
            return {
                success: false,
                error: errorMsg,
                channel: channel
            }
        }
    } catch(e) {
        const errorMsg = e.response?.data?.error || e.message || 'Network error'
        console.error('Network Error:', errorMsg, e)
        return {
            success: false,
            error: errorMsg,
            channel: channel
        }
    }
}

async function tryChannelById(channelId) {
    // Initialize channel status
    channelStatus.value[channelId] = 'loading'
    channelErrors.value[channelId] = ''
    
    // All channels use WATCHPAY gateway
    const result = await tryChannel('WATCHPAY')
    
    if (result.success) {
        channelStatus.value[channelId] = 'success'
        return result
    } else {
        channelStatus.value[channelId] = 'error'
        channelErrors.value[channelId] = result.error
        return null
    }
}

async function makeDeposit() {
    if (loading.value) return // Prevent double clicks
    loading.value = true
    message.value = ''
    error.value = ''
    showManualRedirect.value = false
    paymentUrl.value = ''
    
    // Reset all channel statuses
    channels.forEach(ch => {
        channelStatus.value[ch.id] = 'idle'
        channelErrors.value[ch.id] = ''
    })
    
    // Try channels one by one
    let successResult = null
    for (const channel of channels) {
        const result = await tryChannelById(channel.id)
        if (result && result.success) {
            successResult = result
            selectedChannel.value = channel.id
            break
        }
    }
    
    if (successResult) {
        // Success with one of the channels
        console.log('Setting payment URL:', successResult.payment_url)
        paymentUrl.value = successResult.payment_url
        
        // Always show manual button immediately
        showManualRedirect.value = true
        
        // Direct GET redirect with parameters - open in new tab
        message.value = `Opening payment gateway...`
        
        // Try to open in new tab immediately
        try {
            console.log('Attempting to open:', successResult.payment_url)
            const newWindow = window.open(successResult.payment_url, '_blank', 'noopener,noreferrer')
            
            // Wait a bit to check if window was blocked
            setTimeout(() => {
                if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                    // Popup blocked
                    console.warn('Popup blocked or failed to open')
                    message.value = 'Popup blocked. Please click the button below to open payment gateway.'
                } else {
                    // Successfully opened
                    console.log('Payment gateway opened successfully')
                    message.value = 'Payment gateway opened in new tab. Complete payment there.'
                }
            }, 500)
        } catch (e) {
            console.error('Error opening window:', e)
            message.value = 'Please click the button below to open payment gateway.'
        }
        
        loading.value = false
        return
    }
    
    // All channels failed
    error.value = 'All payment channels failed. Please try again later or contact support.'
    message.value = ''
    loading.value = false
}

async function retryChannel(channelId) {
    if (loading.value) return
    
    loading.value = true
    message.value = ''
    error.value = ''
    showManualRedirect.value = false
    paymentUrl.value = ''
    
    const result = await tryChannelById(channelId)
    
    if (result && result.success) {
        selectedChannel.value = channelId
        paymentUrl.value = result.payment_url
        showManualRedirect.value = true
        message.value = 'Payment gateway ready. Click the button below to open.'
        
        // Try to open in new tab
        try {
            const newWindow = window.open(result.payment_url, '_blank', 'noopener,noreferrer')
            setTimeout(() => {
                if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                    message.value = 'Popup blocked. Please click the button below to open payment gateway.'
                } else {
                    message.value = 'Payment gateway opened in new tab. Complete payment there.'
                }
            }, 500)
        } catch (e) {
            message.value = 'Please click the button below to open payment gateway.'
        }
    } else {
        error.value = channelErrors.value[channelId] || 'Channel failed. Please try another channel.'
    }
    
    loading.value = false
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
      
      <!-- Multiple Payment Channels -->
      <div class="glass-card channel-selection">
          <p class="label">Payment Method</p>
          <p class="text-xs text-gray-400 mb-3">Select a payment channel. If one fails, try another.</p>
          <div class="channel-options">
              <div 
                  v-for="channel in channels" 
                  :key="channel.id"
                  class="channel-item-wrapper"
              >
                  <button 
                      :class="['channel-btn', {
                          active: selectedChannel === channel.id,
                          error: channelStatus[channel.id] === 'error',
                          success: channelStatus[channel.id] === 'success',
                          loading: channelStatus[channel.id] === 'loading'
                      }]"
                      :disabled="loading || isAlreadyDeposited || mustWithdrawFirst || channelStatus[channel.id] === 'loading'"
                      @click="retryChannel(channel.id)"
                  >
                      <span class="channel-icon">{{ channel.icon }}</span>
                      <span class="channel-name">{{ channel.name }}</span>
                      <span class="channel-desc">Secure Payment Gateway</span>
                      
                      <!-- Status indicators -->
                      <span v-if="channelStatus[channel.id] === 'loading'" class="channel-status loading-spinner">⏳</span>
                      <span v-else-if="channelStatus[channel.id] === 'success'" class="channel-status">✅</span>
                      <span v-else-if="channelStatus[channel.id] === 'error'" class="channel-status">❌</span>
                  </button>
                  
                  <!-- Error message for this channel -->
                  <div v-if="channelErrors[channel.id]" class="channel-error-message">
                      <p class="error-text">{{ channelErrors[channel.id] }}</p>
                      <button 
                          @click="retryChannel(channel.id)"
                          class="retry-btn"
                          :disabled="loading"
                      >
                          🔄 Retry
                      </button>
                  </div>
              </div>
          </div>
      </div>
      
      <button 
          @click.prevent="makeDeposit" 
          :disabled="loading || isAlreadyDeposited || mustWithdrawFirst" 
          class="btn-action"
          type="button"
      >
          <span v-if="loading">Processing...</span>
          <span v-else-if="mustWithdrawFirst">WITHDRAW FIRST</span>
          <span v-else-if="isAlreadyDeposited">FUNDS ALREADY ADDED</span>
          <span v-else>PAY ₹{{ amount }} NOW</span>
      </button>
      
       <div v-if="mustWithdrawFirst" class="glass-card mt-4 p-4 border-yellow-500/20 bg-yellow-500/10">
           <p class="text-yellow-400 text-sm font-bold">⚠️ Please withdraw your previous level funds to unlock the next level deposit.</p>
           <button @click="router.push('/withdraw')" class="mt-2 text-xs underline text-white">Go to Withdraw</button>
       </div>

       <div v-if="isAlreadyDeposited" class="glass-card mt-4 p-4 border-yellow-500/20 bg-yellow-500/10">
           <p class="text-yellow-400 text-sm font-bold">⚠️ You already have the required funds in your wallet. You can proceed to play the quiz.</p>
           <button @click="router.push('/dashboard')" class="mt-2 text-xs underline text-white">Go to Dashboard</button>
       </div>

       <div v-if="message" class="glass-card message success">{{ message }}</div>
       <div v-if="error" class="glass-card message error">{{ error }}</div>
       
       <!-- Manual Redirect Button (Always shown when payment URL is ready) -->
       <div v-if="showManualRedirect && paymentUrl" class="glass-card mt-4 p-4 border-blue-500/20 bg-blue-500/10">
           <p class="text-blue-400 text-sm font-bold mb-3">💳 Click below to open payment gateway:</p>
           <a 
               :href="paymentUrl" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="btn-action" 
               style="text-decoration: none; display: block; text-align: center; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); cursor: pointer;"
           >
               🔗 OPEN PAYMENT GATEWAY
           </a>
       </div>
       
       <!-- Hidden form for direct submission (backup) -->
       <form v-if="paymentUrl" :action="paymentUrl" method="GET" ref="paymentForm" style="display: none;">
       </form>

       <!-- Deposit History Section -->
       <div class="mt-8">
           <h3 class="text-lg font-bold text-white mb-4 text-left">Deposit History</h3>
           
           <div v-if="loadingHistory" class="glass-card p-4 text-center">
               <p class="text-gray-400">Loading history...</p>
           </div>
           
           <div v-else-if="depositHistory.length === 0" class="glass-card p-4 text-center">
               <p class="text-gray-400">No deposit history found</p>
           </div>
           
           <div v-else class="space-y-3">
               <div 
                   v-for="deposit in depositHistory" 
                   :key="deposit.id"
                   class="glass-card deposit-history-item"
               >
                   <div class="flex justify-between items-start mb-2">
                       <div class="text-left">
                           <p class="text-white font-bold text-lg">₹{{ deposit.amount }}</p>
                           <p class="text-gray-400 text-xs mt-1">{{ formatDate(deposit.created_at) }}</p>
                           <p v-if="deposit.order_id" class="text-gray-500 text-xs mt-1">Order: {{ deposit.order_id }}</p>
                       </div>
                       <div class="text-right">
                           <span 
                               :class="{
                                   'status-badge': true,
                                   'status-success': deposit.status === 'success',
                                   'status-pending': deposit.status === 'pending' && !deposit.has_dispute,
                                   'status-failed': deposit.status === 'failed',
                                   'status-review': deposit.has_dispute
                               }"
                           >
                               <span v-if="deposit.has_dispute">
                                   🔍 Under Review
                               </span>
                               <span v-else>
                                   {{ deposit.status === 'success' ? '✅ Success' : deposit.status === 'pending' ? '⏳ Pending' : '❌ Failed' }}
                               </span>
                           </span>
                       </div>
                   </div>
                   
                   <div v-if="deposit.has_dispute" class="mt-3 pt-3 border-t border-white/10">
                       <div class="bg-blue-500/10 border border-blue-500/20 rounded-lg p-3">
                           <p class="text-blue-400 text-sm font-bold">📸 Dispute Submitted</p>
                           <p class="text-xs text-gray-400 mt-1">Your payment issue is under review. Admin will check and update you soon.</p>
                           <p class="text-xs text-gray-500 mt-1">Status: {{ deposit.dispute_status === 'pending' ? 'Pending Review' : deposit.dispute_status === 'reviewed' ? 'Under Review' : 'Resolved' }}</p>
                       </div>
                   </div>
                   
                   <div v-else-if="deposit.can_report" class="mt-3 pt-3 border-t border-white/10">
                       <button 
                           @click="openDisputeModal(deposit)"
                           class="btn-dispute"
                       >
                           📸 Paid But Not Received
                       </button>
                       <p class="text-xs text-yellow-400 mt-1">Payment is {{ deposit.hours_old }} hours old</p>
                   </div>
               </div>
           </div>
       </div>

       <!-- Dispute Modal -->
       <div v-if="showDisputeModal" class="modal-overlay" @click.self="closeDisputeModal">
           <div class="modal-content">
               <div class="modal-header">
                   <h3 class="modal-title">Report Payment Issue</h3>
                   <button @click="closeDisputeModal" class="modal-close">×</button>
               </div>
               
               <div class="modal-body">
                   <div class="mb-4">
                       <p class="text-sm text-gray-400 mb-2">Deposit Details:</p>
                       <p class="text-white font-bold">₹{{ selectedDeposit?.amount }}</p>
                       <p class="text-gray-400 text-xs">{{ selectedDeposit ? formatDate(selectedDeposit.created_at) : '' }}</p>
                   </div>
                   
                   <div class="mb-4">
                       <label class="block text-sm font-bold text-white mb-2">
                           Upload Payment Screenshot *
                       </label>
                       <input 
                           type="file" 
                           accept="image/*" 
                           @change="handleScreenshotSelect"
                           class="file-input"
                       />
                       <p class="text-xs text-gray-400 mt-1">Max 5MB. Formats: JPG, PNG, GIF, WEBP</p>
                       <p v-if="disputeScreenshot" class="text-xs text-green-400 mt-1">
                           ✓ Selected: {{ disputeScreenshot.name }}
                       </p>
                   </div>
                   
                   <div class="mb-4">
                       <label class="block text-sm font-bold text-white mb-2">
                           Additional Message (Optional)
                       </label>
                       <textarea 
                           v-model="disputeMessage"
                           rows="3"
                           class="textarea-input"
                           placeholder="Describe the payment issue..."
                       ></textarea>
                   </div>
                   
                   <div class="flex gap-3">
                       <button 
                           @click="closeDisputeModal"
                           class="btn-cancel"
                           :disabled="submittingDispute"
                       >
                           Cancel
                       </button>
                       <button 
                           @click="submitDispute"
                           class="btn-submit"
                           :disabled="submittingDispute || !disputeScreenshot"
                       >
                           <span v-if="submittingDispute">Submitting...</span>
                           <span v-else>Submit</span>
                       </button>
                   </div>
               </div>
           </div>
       </div>
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

.channel-selection {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
}

.channel-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
}

.channel-item-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.channel-btn {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 0.5rem;
    background: #1a1a1a;
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    color: white;
}

.channel-btn:hover:not(:disabled) {
    border-color: rgba(251, 191, 36, 0.5);
    background: rgba(251, 191, 36, 0.1);
    transform: translateY(-2px);
}

.channel-btn.active {
    border-color: #fbbf24;
    background: rgba(251, 191, 36, 0.15);
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
}

.channel-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.channel-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.channel-name {
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.channel-desc {
    font-size: 0.7rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.channel-info {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    font-style: italic;
}

.channel-btn.error {
    border-color: rgba(239, 68, 68, 0.5) !important;
    background: rgba(239, 68, 68, 0.1) !important;
}

.channel-btn.success {
    border-color: rgba(74, 222, 128, 0.5) !important;
    background: rgba(74, 222, 128, 0.1) !important;
}

.channel-status {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 0.9rem;
}

.loading-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.channel-error-message {
    margin-top: 0.25rem;
    padding: 0.5rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 6px;
    font-size: 0.7rem;
}

.retry-btn {
    margin-top: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.4);
    border-radius: 4px;
    color: #ef4444;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.retry-btn:hover:not(:disabled) {
    background: rgba(239, 68, 68, 0.3);
    transform: translateY(-1px);
}

.retry-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.channel-error {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 0.8rem;
    color: #ef4444;
}

.channel-errors {
    margin-top: 1rem;
    padding: 0.75rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 8px;
    text-align: left;
}

.error-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #ef4444;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
}

.error-text {
    font-size: 0.7rem;
    color: #fca5a5;
    margin: 0.25rem 0;
    word-break: break-word;
}

/* Deposit History Styles */
.deposit-history-item {
    padding: 1rem;
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    text-align: left;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.status-success {
    background: rgba(74, 222, 128, 0.2);
    color: #4ade80;
    border: 1px solid rgba(74, 222, 128, 0.3);
}

.status-pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.status-failed {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-review {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.btn-dispute {
    width: 100%;
    padding: 0.75rem;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    font-size: 0.9rem;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s;
}

.btn-dispute:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-content {
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: white;
}

.modal-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 2rem;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.modal-close:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.modal-body {
    padding: 1.5rem;
}

.file-input {
    width: 100%;
    padding: 0.75rem;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: white;
    font-size: 0.9rem;
}

.file-input::file-selector-button {
    padding: 0.5rem 1rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-right: 1rem;
    font-weight: 600;
}

.textarea-input {
    width: 100%;
    padding: 0.75rem;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: white;
    font-size: 0.9rem;
    font-family: inherit;
    resize: vertical;
}

.textarea-input:focus {
    outline: none;
    border-color: #3b82f6;
}

.btn-cancel, .btn-submit {
    flex: 1;
    padding: 0.75rem;
    font-size: 0.9rem;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: #374151;
    color: white;
}

.btn-cancel:hover:not(:disabled) {
    background: #4b5563;
}

.btn-submit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.btn-submit:disabled, .btn-cancel:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
