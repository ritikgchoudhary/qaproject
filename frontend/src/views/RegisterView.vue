<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '../stores/user'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const name = ref('')
const mobile = ref('')
const countryCode = ref('+91')
const password = ref('')
const referralCode = ref(route.query.ref || '') // Auto-fill from URL
const error = ref('')
const success = ref('')
const loading = ref(false)

async function handleRegister() {
    error.value = ''
    success.value = ''
    loading.value = true
    try {
        const res = await axios.post('/api/register.php', {
            name: name.value,
            mobile: mobile.value,
            password: password.value,
            referral_code: referralCode.value
        })
        
        if (res.data.success) {
            success.value = 'Registration Successful! Logging in...'
            // Auto Login
            await userStore.login(mobile.value, password.value)
            router.push('/dashboard')
        } else {
             if (res.data.error) error.value = res.data.error;
             setTimeout(() => error.value = '', 4000)
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Registration failed'
        setTimeout(() => error.value = '', 4000)
    } finally {
        loading.value = false
    }
}
</script>

<template>
<div class="register-wrapper">
    <!-- Top Nav -->
    <div class="top-nav-bar">
        <button class="nav-btn back-btn" @click="router.push('/')">
            <img src="https://img.icons8.com/ios-filled/50/ffffff/left.png" alt="Back"/>
        </button>

        <!-- Logo moved to Header -->
        <img src="https://img.icons8.com/3d-fluency/94/trophy.png" class="header-logo" alt="Logo"/>

        <div class="lang-pill">
            <img src="https://img.icons8.com/color/48/usa.png" class="flag-icon" alt="EN"/>
            <span class="lang-text">EN</span>
        </div>
    </div>

    <div class="content-container">
        <!-- Brand Section Removed -->


        <!-- Register Form Area -->
        <div class="register-area">
            <h2 class="form-header">Create Account</h2>
            
            <form @submit.prevent="handleRegister" class="form-body">
                
                <!-- Name -->
                <div class="input-field">
                    <div class="icon-slot">
                        <img src="https://img.icons8.com/3d-fluency/94/user-male-circle.png" />
                    </div>
                    <input type="text" v-model="name" placeholder="Full Name" maxlength="20" required />
                </div>

                <!-- Mobile Number -->
                <div class="input-field">
                    <div class="icon-slot">
                        <img src="https://img.icons8.com/3d-fluency/94/phone.png" />
                    </div>
                    <select v-model="countryCode" class="phone-prefix-select">
                        <option value="+91">+91</option>
                    </select>
                    <input type="tel" v-model="mobile" placeholder="Mobile Number" maxlength="15" required />
                </div>

                <!-- Password -->
                <div class="input-field">
                    <div class="icon-slot">
                        <img src="https://img.icons8.com/3d-fluency/94/lock.png" />
                    </div>
                    <input type="password" v-model="password" placeholder="Password" required />
                </div>

                <!-- Referral Code -->
                <div class="input-field" :class="{ 'ref-active': referralCode }">
                    <div class="icon-slot">
                        <img src="https://img.icons8.com/3d-fluency/94/gift.png" />
                    </div>
                    <input type="text" v-model="referralCode" placeholder="Referral Code (Optional)" />
                    <span v-if="referralCode" class="ref-badge">APPLIED</span>
                </div>

                <button type="submit" class="cta-btn" :disabled="loading">
                    <span v-if="!loading">REGISTER ACCOUNT</span>
                    <span v-else class="loader"></span>
                </button>
            </form>

            <div class="divider">
                <div class="line"></div>
                <span>OR</span>
                <div class="line"></div>
            </div>

            <div class="login-link">
                <p>Already have an account? <router-link to="/">Login Here</router-link></p>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <transition name="slide-up">
        <div v-if="error" class="toast error-toast">
            <div class="toast-icon">⚠️</div>
            <div class="toast-content">
                <p class="toast-title">Error</p>
                <p class="toast-desc">{{ error }}</p>
            </div>
            <button @click="error = ''" class="close-btn">×</button>
        </div>
    </transition>

    <transition name="slide-up">
        <div v-if="success" class="toast success-toast">
            <div class="toast-icon">🎉</div>
            <div class="toast-content">
                <p class="toast-title">Welcome!</p>
                <p class="toast-desc">{{ success }}</p>
            </div>
        </div>
    </transition>
</div>
</template>

<style scoped>
/* Layout */
.register-wrapper {
    min-height: 100vh;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: radial-gradient(circle at top, #1e293b 0%, #020617 100%);
    position: relative;
}

.top-nav-bar {
    width: 100%;
    max-width: 480px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: absolute;
    top: 0;
    z-index: 20;
}
.nav-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    border-radius: 50%;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(5px);
    transition: 0.2s;
}
.nav-btn:hover { background: rgba(255,255,255,0.2); }
.nav-btn img { width: 20px; height: 20px; }

.lang-pill {
    background: rgba(255, 255, 255, 0.05);
    padding: 6px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
}
.flag-icon { width: 18px; height: 18px; }
.lang-text { font-size: 0.8rem; font-weight: 700; color: #cbd5e1; }

.content-container {
    width: 100%;
    max-width: 480px; 
    z-index: 10;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 80px 1.5rem 2rem;
}

.header-logo {
    width: 40px; 
    height: 40px; 
    object-fit: contain;
}

/* Brand Styles Removed */

/* Register Area (No Card) */
.register-area {
    width: 100%;
}

.form-header {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 2rem;
    text-align: center;
    color: #f1f5f9;
    text-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

.form-body { display: flex; flex-direction: column; gap: 1rem; }

/* Inputs */
.input-field {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    display: flex;
    align-items: center;
    padding: 4px;
    transition: all 0.3s ease;
}
.input-field:focus-within {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
}
.icon-slot { width: 48px; display: flex; justify-content: center; opacity: 0.8; }
.icon-slot img { width: 22px; height: 22px; }
.input-field input {
    flex: 1;
    background: transparent;
    border: none;
    padding: 14px 14px 14px 0;
    color: white;
    font-size: 0.95rem;
    font-weight: 500;
    outline: none;
}
.input-field input::placeholder { color: #64748b; }

.phone-prefix-select {
    background: transparent;
    border: none;
    color: #cbd5e1;
    font-weight: 700;
    margin-right: 4px;
    padding: 0 4px;
    outline: none;
    cursor: pointer;
    appearance: none; /* simple look */
    -webkit-appearance: none;
    font-size: 1rem;
}
.phone-prefix-select option {
    background: #1e293b;
    color: white;
}

.ref-active {
    border: 1px solid rgba(251, 191, 36, 0.3);
    background: rgba(251, 191, 36, 0.05);
}
.ref-badge {
    font-size: 0.65rem;
    background: #fbbf24;
    color: #000;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 800;
    margin-right: 10px;
}

/* Button */
/* Button */
.cta-btn {
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
.cta-btn:hover { 
    background: #f59e0b; 
}
.cta-btn:active {
    background: #d97706;
}
.cta-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Footer / Divider */
.divider { 
    display: flex; 
    align-items: center; 
    gap: 1rem; 
    margin: 1.5rem 0; 
}
.line { 
    flex: 1; 
    height: 1px; 
    background: rgba(255,255,255,0.1); 
}
.divider span { 
    color: #64748b; 
    font-size: 0.8rem; 
    font-weight: 600;
}

.login-link { text-align: center; font-size: 0.95rem; color: #94a3b8; }
.login-link a { color: #fbbf24; text-decoration: none; font-weight: 700; margin-left: 5px; }

/* Loader */
.loader {
    width: 20px; height: 20px;
    border: 3px solid #000;
    border-bottom-color: transparent;
    border-radius: 50%;
    display: inline-block;
    animation: spin 1s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Toast */
.toast {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    padding: 1rem 1.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);
    width: 90%;
    max-width: 400px;
    z-index: 100;
}
.error-toast { background: #ef4444; color: white; }
.success-toast { background: #10b981; color: white; }
.toast-icon { font-size: 1.2rem; }
.toast-content { flex: 1; }
.toast-title { font-weight: 800; font-size: 0.9rem; margin-bottom: 2px; }
.toast-desc { font-size: 0.8rem; opacity: 0.9; }
.close-btn { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.7; }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translate(-50%, 20px); }
</style>
