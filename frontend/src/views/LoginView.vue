<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'

const userStore = useUserStore()
const router = useRouter()

const mobile = ref('')
const countryCode = ref('+91')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
    error.value = ''
    loading.value = true
    try {
        await userStore.login(mobile.value, password.value) 
        if (userStore.user.role === 'agent') {
            router.push('/agent')
        } else {
            router.push('/dashboard')
        }
    } catch (e) {
         error.value = e.response?.data?.error || e.message || 'Login failed'
         // Auto-clear error after 3 seconds for a smoother feel
         setTimeout(() => error.value = '', 4000)
    } finally {
        loading.value = false
    }
}
</script>

<template>
<div class="login-wrapper">
    <!-- Top Nav with Logo -->
    <div class="top-nav">
        <!-- Logo here -->
        <img src="https://img.icons8.com/3d-fluency/94/trophy.png" class="header-logo" alt="Logo"/>

        <div class="lang-pill">
            <img src="https://img.icons8.com/color/48/usa.png" class="flag-icon" alt="EN"/>
            <span class="lang-text">EN</span>
        </div>
    </div>

    <div class="content-container">
        <!-- Brand Section Removed -->

        <!-- Login Area -->
        <div class="login-area">
            <h2 class="form-header">Sign In</h2>
            
            <form @submit.prevent="handleLogin" class="form-body">
                <div class="input-field">
                    <div class="icon-slot">
                        <img src="https://img.icons8.com/3d-fluency/94/phone.png" />
                    </div>
                     <select v-model="countryCode" class="phone-prefix-select">
                        <option value="+91">+91</option>
                    </select>
                    <input type="tel" v-model="mobile" placeholder="Mobile Number" required />
                </div>

                <div class="input-field">
                    <div class="icon-slot">
                        <img src="https://img.icons8.com/3d-fluency/94/lock.png" />
                    </div>
                    <input type="password" v-model="password" placeholder="Password" required />
                </div>

                <div class="options-row">
                    <label class="custom-checkbox">
                        <input type="checkbox" checked>
                        <span class="checkmark"></span>
                        <span class="label-text">Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot?</a>
                </div>

                <button type="submit" class="cta-btn" :disabled="loading">
                    <span v-if="!loading">LOGIN TO DASHBOARD</span>
                    <span v-else class="loader"></span>
                </button>
            </form>

            <div class="divider">
                <div class="line"></div>
                <span>OR</span>
                <div class="line"></div>
            </div>

            <div class="register-link">
                <p>New here? <router-link to="/register">Create an Account</router-link></p>
            </div>
        </div>
    </div>

    <!-- Floating Error Toast -->
    <transition name="slide-up">
        <div v-if="error" class="error-toast">
            <div class="error-icon">⚠️</div>
            <div class="error-content">
                <p class="error-title">Login Failed</p>
                <p class="error-desc">{{ error }}</p>
            </div>
            <button @click="error = ''" class="close-btn">×</button>
        </div>
    </transition>
</div>
</template>

<style scoped>
/* Page Layout */
.login-wrapper {
    min-height: 100vh;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: radial-gradient(circle at top, #1e293b 0%, #020617 100%);
    position: relative;
}

.top-nav {
    width: 100%;
    max-width: 480px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: absolute; /* Keep it absolute to overlay or stay top */
    top: 0;
    z-index: 20;
}

.header-logo {
    width: 40px; 
    height: 40px; 
    object-fit: contain;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
}

.lang-pill {
    background: rgba(255, 255, 255, 0.05);
    padding: 6px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    margin-left: auto; /* Push to right */
}
.flag-icon { width: 18px; height: 18px; }
.lang-text { font-size: 0.8rem; font-weight: 700; color: #cbd5e1; }

.content-container {
    width: 100%;
    max-width: 480px; 
    z-index: 10;
    flex: 1; /* Take remaining height */
    display: flex;
    flex-direction: column;
    justify-content: center; /* Vertically center */
    padding: 80px 1.5rem 2rem; /* Add top padding to account for nav, and side padding */
}

/* Brand Section Removed Styles */

/* Login Area (No Card) */
.login-area {
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

.form-body {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}

/* Inputs */
.input-field {
    background: rgba(255, 255, 255, 0.05); /* Slightly lighter than card */
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
    /* Focus glow instead of border */
}

.icon-slot {
    width: 48px;
    display: flex;
    justify-content: center;
    opacity: 0.8;
}
.icon-slot img { width: 22px; height: 22px; }

.input-field input {
    flex: 1;
    background: transparent;
    border: none;
    padding: 14px 14px 14px 0;
    color: white;
    font-size: 1rem;
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
    appearance: none;
    -webkit-appearance: none;
    font-size: 1rem;
}
.phone-prefix-select option {
    background: #1e293b;
    color: white;
}

/* Options */
.options-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}
.custom-checkbox {
    display: flex; align-items: center; gap: 8px; cursor: pointer; color: #94a3b8; user-select: none;
}
.custom-checkbox input { display: none; }
.checkmark {
    width: 18px; height: 18px;
    background: rgba(255,255,255,0.1);
    border-radius: 5px;
    position: relative;
    transition: 0.2s;
}
.custom-checkbox input:checked ~ .checkmark { background: #fbbf24; }
.custom-checkbox input:checked ~ .checkmark:after {
    content: ''; position: absolute; left: 6px; top: 2px;
    width: 4px; height: 9px;
    border: solid black; border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.forgot-link { color: #fbbf24; text-decoration: none; font-weight: 600; font-size: 0.85rem; }

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
    margin-top: 1rem;
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

/* Loader Snippet */
.loader {
    width: 20px; height: 20px;
    border: 3px solid #000;
    border-bottom-color: transparent;
    border-radius: 50%;
    display: inline-block;
    animation: spin 1s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

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

.register-link { text-align: center; font-size: 0.95rem; color: #94a3b8; }
.register-link a { color: #fbbf24; text-decoration: none; font-weight: 700; margin-left: 5px; }
.register-link a:hover { text-decoration: underline; }

/* Error Toast Animation */
.error-toast {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    background: #ef4444;
    color: white;
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
.error-icon { font-size: 1.2rem; }
.error-content { flex: 1; }
.error-title { font-weight: 800; font-size: 0.9rem; margin-bottom: 2px; }
.error-desc { font-size: 0.8rem; opacity: 0.9; }
.close-btn { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.7; }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translate(-50%, 20px); }
</style>
