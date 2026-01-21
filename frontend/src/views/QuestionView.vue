<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'

const userStore = useUserStore()
const router = useRouter()
const history = ref([])
const showHistory = ref(false)

// Configure axios for credentials
const api = axios.create({
    baseURL: '/api',
    withCredentials: true
})

const currentQuestion = ref(null)
const selectedAnswer = ref(null)
const score = ref(0)
const loading = ref(true)
const quizFinished = ref(false)
const error = ref('')
const feedbackMessage = ref('')
const feedbackClass = ref('')
const statusMessage = ref('')

const hasDeposited = computed(() => userStore.user?.has_deposited)

async function fetchHistory() {
    try {
        const res = await api.get('/getHistory.php')
        history.value = res.data.history
    } catch (e) {
        console.error('Failed to fetch history', e)
    }
}

async function fetchQuestion() {
    loading.value = true
    error.value = ''
    selectedAnswer.value = null
    feedbackMessage.value = ''
    
    // Ensure user data is loaded first to check deposit status
    if (!userStore.user) {
        await userStore.fetchUser()
    }

    if (!hasDeposited.value) {
        loading.value = false
        return // Stop here, template will handle UI
    }
    
    // Also fetch history on load
    fetchHistory()

    try {
const res = await api.get('/getQuestion.php')
        if (res.data && res.data.id) {
            // Fix image URL if necessary
            if (res.data.image_url) {
                if (res.data.image_url.startsWith('/wikipedia')) {
                    res.data.image_url = 'https://upload.wikimedia.org' + res.data.image_url
                } else if (!res.data.image_url.startsWith('http') && !res.data.image_url.startsWith('/api')) {
                    // Fallback only if NOT local api path
                    res.data.image_url = 'https://upload.wikimedia.org' + res.data.image_url
                }
            }
            currentQuestion.value = res.data
            quizFinished.value = false
        } else if (res.data.message) {
            statusMessage.value = res.data.message
            quizFinished.value = true
            currentQuestion.value = null
        } else {
             error.value = 'Failed to load question.'
        }
    } catch (e) {
        console.error(e)
        if (e.response && e.response.status === 401) {
             error.value = 'कृपया जारी रखने के लिए लॉगिन करें।'
        } else {
             error.value = 'नेटवर्क त्रुटि। कृपया बाद में पुनः प्रयास करें।'
        }
    } finally {
        loading.value = false
    }
}

async function selectAnswer(answer) {
    if (selectedAnswer.value) return // Prevent multiple clicks
    selectedAnswer.value = answer
    
    // Submit answer to backend
    try {
        const res = await api.post('/submitQuestion.php', {
            question_id: currentQuestion.value.id,
            answer: answer
        })

        if (res.data.success) {
            feedbackMessage.value = res.data.message
            feedbackClass.value = 'text-green-400'
            score.value += 10 // Visual feedback
            
            // Update Global Wallet Real-time
            await userStore.fetchUser()
            fetchHistory() // Update history list
            
            // ONE QUESTION ONLY RULE:
            // Do NOT fetch next question. Show result.
            setTimeout(() => {
                quizFinished.value = true
                currentQuestion.value = null
            }, 2000)

        } else {
            // Check specifically for insufficient balance
            if (res.data.insufficient_balance) {
                 feedbackMessage.value = res.data.message;
                 feedbackClass.value = 'text-red-400';
                 // Do NOT finish quiz. Allow them to deposit and try again.
                 // Reset selection after delay so they can re-click
                 setTimeout(() => {
                    selectedAnswer.value = null; 
                 }, 1500);
            } else {
                // Wrong answer or other error -> Game Over
                feedbackMessage.value = res.data.message || 'गलत जवाब।';
                feedbackClass.value = 'text-red-400';
                
                // Still update history even on wrong answer
                // Update wallet because balance is deducted
                await userStore.fetchUser();
                fetchHistory();
                
                 // ONE QUESTION ONLY RULE:
                 // Even on loss, game over (One attempt).
                 setTimeout(() => {
                    quizFinished.value = true;
                    currentQuestion.value = null;
                }, 2000);
            }
        }

    } catch (e) {
        feedbackMessage.value = 'Jawab submit karne mein error'
        selectedAnswer.value = null // allow retry
    }
}

