import { defineStore } from 'pinia';
import axios from 'axios';

export const useUserStore = defineStore('user', {
    state: () => ({
        user: null,
        wallet: null,
        loading: false,
        error: null,
    }),
    actions: {
        async fetchUser() {
            this.loading = true;
            try {
                const res = await axios.get('/api/getUser.php', { withCredentials: true });
                if (res.data.user) {
                    this.user = res.data.user;
                    this.wallet = res.data.wallet;
                } else {
                    this.user = null;
                }
            } catch (err) {
                this.user = null;
                this.wallet = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },
        async login(mobile, password) {
            try {
                // Use full URL or proxy if setup. 
                // Since QuestionView used http://localhost:8000/qa-platform/api, we should probably be consistent.
                // But let's assume proxy or relative for now, or just hardcode for immediate fix.
                const res = await axios.post('/api/login.php', {
                    mobile: mobile,
                    password: password
                }, { withCredentials: true });

                if (res.data.success) {
                    this.user = res.data.user;
                    // update wallet
                    await this.fetchUser();
                    return true;
                }
                return false;
            } catch (e) {
                throw e;
            }
        },
        async logout() {
            try {
                await axios.post('/api/logout.php', {}, { withCredentials: true });
            } catch (e) {
                console.error(e);
            }
            this.user = null;
            this.wallet = null;
        }
    }
});
