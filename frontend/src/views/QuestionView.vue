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
const timeLeft = ref(60)
let timerInterval = null
const isTimingOut = ref(false)
const showInstructions = ref(false)
const isQuizActive = ref(false)
const showCongratulations = ref(false)
const winAmount = ref(0)
const quizProgress = ref(null)

const hasDeposited = computed(() => userStore.user?.has_deposited)

async function fetchHistory() {
    try {
        const res = await api.get('/getHistory.php')
        history.value = res.data.history
    } catch (e) {
        console.error('Failed to fetch history', e)
    }
}

async function fetchQuizProgress() {
    try {
        const res = await api.get('/getQuizProgress.php')
        if (res.data && res.data.levels) {
            quizProgress.value = res.data
            console.log('Quiz progress loaded:', res.data)
        } else {
            console.warn('Quiz progress data invalid:', res.data)
        }
    } catch (e) {
        console.error('Failed to fetch quiz progress', e)
        // Set default empty structure to show locked levels
        quizProgress.value = {
            current_level: 1,
            quiz_level_completed: 0,
            quiz_level: 1,
            levels: [
                { level: 1, status: 'active', stake: 100, win: 200, label: 'Level 1' },
                { level: 2, status: 'locked', stake: 200, win: 400, label: 'Level 2' },
                { level: 3, status: 'locked', stake: 400, win: 800, label: 'Level 3' },
                { level: 4, status: 'locked', stake: 800, win: 1600, label: 'Level 4' },
                { level: 5, status: 'locked', stake: 1600, win: 3200, label: 'Level 5' },
                { level: 6, status: 'locked', stake: 3200, win: 6400, label: 'Level 6' }
            ]
        }
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

    // Fetch quiz progress even if not deposited (to show locked state)
    fetchQuizProgress()
    
    if (!hasDeposited.value) {
        loading.value = false
        return // Stop here, template will handle UI
    }
    
    // Check Tutorial (Unskippable)
    if (!checkTutorialStatus()) {
        loading.value = false
        return
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
        } else if (res.data.deposit_required) {
            // NEW: Level Up Deposit Limit increased
            statusMessage.value = res.data.message
            quizFinished.value = true
            currentQuestion.value = null
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
        if (currentQuestion.value) {
            showInstructions.value = true
        }
    }
}

function closeInstructions() {
    showInstructions.value = false
    isQuizActive.value = false // Stay in "Ready to Start" mode
}

function startActualQuiz() {
    isQuizActive.value = true
    startTimer()
}

function startTimer() {
    stopTimer()
    timeLeft.value = 60
    timerInterval = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--
        } else {
            handleTimeout()
        }
    }, 1000)
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval)
        timerInterval = null
    }
}

async function handleTimeout() {
    if (isTimingOut.value || !currentQuestion.value) return
    isTimingOut.value = true
    stopTimer()
    
    try {
        const res = await api.post('/timeoutPenalty.php', {
            question_id: currentQuestion.value.id
        })
        
        feedbackMessage.value = res.data.message || 'Time up! Penalty deducted.'
        feedbackClass.value = 'text-red-400'
        
        await userStore.fetchUser()
        fetchHistory()
        
        setTimeout(() => {
            quizFinished.value = true
            currentQuestion.value = null
            isTimingOut.value = false
        }, 3000)
        
    } catch (e) {
        console.error("Timeout handle failed", e)
        isTimingOut.value = false
    }
}

