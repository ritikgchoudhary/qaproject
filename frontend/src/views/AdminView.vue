<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const tab = ref('users')
const users = ref([])
const deposits = ref([])
const withdraws = ref([])

async function loadData() {
    try {
        if (tab.value === 'users') {
            const res = await axios.get('/api/admin.php?action=getUsers')
            users.value = res.data
        } else if (tab.value === 'deposits') {
            const res = await axios.get('/api/admin.php?action=getDeposits')
            deposits.value = res.data
        } else if (tab.value === 'withdraws') {
            const res = await axios.get('/api/admin.php?action=getWithdraws')
            withdraws.value = res.data
        }
    } catch(e) {
        console.error(e)
    }
}

async function approveWithdraw(id) {
    if (!confirm('Approve this withdrawal?')) return
    await axios.post('/api/admin.php?action=approveWithdraw', { id })
    loadData()
}

async function rejectWithdraw(id) {
    if (!confirm('Reject this withdrawal?')) return
    await axios.post('/api/admin.php?action=rejectWithdraw', { id })
    loadData()
}

onMounted(() => loadData())
</script>

<template>
  <div class="admin-panel">
    <h1>Admin Panel</h1>
    <div class="tabs">
        <button @click="tab='users'; loadData()" :class="{active: tab=='users'}">Users</button>
        <button @click="tab='deposits'; loadData()" :class="{active: tab=='deposits'}">Deposits</button>
        <button @click="tab='withdraws'; loadData()" :class="{active: tab=='withdraws'}">Withdraws</button>
    </div>

    <div class="content">
        <div v-if="tab==='users'">
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Referral Code</th><th>Referred By</th></tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id">
                        <td>{{u.id}}</td><td>{{u.name}}</td><td>{{u.email}}</td><td>{{u.referral_code}}</td><td>{{u.referred_by}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="tab==='deposits'">
             <table>
                <thead>
                    <tr><th>ID</th><th>User ID</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <tr v-for="d in deposits" :key="d.id">
                        <td>{{d.id}}</td><td>{{d.user_id}}</td><td>₹{{d.amount}}</td>
                        <td><span :class="'status ' + d.status">{{d.status}}</span></td>
                        <td>{{d.created_at}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="tab==='withdraws'">
             <table>
                <thead>
                    <tr><th>ID</th><th>User ID</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="w in withdraws" :key="w.id">
                        <td>{{w.id}}</td><td>{{w.user_id}}</td><td>₹{{w.amount}}</td>
                        <td><span :class="'status ' + w.status">{{w.status}}</span></td>
                        <td>{{w.created_at}}</td>
                        <td>
                            <div v-if="w.status==='pending'" class="actions">
                                <button @click="approveWithdraw(w.id)" class="approve">Approve</button>
                                <button @click="rejectWithdraw(w.id)" class="reject">Reject</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
  </div>
</template>

<style scoped>
.admin-panel { max-width: 1000px; margin: 20px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.tabs button { padding: 10px 20px; border: none; background: #eee; cursor: pointer; border-radius: 5px; font-weight: bold; }
.tabs button.active { background: #3498db; color: white; }

table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #eee; padding: 10px; text-align: left; }
th { background: #f9f9f9; }
.status { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; }
.status.success, .status.approved { background: #d4edda; color: #155724; }
.status.pending { background: #fff3cd; color: #856404; }
.status.rejected { background: #f8d7da; color: #721c24; }

.actions button { margin-right: 5px; padding: 5px 10px; cursor: pointer; border: none; border-radius: 3px; color: white; }
.approve { background: #2ecc71; }
.reject { background: #e74c3c; }
</style>