function restartQuiz() {
    quizFinished.value = false
    fetchQuestion()
}

onMounted(fetchQuestion)
</script>

<template>
<div class="page-container">
    <div class="header-section">
        <h1 class="page-title">Daily Quiz</h1>
        <div class="score-badge">
            <img src="https://img.icons8.com/3d-fluency/94/coins.png" width="24" />
            <span>Score: {{ score }}</span>
        </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
        <div class="loader"></div>
        <p>Loading Question...</p>
    </div>

    <!-- Deposit Required State -->
    <div v-else-if="!hasDeposited" class="deposit-lock-card glass-card">
        <img src="https://img.icons8.com/3d-fluency/94/lock-2.png" class="lock-icon" />
        <h2>Access Locked</h2>
        <p class="lock-desc">Complete your first deposit to verify your account and unlock daily quizzes.</p>
        <button @click="router.push('/deposit')" class="btn-gold shimmer-btn">
            DEPOSIT NOW
        </button>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-card glass-card">
        <p>{{ error }}</p>
        <button @click="fetchQuestion" class="btn-gold mt-4">Try Again</button>
    </div>

    <!-- Quiz Content -->
    <div v-else-if="currentQuestion" class="quiz-container">
        
        <!-- Question Card -->
        <div class="question-card glass-card">
            <div v-if="currentQuestion.image_url" class="question-image-container">
                <img :src="currentQuestion.image_url" alt="Question Image" class="question-image" />
            </div>
            <h3 class="question-text">{{ currentQuestion.question }}</h3>
            
            <div class="options-grid">
                <button 
                    v-for="answer in currentQuestion.options" 
                    :key="answer"
                    class="option-btn"
                    :class="{
                        'selected': selectedAnswer === answer,
                        'disabled': selectedAnswer
                    }"
                    @click="selectAnswer(answer)"
                >
                    {{ answer }}
                </button>
            </div>
             <p v-if="feedbackMessage" class="feedback-msg" :class="feedbackClass">
                {{ feedbackMessage }}
                <a v-if="feedbackMessage.includes('Deposit')" @click.prevent="router.push('/deposit')" href="#" class="underline ml-2 text-yellow-400">Add Money</a>
             </p>
        </div>
    </div>

    <!-- No More Questions / Finished -->
    <div v-else-if="quizFinished" class="results-card glass-card">
        <img src="https://img.icons8.com/3d-fluency/94/trophy.png" class="result-icon" />
        <h2>All Done!</h2>
        <p class="final-score">{{ statusMessage || "You've completed all available questions." }}</p>
        
        <div v-if="statusMessage && statusMessage.includes('Withdraw')">
             <button @click="router.push('/withdraw')" class="btn-gold mt-4">Go to Withdraw</button>
        </div>
        <div v-else>
             <p class="sub-text">Check back later for more.</p>
             <button @click="fetchQuestion" class="btn-gold mt-4">Refresh</button>
        </div>
    </div>

    <!-- History Section -->
    <div class="history-section mt-6" v-if="hasDeposited">
        <h3 class="text-xl font-bold text-white mb-4">Quiz History</h3>
        <div class="history-list glass-card p-0 overflow-hidden">
            <div v-for="item in history" :key="item.id" class="history-item">
                <div class="history-info">
                   <p class="text-sm text-gray-300 line-clamp-1">{{ item.question }}</p>
                   <p class="text-xs text-gray-500">{{ new Date(item.created_at).toLocaleString() }}</p>
                </div>
                <div class="history-amount" :class="{'text-green-400': item.is_correct, 'text-red-400': !item.is_correct}">
                    {{ item.is_correct ? '+₹' + item.earned_amount : '₹0' }}
                </div>
            </div>
             <div v-if="history.length === 0" class="p-4 text-center text-gray-500">No history found</div>
        </div>
    </div>