async function selectAnswer(answer) {
    if (selectedAnswer.value || timeLeft.value <= 0) return 
    selectedAnswer.value = answer
    stopTimer()
    
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
            
            // Use win_amount from response if available, otherwise extract from message
            if (res.data.win_amount) {
                winAmount.value = parseInt(res.data.win_amount)
            } else {
                // Fallback: Extract win amount from message (format: "Correct! ₹XXX added...")
                const amountMatch = res.data.message.match(/₹(\d+)/)
                if (amountMatch) {
                    winAmount.value = parseInt(amountMatch[1])
                }
            }
            
            // Show congratulations popup
            showCongratulations.value = true
            
            // Update Global Wallet Real-time
            await userStore.fetchUser()
            fetchHistory() // Update history list
            fetchQuizProgress() // Update progress overview
            
            // ONE QUESTION ONLY RULE:
            // Do NOT fetch next question. Show result.
            setTimeout(() => {
                showCongratulations.value = false
                quizFinished.value = true
                currentQuestion.value = null
            }, 3000) // Show popup for 3 seconds

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
                fetchQuizProgress(); // Update progress overview
                
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

// Tutorial Video State
const showTutorial = ref(false)
const videoEnded = ref(false)
const videoRef = ref(null)

// Dynamic Settings
const tutorialSettings = ref({
    video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
    title: 'How It Works',
    desc: 'Watch the full video to unlock your quiz.',
    btn_text: 'WATCH TO CONTINUE'
})

async function fetchSettings() {
    try {
        const res = await axios.get('/api/getSettings.php')
        if (res.data) {
            tutorialSettings.value = {
                video_url: res.data.tutorial_video_url || tutorialSettings.value.video_url,
                title: res.data.tutorial_title || tutorialSettings.value.title,
                desc: res.data.tutorial_desc || tutorialSettings.value.desc,
                btn_text: res.data.tutorial_btn_text || tutorialSettings.value.btn_text,
            }
        }
    } catch (e) {
        console.error("Failed to load settings", e)
    }
}

function checkTutorialStatus() {
    const key = `tutorial_watched_${userStore.user.id}`
    const hasWatched = localStorage.getItem(key)
    
    if (!hasWatched) {
        showTutorial.value = true
        return false // Block access
    }
    return true // Allow access
}

function onVideoEnded() {
    videoEnded.value = true
}

function finishTutorial() {
    const key = `tutorial_watched_${userStore.user.id}`
    localStorage.setItem(key, 'true')
    showTutorial.value = false
    // Resume loading question
    fetchQuestion()
}

onMounted(() => {
    // Fetch quiz progress immediately (always, not just if deposited)
    fetchQuizProgress()
    
    fetchSettings().then(() => {
        // Initial fetch handled by logic inside fetchQuestion
        fetchQuestion()
    })
})
</script>

<template>
<div class="page-container">
    <!-- Tutorial Modal (Unskippable) -->
    <div v-if="showTutorial" class="tutorial-overlay">
        <div class="tutorial-card">
            <h2 class="tutorial-title">{{ tutorialSettings.title }}</h2>
            <p class="tutorial-desc">{{ tutorialSettings.desc }}</p>
            
            <div class="video-wrapper">
                <video 
                    ref="videoRef"
                    :src="tutorialSettings.video_url" 
                    autoplay 
                    playsinline
                    @ended="onVideoEnded"
                    class="main-video"
                    @contextmenu.prevent
                ></video>
                <!-- Cover controls with invisible div if needed, but removing 'controls' attr is enough -->
            </div>

            <button 
                v-if="videoEnded"
                @click="finishTutorial" 
                class="btn-gold mt-6 w-full"
            >
                START PLAYING
            </button>
        </div>
    </div>
    
    <!-- Congratulations Popup -->
    <Transition name="popup">
        <div v-if="showCongratulations" class="congratulations-overlay">
            <div class="congratulations-card">
                <h2 class="congrats-title">🎉 Congratulations! 🎉</h2>
                <p class="congrats-message">Sahi Jawab!</p>
                <div class="win-amount-display">
                    <span class="currency">₹</span>
                    <span class="amount">{{ winAmount }}</span>
                </div>
                <p class="win-description">Aapke wallet mein add ho gaya hai!</p>
                <div class="confetti">
                    <span class="confetti-piece"></span>
                    <span class="confetti-piece"></span>
                    <span class="confetti-piece"></span>
                    <span class="confetti-piece"></span>
                    <span class="confetti-piece"></span>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Instruction Popup -->
    <Transition name="fade">
        <div v-if="showInstructions" class="instruction-overlay">
            <div class="instruction-card glass-card">
                <div class="header-icon">
                    <img src="https://img.icons8.com/3d-fluency/94/scroll.png" width="60" />
                </div>
                <h2 class="text-2xl font-black text-yellow-500 mb-2 italic">WARRIOR RULES</h2>
                <ul class="rule-list">
                    <li><span class="dot"></span> 60s time limit per question.</li>
                    <li><span class="dot"></span> Timeout results in stake deduction.</li>
                    <li><span class="dot"></span> Choose wisely before time runs out.</li>
                </ul>
                <button @click="closeInstructions" class="btn-gold w-full mt-6 py-4 text-base tracking-widest">
                    I UNDERSTAND
                </button>
            </div>
        </div>
    </Transition>

    <!-- Header Section -->
    <div class="header-section">
        <h1 class="page-title">Daily Quiz</h1>
        <div class="score-badge">
            <img src="https://img.icons8.com/3d-fluency/94/coins.png" width="24" />
            <span>Score: {{ score }}</span>
        </div>
    </div>

    <!-- Quiz Progress Overview -->
    <div class="quiz-overview-section">
        <h3 class="overview-title">Quiz Levels Overview</h3>
        <div v-if="quizProgress && quizProgress.levels && quizProgress.levels.length > 0" class="levels-scroll-container">
            <div class="levels-scroll">
                <!-- All Levels in Linear Order -->
                <div 
                    v-for="level in quizProgress.levels" 
                    :key="'level-' + level.level"
                    :class="['level-card-mini', level.status]"
                >
                    <div class="level-icon-mini">
                        <span v-if="level.status === 'completed'">✅</span>
                        <span v-else-if="level.status === 'active'">🎯</span>
                        <span v-else>🔒</span>
                    </div>
                    <div class="level-info-mini">
                        <div class="level-label-mini">{{ level.label }}</div>
                        <div class="level-reward-mini">₹{{ level.stake }}→₹{{ level.win }}</div>
                    </div>
                </div>
                
                <!-- More Indicator -->
                <div v-if="quizProgress.has_more" class="level-card-mini more-indicator">
                    <div class="level-icon-mini">➕</div>
                    <div class="level-info-mini">
                        <div class="level-label-mini">More</div>
                        <div class="level-reward-mini">...</div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="loading-progress">
            <div class="loader-small"></div>
            <p>Loading quiz levels...</p>
        </div>
    </div>

    <!-- Active Timer (Fixed Top when quiz is on) -->
    <Transition name="slide-down">
        <div v-if="isQuizActive" class="top-timer-bar">
            <div class="timer-inner">
                <span class="timer-label">TIME REMAINING</span>
                <div class="timer-digits" :class="{'text-red-500 animate-pulse': timeLeft < 10}">
                    {{ timeLeft }}s
                </div>
            </div>
            <div class="progress-track">
                <div class="progress-fill" :style="{ width: (timeLeft / 60 * 100) + '%' }"></div>
            </div>
        </div>
    </Transition>

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
        
        <!-- Pre-Quiz Start Button -->
        <div v-if="!isQuizActive" class="start-trigger-card glass-card">
            <img src="https://img.icons8.com/3d-fluency/94/brain.png" class="trigger-icon" />
            <div v-if="currentQuestion && currentQuestion.level" class="level-info-display">
                <h2 class="text-2xl font-black text-yellow-500 mb-2">Level {{ currentQuestion.level }} Quiz</h2>
                <div class="reward-info">
                    <div class="stake-amount">
                        <span class="label">Stake:</span>
                        <span class="value">₹{{ currentQuestion.stake || (100 * Math.pow(2, (currentQuestion.level - 1))) }}</span>
                    </div>
                    <div class="arrow">→</div>
                    <div class="win-amount">
                        <span class="label">Win:</span>
                        <span class="value text-yellow-400">₹{{ (currentQuestion.stake || (100 * Math.pow(2, (currentQuestion.level - 1)))) * 2 }}</span>
                    </div>
                </div>
            </div>
            <div v-else>
                <h2 class="text-xl font-black text-white mb-2">READY FOR BATTLE?</h2>
                <p class="text-gray-400 text-sm mb-6">Your today's question is loaded and ready.</p>
            </div>
            <button @click="startActualQuiz" class="btn-gold w-full shimmer-btn mt-4">
                START QUIZ NOW
            </button>
        </div>

        <!-- Question Card -->
        <div v-else class="question-card glass-card">
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
        <h2 v-if="statusMessage?.includes('Deposit')">Deposit Required</h2>
        <h2 v-else>All Done!</h2>
        <p class="final-score">{{ statusMessage || "You've completed all available questions." }}</p>
        
        <div v-if="statusMessage?.includes('Withdraw')">
             <button @click="router.push('/withdraw')" class="btn-gold mt-4">GO TO WITHDRAW</button>
        </div>
        <div v-else-if="statusMessage?.includes('Deposit')">
             <button @click="router.push('/deposit')" class="btn-gold mt-4">GO TO DEPOSIT</button>
        </div>
        <div v-else-if="statusMessage?.includes('matrix')">
             <button @click="router.push('/agent/team')" class="btn-gold mt-4">COMPLETE SQUAD MATRIX</button>
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
@reference "tailwindcss";
.page-container {
    padding: 1rem;
    padding-bottom: 80px; 
    width: 100%;
    min-height: 100vh;
    font-family: 'Inter', sans-serif;
    background-color: #050505;
    color: white;
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
.result-icon { margin: 0 auto 1.5rem auto; display: block; }
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

/* Tutorial Overlay */
.tutorial-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.95);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(10px);
}
.tutorial-card {
    background: #111;
    border: 1px solid #fbbf24;
    border-radius: 20px;
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 0 50px rgba(251, 191, 36, 0.2);
}
.tutorial-title {
    color: #fbbf24;
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
}
.tutorial-desc {
    color: #9ca3af;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}
.video-wrapper {
    background: black;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255,255,255,0.1);
    aspect-ratio: 16/9;
}
.main-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
/* Timer Styles */
.timer-container {
    position: absolute;
    top: -20px;
    right: 20px;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timer-svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}
