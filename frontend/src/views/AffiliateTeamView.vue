<template>
  <div class="min-h-screen bg-[#050505] text-white font-sans pb-10">
    <!-- Header -->
    <nav class="sticky top-0 z-40 bg-black/80 backdrop-blur-xl border-b border-white/5 py-3 px-4 flex items-center gap-3">
        <button @click="$router.back()" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-gray-400">
           <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        <h1 class="font-bold text-lg">My Squad (Tree)</h1>
    </nav>

    <!-- Filters -->
    <div class="sticky top-[57px] z-30 bg-[#050505]/95 backdrop-blur-md border-b border-white/5 px-4 py-3 space-y-3">
        <!-- Search -->
        <div class="relative">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
             <input type="text" v-model="filters.search" @input="debouncedSearch" placeholder="Search team member..." class="w-full bg-[#111] border border-white/10 rounded-xl pl-9 pr-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-colors" />
        </div>
    <!-- Toggle View Mode -->
    <div class="px-4 py-2 flex justify-end">
        <div class="bg-[#111] p-1 rounded-lg border border-white/10 flex gap-1">
             <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-yellow-500 text-black' : 'text-gray-500 hover:text-white'" class="p-2 rounded-md transition-all">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
             </button>
             <button @click="viewMode = 'org'" :class="viewMode === 'org' ? 'bg-yellow-500 text-black' : 'text-gray-500 hover:text-white'" class="p-2 rounded-md transition-all">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
             </button>
        </div>
    </div>
    </div>

    <!-- Tree View (Container) -->
    <div class="px-4 py-2 min-h-[50vh] overflow-x-auto">
        <div v-if="loading && team.length === 0" class="flex justify-center py-10">
             <div class="w-8 h-8 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- LIST MODE (Classic) -->
        <div v-else-if="viewMode === 'list' && teamTree.length > 0" class="space-y-4">
             <!-- ... Existing List Code reuse via V-IF ... -->
             <!-- Re-pasting the existing list logic here for clarity, or assuming user replaces the block properly -->
             <!-- Since I cannot reference "existing code" inside a replace block easily without pasting it back, I must include it. -->
             
             <div v-for="l1 in teamTree" :key="l1.id" class="tree-node relative">
                <!-- L1 Card -->
                <div class="node-card border-l-4 border-yellow-500 bg-[#111] p-3 rounded-r-xl border border-white/5 mb-2 relative z-10 transition-all hover:bg-[#151515]">
                    <div class="flex justify-between items-start cursor-pointer" @click="toggleNode(l1.id)">
                        <div class="flex items-center gap-3">
                             <div class="relative">
                                 <div class="w-10 h-10 rounded-full bg-yellow-500/10 text-yellow-500 flex items-center justify-center font-bold text-sm border border-yellow-500/20 z-10 relative">
                                     L1
                                 </div>
                                 <div v-if="l1.children.length > 0 && isExpanded(l1.id)" class="absolute -bottom-6 left-1/2 w-0.5 h-6 bg-white/20 -translate-x-1/2 z-0"></div>
                             </div>
                             <div>
                                 <h4 class="font-bold text-sm text-white flex items-center gap-2">
                                    {{ l1.name }}
                                    <span v-if="l1.children.length > 0" class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10 text-gray-400">
                                        {{ isExpanded(l1.id) ? '−' : '+' }} {{ l1.children.length }}
                                    </span>
                                 </h4>
                                 <p class="text-[10px] text-gray-500">Ref: {{ l1.referral_code }}</p>
                             </div>
                        </div>
                        <div class="text-right">
                             <p class="text-[10px] text-white font-bold">₹{{ formatNumber(l1.total_deposit) }}</p>
                             <p class="text-[9px] text-green-400">+₹{{ formatNumber(l1.earned_from) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Children Container -->
                <div v-if="l1.children.length > 0 && isExpanded(l1.id)" class="pl-5 relative">
                     <div class="absolute top-[-10px] left-[22px] bottom-4 w-0.5 bg-white/10 z-0"></div>
                     <div v-for="l2 in l1.children" :key="l2.id" class="relative pl-6 pt-3">
                         <div class="absolute top-[28px] left-0.5 w-[22px] h-0.5 bg-white/10 rounded-bl-xl"></div>
                         <div class="node-card border-l-4 border-blue-500 bg-[#161616] p-3 rounded-r-xl border border-white/5 relative z-10 transition-all hover:bg-[#1a1a1a]">
                            <div class="flex justify-between items-start cursor-pointer" @click="toggleNode(l2.id)">
                                <div class="flex items-center gap-3">
                                     <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold text-xs border border-blue-500/20">L2</div>
                                     <div>
                                         <h4 class="font-bold text-sm text-white flex items-center gap-2">
                                            {{ l2.name }}
                                            <span v-if="l2.children.length > 0" class="text-[8px] px-1.5 py-0.5 rounded-full bg-white/10 text-gray-400">
                                                {{ isExpanded(l2.id) ? '−' : '+' }} {{ l2.children.length }}
                                            </span>
                                         </h4>
                                         <p class="text-[10px] text-gray-500">From: {{ l1.name }}</p>
                                     </div>
                                </div>
                                <div class="text-right">
                                     <p class="text-[10px] text-white font-bold">₹{{ formatNumber(l2.total_deposit) }}</p>
                                     <p class="text-[9px] text-green-400">+₹{{ formatNumber(l2.earned_from) }}</p>
                                </div>
                            </div>
                         </div>
                         <div v-if="l2.children.length > 0 && isExpanded(l2.id)" class="pl-5 relative">
                              <div class="absolute top-[-10px] left-[18px] bottom-4 w-0.5 bg-white/10 z-0"></div>
                              <div v-for="l3 in l2.children" :key="l3.id" class="relative pl-6 pt-3">
                                  <div class="absolute top-[22px] left-[-3px] w-[22px] h-0.5 bg-white/10"></div>
                                  <div class="node-card border-l-4 border-purple-500 bg-[#1a1a1a] p-2 rounded-r-xl border border-white/5 opacity-90">
                                      <div class="flex justify-between items-center">
                                          <div class="flex items-center gap-2">
                                               <div class="w-6 h-6 rounded-full bg-purple-500/10 text-purple-500 flex items-center justify-center font-bold text-[10px] border border-purple-500/20">L3</div>
                                               <div><h4 class="font-bold text-xs text-white">{{ l3.name }}</h4></div>
                                          </div>
                                          <div class="text-right"><p class="text-[9px] text-white">Dep: ₹{{ formatNumber(l3.total_deposit) }}</p></div>
                                      </div>
                                  </div>
                              </div>
                         </div>
                     </div>
                </div>
            </div>
        </div>

        <!-- ORG CHART MODE -->
        <div v-else-if="viewMode === 'org' && teamTree.length > 0" class="org-tree-container pb-10">
            <div class="tree">
                <ul>
                    <li v-for="l1 in teamTree" :key="l1.id">
                        <!-- L1 Node -->
                        <div class="tree-card l1 cursor-pointer" @click="toggleNode(l1.id)">
                            <!-- Status Dot -->
                            <div class="absolute top-1 right-1 w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                            
                            <div class="w-12 h-12 mx-auto rounded-full p-[2px] bg-gradient-to-br from-yellow-400 to-yellow-600 mb-2">
                                <div class="w-full h-full rounded-full bg-black flex items-center justify-center font-bold text-yellow-500">L1</div>
                            </div>
                            <h4 class="font-bold text-xs text-white truncate max-w-[80px] mx-auto">{{ l1.name }}</h4>
                            <p class="text-[9px] text-gray-400">Ref: {{ l1.referral_code }}</p>
                            <span v-if="l1.children.length > 0" class="inline-block mt-1 px-2 py-[2px] rounded-full bg-white/10 text-[8px] font-bold">
                                {{ isExpanded(l1.id) ? 'Hide' : 'Show' }} {{ l1.children.length }}
                            </span>
                        </div>

                        <!-- L2 Children -->
                        <ul v-if="l1.children.length > 0 && isExpanded(l1.id)">
                            <li v-for="l2 in l1.children" :key="l2.id">
                                <div class="tree-card l2 cursor-pointer" @click="toggleNode(l2.id)">
                                    <div class="w-10 h-10 mx-auto rounded-full p-[1px] bg-blue-500/50 mb-1">
                                         <div class="w-full h-full rounded-full bg-black flex items-center justify-center font-bold text-blue-500 text-[10px]">L2</div>
                                    </div>
                                    <h4 class="font-bold text-[10px] text-white truncate max-w-[70px] mx-auto">{{ l2.name }}</h4>
                                    <span v-if="l2.children.length > 0" class="inline-block mt-1 px-1.5 py-[1px] rounded-full bg-white/10 text-[7px]">
                                        {{ l2.children.length }} Subs
                                    </span>
                                </div>
                                
                                <!-- L3 Children -->
                                <ul v-if="l2.children.length > 0 && isExpanded(l2.id)">
                                     <li v-for="l3 in l2.children" :key="l3.id">
                                         <div class="tree-card l3">
                                            <div class="w-8 h-8 mx-auto rounded-full p-[1px] bg-purple-500/50 mb-1">
                                                <div class="w-full h-full rounded-full bg-black flex items-center justify-center font-bold text-purple-500 text-[9px]">L3</div>
                                            </div>
                                            <h4 class="font-bold text-[9px] text-white truncate max-w-[60px] mx-auto">{{ l3.name }}</h4>
                                         </div>
                                     </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>

        <!-- No Data / Loading -->
        <div v-else-if="!loading" class="text-center py-10 text-gray-500 relative">
             <div class="absolute inset-0 flex items-center justify-center opacity-30 pointer-events-none">
                <img src="https://img.icons8.com/3d-fluency/94/confetti.png" class="w-24 grayscale" />
             </div>
             <p>No team members found.</p>
             <p class="text-xs mt-2">Start inviting to build your tree!</p>
        </div>

        <!-- Scroll Trigger -->
        <div ref="loadTrigger" class="h-10 mt-4 flex justify-center">
             <div v-if="loading && team.length > 0" class="w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>

  </div>
</template>

<script setup>
// Infinite Scroll Logic
import { ref, reactive, onMounted, computed, onUnmounted } from 'vue';
import axios from 'axios';

// Simple Debounce Implementation
function debounce(func, wait) {
  let timeout;
  return function(...args) {
    const context = this;
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(context, args), wait);
  };
}

const team = ref([]);
const loading = ref(false);
const myCode = ref(null);
const hasMore = ref(true);
const loadTrigger = ref(null);
const expandedNodes = reactive(new Set());
const viewMode = ref('list');

const isExpanded = (id) => expandedNodes.has(id);
const toggleNode = (id) => {
    if (expandedNodes.has(id)) {
        expandedNodes.delete(id);
    } else {
        expandedNodes.add(id);
    }
};

const filters = reactive({
    search: '',
    page: 1
});

const formatNumber = (num) => Number(num).toLocaleString('en-IN', { maximumFractionDigits: 2 });

const fetchTeam = async (isLoadMore = false) => {
    if (loading.value) return;
    loading.value = true;
    
    try {
        const params = {
            limit: 20, // Load 20 roots at a time
            page: filters.page,
            search: filters.search 
        };

        const response = await axios.get('/api/get_agent_team.php', { params, withCredentials: true });
        
        if (response.data.success) {
            myCode.value = response.data.my_code;
            
            if (isLoadMore) {
                // Deduplicate before merging (just in case of overlap)
                const newItems = response.data.team.filter(newItem => !team.value.some(existing => existing.id === newItem.id));
                team.value = [...team.value, ...newItems];
                // Auto expand new parents
                newItems.forEach(m => expandedNodes.add(m.id));
            } else {
                team.value = response.data.team;
                // Auto expand all init
                response.data.team.forEach(m => expandedNodes.add(m.id));
                if (!isLoadMore) window.scrollTo(0,0);
            }
            
            hasMore.value = response.data.has_more;
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

const resetAndFetch = () => {
    filters.page = 1;
    hasMore.value = true;
    fetchTeam(false);
}

const debouncedSearch = debounce(() => {
    resetAndFetch();
}, 500);

const loadMore = () => {
    if (hasMore.value && !loading.value) {
        filters.page++;
        fetchTeam(true);
    }
}

// Build Tree Logic
// Build Tree Logic
const teamTree = computed(() => {
    if (!myCode.value || team.value.length === 0) return [];

    const map = {};
    const roots = [];
    
    // Sort All Members by ID (Oldest First) to ensure stable 3x3 Matrix
    const allMembers = [...team.value].sort((a, b) => a.id - b.id);

    // Initialize map
    allMembers.forEach(m => {
        m.children = [];
        map[m.referral_code] = m;
    });

    // Link with Strict Matrix Limit (Max 3 Children)
    allMembers.forEach(m => {
        if (m.referred_by === myCode.value) {
            // Only add to roots if < 3
            if (roots.length < 3) {
                roots.push(m);
            }
        } else {
            const parent = map[m.referred_by];
            if (parent) {
                // Only add to children if < 3
                if (parent.children.length < 3) {
                    parent.children.push(m);
                }
            }
        }
    });

    return roots;
});

const orphans = computed(() => {
     if (!myCode.value) return [];
     const map = {};
     team.value.forEach(m => map[m.referral_code] = m);
     
     return team.value.filter(m => {
         // Is it root?
         if (m.referred_by === myCode.value) return false;
         // Does it have a parent in the list?
         if (map[m.referred_by]) return false;
         return true;
     });
});

let observer;
onMounted(() => {
    fetchTeam();

    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value) {
            loadMore();
        }
    }, { threshold: 0.1 });

    if (loadTrigger.value) observer.observe(loadTrigger.value);
});

onUnmounted(() => {
    if (observer) observer.disconnect();
})
</script>

<style scoped>
/* Org Tree CSS */
.org-tree-container {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 2rem;
}

.tree ul {
	padding-top: 20px; 
    position: relative;
	transition: all 0.5s;
    display: flex;
    justify-content: center;
    padding-left: 0;
}

.tree li {
	float: left; text-align: center;
	list-style-type: none;
	position: relative;
	padding: 20px 10px 0 10px;
	transition: all 0.5s;
}

/* Connectors */
.tree li::before, .tree li::after{
	content: '';
	position: absolute; top: 0; right: 50%;
	border-top: 1px solid rgba(255,255,255,0.2);
	width: 50%; height: 20px;
}
.tree li::after{
	right: auto; left: 50%;
	border-left: 1px solid rgba(255,255,255,0.2);
}

/* Remove connectors from orphans/singles */
.tree li:only-child::after, .tree li:only-child::before {
	display: none;
}
.tree li:only-child{ padding-top: 0;}

/* Remove left connector from first child and right from last child */
.tree li:first-child::before, .tree li:last-child::after{
	border: 0 none;
}

/* Adding back the vertical connector to the last nodes */
.tree li:last-child::before{
	border-right: 1px solid rgba(255,255,255,0.2);
	border-radius: 0 5px 0 0;
}
.tree li:first-child::after{
	border-radius: 5px 0 0 0;
}

/* Downward connector from parent */
.tree ul ul::before{
	content: '';
	position: absolute; top: 0; left: 50%;
	border-left: 1px solid rgba(255,255,255,0.2);
	width: 0; height: 20px;
}

.tree-card {
    border: 1px solid rgba(255,255,255,0.1);
    background: #111;
    padding: 10px;
    border-radius: 12px;
    display: inline-block;
    min-width: 90px;
    position: relative;
    z-index: 10;
    transition: all 0.3s;
}
.tree-card:hover {
    background: #1a1a1a;
    border-color: rgba(255,255,255,0.3);
    transform: scale(1.05);
}

.l1 { border-bottom: 3px solid #eab308; }
.l2 { border-bottom: 3px solid #3b82f6; }
.l3 { border-bottom: 3px solid #a855f7; }

</style>