</div>
</template>

<style scoped>
@reference "../assets/main.css";

.page-container {
    padding: 1rem;
    padding-bottom: 80px; 
    width: 100%;
    font-family: 'Inter', sans-serif;
    @apply w-full min-h-screen bg-[#050505] text-white;
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
.page-title { 
    font-size: 1.5rem; 
    text-transform: uppercase; 
    letter-spacing: 1px;
    font-weight: 800;
    background: linear-gradient(to right, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.score-badge {
    background: rgba(0,0,0,0.6);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fbbf24;
    font-weight: 700;
}

/* Loading */
.loading-state {
    text-align: center;
    padding: 4rem 0;
    color: #94a3b8;
}
.loader {
    width: 40px; height: 40px;
    border: 4px solid #fbbf24;
    border-bottom-color: transparent;
    border-radius: 50%;
    margin: 0 auto 1rem;
    animation: spin 1s linear infinite;
}

/* Glass Cards Common */
.glass-card {
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
}

/* Deposit Lock Card */
.deposit-lock-card {
    text-align: center;
    padding: 3rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.lock-icon { width: 80px; margin-bottom: 1rem; }
.lock-desc { font-size: 0.95rem; color: #cbd5e1; max-width: 300px; margin: 0 auto 1.5rem; }
.shimmer-btn {
    position: relative;
    overflow: hidden;
    padding: 1rem 2rem;
    width: 100%;
    max-width: 250px;
}
.shimmer-btn::after {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 50%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer { 100% { left: 150%; } }

.error-card {
    padding: 2rem;
    text-align: center;
    color: #f87171;
}

/* Question */
.question-card {
    padding: 2rem;
    text-align: center;
}
.question-image-container {
    margin-bottom: 1.5rem;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.1);
}
.question-image {
    width: 100%;
    max-height: 250px;
    object-fit: cover;
    display: block;
}
.question-text {
    font-size: 1.25rem;
    margin-bottom: 2rem;
    line-height: 1.5;
    color: white;
    font-weight: 700;
}

.options-grid {
    display: grid;
    gap: 1rem;
}

.option-btn {
    background: #161616;
    border: 1px solid rgba(255,255,255,0.1);
    padding: 1rem;
    border-radius: 12px;
    color: #e2e8f0;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    text-align: left;
}
.option-btn:not(.disabled):hover {
    background: #1a1a1a;
    border-color: #fbbf24;
}

.option-btn.selected {
    background: rgba(251, 191, 36, 0.1);
    border-color: #fbbf24;
    color: #fbbf24;
    font-weight: 700;
}

.option-btn.disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.feedback-msg {
    margin-top: 1rem;
    font-weight: 700;
}
.text-green-400 { color: #4ade80; }
.text-red-400 { color: #f87171; }

/* Results */
.results-card {
    text-align: center;
    padding: 3rem 2rem;
}
.result-icon { margin-bottom: 1.5rem; }
.final-score { font-size: 1.2rem; margin: 1rem 0; color: #e2e8f0; }
.sub-text { color: #94a3b8; margin-bottom: 2rem; }
.mt-4 { margin-top: 1.5rem; }

@keyframes spin { 100% { transform: rotate(360deg); } }

/* History Styles */
.history-list {
    background: #111;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; 
}
.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.history-item:last-child { border-bottom: none; }
.history-info { flex: 1; margin-right: 1rem; }
.history-amount { font-weight: 700; font-size: 1.1rem; }
.p-0 { padding: 0 !important; }
.text-gray-300 { color: #cbd5e1; }
.text-gray-500 { color: #64748b; }
.text-xs { font-size: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.text-xl { font-size: 1.25rem; }
.font-bold { font-weight: 700; }
.mt-6 { margin-top: 1.5rem; }
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1; 
    -webkit-box-orient: vertical;  
    overflow: hidden;
}
</style>