.timer-bg {
    fill: #111;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 8;
}
.timer-progress {
    fill: none;
    stroke: #fbbf24;
    stroke-width: 8;
    stroke-linecap: round;
    stroke-dasharray: 283; /* 2 * PI * R (45) */
    transition: stroke-dashoffset 1s linear, stroke 0.3s;
}
.timer-text {
    position: absolute;
    font-size: 0.875rem;
    font-weight: 900;
}
/* Start Trigger */
.start-trigger-card {
    padding: 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.trigger-icon { width: 5rem; height: 5rem; margin-bottom: 0.5rem; }

.level-info-display {
    width: 100%;
    margin-bottom: 1rem;
}

.reward-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 12px;
}

.stake-amount, .win-amount {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.stake-amount .label, .win-amount .label {
    font-size: 0.75rem;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stake-amount .value, .win-amount .value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
}

.win-amount .value {
    color: #fbbf24;
    text-shadow: 0 0 10px rgba(251, 191, 36, 0.5);
}

.arrow {
    font-size: 1.5rem;
    color: #fbbf24;
    font-weight: 700;
}

/* Top Timer Bar */
.top-timer-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 60;
    background-color: #050505;
    border-bottom: 1px solid rgba(251, 191, 36, 0.2);
    padding: 0.75rem 1rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 480px;
    margin: 0 auto;
}
.timer-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.timer-label {
    font-size: 10px;
    font-weight: 900;
    color: #6b7280;
    letter-spacing: 0.1em;
}
.timer-digits {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fbbf24;
}
.progress-track {
    width: 100%;
    height: 0.25rem;
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 9999px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background-color: #fbbf24;
    transition: all 1000ms linear;
}

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.4s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); }

