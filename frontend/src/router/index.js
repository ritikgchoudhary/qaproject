import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '../stores/user'

import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import DashboardView from '../views/DashboardView.vue'
import QuestionView from '../views/QuestionView.vue'
import WalletView from '../views/WalletView.vue'
import DepositView from '../views/DepositView.vue'
import WithdrawView from '../views/WithdrawView.vue'
import ReferralsView from '../views/ReferralsView.vue'
import AdminView from '../views/AdminView.vue'

import AgentDashboardView from '../views/AgentDashboardView.vue'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        { path: '/', name: 'login', component: LoginView },
        { path: '/register', name: 'register', component: RegisterView },
        { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
        { path: '/agent', name: 'agent_dashboard', component: AgentDashboardView, meta: { requiresAuth: true } },
        {
            path: '/affiliate-team',
            name: 'affiliate_team',
            component: () => import('../views/AffiliateTeamView.vue'),
            meta: { requiresAuth: true }
        },
        { path: '/question', name: 'question', component: QuestionView, meta: { requiresAuth: true } },
        { path: '/wallet', name: 'wallet', component: WalletView, meta: { requiresAuth: true } },
        { path: '/deposit', name: 'deposit', component: DepositView, meta: { requiresAuth: true } },
        { path: '/withdraw', name: 'withdraw', component: WithdrawView, meta: { requiresAuth: true } },
        { path: '/referrals', name: 'referrals', component: ReferralsView, meta: { requiresAuth: true } },
        { path: '/admin', name: 'admin', component: AdminView },
    ]
})

router.beforeEach(async (to, from, next) => {
    const userStore = useUserStore()

    // Check user status on every page load if not loaded, 
    // to handle redirects for 'login'/'register' if already logged in.
    if (!userStore.user) {
        try {
            await userStore.fetchUser()
        } catch (e) { }
    }

    if (to.meta.requiresAuth && !userStore.user) {
        next({ name: 'login' })
    } else if ((to.name === 'login' || to.name === 'register') && userStore.user) {
        // Redirect based on role
        if (userStore.user.role === 'agent') {
            next({ name: 'agent_dashboard' })
        } else {
            next({ name: 'dashboard' })
        }
    } else {
        // Strict Agent Blocking
        if (userStore.user && userStore.user.role === 'agent') {
            // List of allowed routes for agents
            const allowedForMethod = ['agent_dashboard', 'affiliate_team'];

            if (!allowedForMethod.includes(to.name)) {
                // If trying to access any other page (wallet, question, dashboard, etc.), redirect to agent panel
                next({ name: 'agent_dashboard' })
                return
            }
        }

        // Prevent Users from accessing Agent Dashboard
        if (userStore.user && userStore.user.role !== 'agent' && to.name === 'agent_dashboard') {
            next({ name: 'dashboard' })
            return
        }

        next()
    }
})

export default router