/* Congratulations Popup */
.congratulations-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background-color: rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(20px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.congratulations-card {
    position: relative;
    width: 100%;
    max-width: 350px;
    padding: 2.5rem 2rem;
    text-align: center;
    background: linear-gradient(180deg, #1a1a1a 0%, #0a0a0a 100%);
    border: 2px solid #fbbf24;
    border-radius: 24px;
    box-shadow: 0 0 60px rgba(251, 191, 36, 0.4), 0 0 100px rgba(251, 191, 36, 0.2);
    overflow: hidden;
}

.celebration-icon {
    margin-bottom: 1rem;
    animation: bounce 0.6s ease infinite;
}

.party-icon {
    width: 80px;
    height: 80px;
    filter: drop-shadow(0 0 20px rgba(251, 191, 36, 0.6));
}

.congrats-title {
    font-size: 1.75rem;
    font-weight: 900;
    color: #fbbf24;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    text-shadow: 0 0 20px rgba(251, 191, 36, 0.5);
}

.congrats-message {
    font-size: 1.25rem;
    font-weight: 700;
    color: #4ade80;
    margin-bottom: 1.5rem;
}

.win-amount-display {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 0.25rem;
    margin: 1.5rem 0;
    padding: 1.5rem;
    background: rgba(251, 191, 36, 0.1);
    border: 2px solid rgba(251, 191, 36, 0.3);
    border-radius: 16px;
    backdrop-filter: blur(10px);
}

.currency {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fbbf24;
}

.amount {
    font-size: 3rem;
    font-weight: 900;
    color: #fbbf24;
    text-shadow: 0 0 30px rgba(251, 191, 36, 0.6);
    animation: pulse 1.5s ease infinite;
}

.win-description {
    font-size: 0.95rem;
    color: #cbd5e1;
    margin-top: 1rem;
}

.confetti {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    pointer-events: none;
    overflow: hidden;
}

.confetti-piece {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #fbbf24;
    animation: confetti-fall 3s ease infinite;
}

.confetti-piece:nth-child(1) {
    left: 10%;
    animation-delay: 0s;
    background: #fbbf24;
}

.confetti-piece:nth-child(2) {
    left: 30%;
    animation-delay: 0.5s;
    background: #4ade80;
}

.confetti-piece:nth-child(3) {
    left: 50%;
    animation-delay: 1s;
    background: #60a5fa;
}

.confetti-piece:nth-child(4) {
    left: 70%;
    animation-delay: 1.5s;
    background: #f87171;
}

.confetti-piece:nth-child(5) {
    left: 90%;
    animation-delay: 2s;
    background: #a78bfa;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes confetti-fall {
    0% {
        transform: translateY(-100px) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(400px) rotate(720deg);
        opacity: 0;
    }
}

.popup-enter-active, .popup-leave-active {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.popup-enter-from {
    opacity: 0;
    transform: scale(0.8) translateY(20px);
}

.popup-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
}

/* Instruction Popup */
.instruction-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    background-color: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(24px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.instruction-card {
    width: 100%;
    max-width: 24rem;
    padding: 2rem;
    text-align: center;
    border: 2px solid rgba(251, 191, 36, 0.3);
    background: linear-gradient(180deg, #111 0%, #050505 100%);
    box-shadow: 0 0 40px rgba(251, 191, 36, 0.1);
}
.rule-list {
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin: 1.5rem 0;
}
.rule-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #9ca3af;
    font-weight: 500;
}
.dot {
    width: 0.375rem;
    height: 0.375rem;
    border-radius: 9999px;
    background-color: #fbbf24;
    margin-top: 0.375rem;
    flex-shrink: 0;
    box-shadow: 0 0 5px #fbbf24;
}

/* Quiz Overview Section */
.quiz-overview-section {
    margin-bottom: 1.5rem;
    background: rgba(17, 17, 17, 0.8);
    border: 1px solid rgba(251, 191, 36, 0.2);
    border-radius: 12px;
    padding: 1rem;
    backdrop-filter: blur(10px);
}

.overview-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #fbbf24;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
}

.levels-scroll-container {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #fbbf24 rgba(255, 255, 255, 0.1);
}

.levels-scroll-container::-webkit-scrollbar {
    height: 6px;
}

.levels-scroll-container::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.levels-scroll-container::-webkit-scrollbar-thumb {
    background: #fbbf24;
    border-radius: 10px;
}

.levels-scroll {
    display: flex;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    min-width: max-content;
}

.level-card-mini {
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.3s;
    min-width: 80px;
    flex-shrink: 0;
}

.level-card-mini.completed {
    border-color: #4ade80;
    background: rgba(74, 222, 128, 0.05);
}

.level-card-mini.active {
    border-color: #fbbf24;
    background: rgba(251, 191, 36, 0.1);
    box-shadow: 0 0 15px rgba(251, 191, 36, 0.3);
    animation: pulse-glow 2s ease infinite;
}

.level-card-mini.locked {
    border-color: rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.3);
    opacity: 0.7;
}

.level-card-mini.more-indicator {
    border-color: rgba(251, 191, 36, 0.4);
    background: rgba(251, 191, 36, 0.05);
    border-style: dashed;
}

.level-icon-mini {
    font-size: 1rem;
    margin-bottom: 0.1rem;
}

.level-info-mini {
    text-align: center;
    width: 100%;
}

.level-label-mini {
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.15rem;
    white-space: nowrap;
}

.level-card-mini.completed .level-label-mini {
    color: #4ade80;
}

.level-card-mini.active .level-label-mini {
    color: #fbbf24;
}

.level-card-mini.locked .level-label-mini {
    color: #6b7280;
}

.level-card-mini.more-indicator .level-label-mini {
    color: #fbbf24;
}

.level-reward-mini {
    font-size: 0.6rem;
    color: #9ca3af;
    font-weight: 500;
    white-space: nowrap;
}

.level-card-mini.completed .level-reward-mini {
    color: #86efac;
}

.level-card-mini.active .level-reward-mini {
    color: #fcd34d;
}

.level-card-mini.more-indicator .level-reward-mini {
    color: #fbbf24;
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 20px rgba(251, 191, 36, 0.2);
    }
    50% {
        box-shadow: 0 0 30px rgba(251, 191, 36, 0.4);
    }
}

.loading-progress {
    text-align: center;
    padding: 2rem;
    color: #9ca3af;
}

.loader-small {
    width: 30px;
    height: 30px;
    border: 3px solid #fbbf24;
    border-bottom-color: transparent;
    border-radius: 50%;
    margin: 0 auto 1rem;
    animation: spin 1s linear infinite;
}
</style>
