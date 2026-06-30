<script setup>
import { ref, onMounted } from 'vue'

const adminKey = ref(localStorage.getItem('semicon_admin_key') || '')
const stats = ref(null)
const logs = ref([])
const error = ref('')
const loaded = ref(false)

const apiBase = (import.meta.env.VITE_API_URL || window.location.origin).replace(/\/$/, '')

async function fetchJson(path) {
  const res = await fetch(`${apiBase}${path}`, {
    headers: { 'X-Admin-Key': adminKey.value.trim() },
  })
  if (res.status === 401) throw new Error('Invalid admin key')
  if (!res.ok) throw new Error('Request failed')
  return res.json()
}

async function loadDashboard() {
  error.value = ''
  try {
    const [statsData, logsData] = await Promise.all([
      fetchJson('/api/admin/stats'),
      fetchJson('/api/admin/logs?limit=200'),
    ])
    stats.value = statsData
    logs.value = logsData.logs
    loaded.value = true
    localStorage.setItem('semicon_admin_key', adminKey.value.trim())
  } catch (e) {
    error.value = e.message
    loaded.value = false
  }
}

onMounted(() => {
  if (adminKey.value) loadDashboard()
})
</script>

<template>
  <div class="admin">
    <header>
      <h1>SEMICON Chatbot Admin</h1>
      <router-link to="/">← Demo</router-link>
    </header>

    <section class="auth card">
      <label>Admin key</label>
      <input v-model="adminKey" type="password" placeholder="Enter admin key" />
      <button @click="loadDashboard">Load dashboard</button>
      <p v-if="error" class="error">{{ error }}</p>
    </section>

    <section v-if="loaded" class="card">
      <div class="stats">
        <div v-for="item in [
          { label: 'Total requests', value: stats.total_requests },
          { label: 'Total tokens', value: stats.total_tokens },
          { label: 'Avg tokens / chat', value: stats.avg_tokens },
          { label: 'Questions improved', value: stats.improved_count },
          { label: 'Auto-learned Q&A', value: stats.learned_count },
          { label: 'KB answers', value: stats.kb_count },
          { label: 'AI answers', value: stats.ai_count },
          { label: 'Off-topic rejected', value: stats.rejected_count },
        ]" :key="item.label" class="stat">
          <span>{{ item.label }}</span>
          <strong>{{ item.value ?? 0 }}</strong>
        </div>
      </div>
    </section>

    <section v-if="loaded" class="card">
      <div class="toolbar">
        <h2>All chat requests</h2>
        <button class="secondary" @click="loadDashboard">Refresh</button>
      </div>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Time</th>
            <th>Original</th>
            <th>Improved</th>
            <th>Answer</th>
            <th>Tokens</th>
            <th>Source</th>
            <th>Provider</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td>{{ log.id }}</td>
            <td>{{ log.created_at }}</td>
            <td class="cell">{{ log.original_question || log.question }}</td>
            <td class="cell">{{ log.improved_question || '—' }}</td>
            <td class="cell">{{ log.answer }}</td>
            <td>{{ log.tokens_used }}</td>
            <td><span :class="['badge', log.source]">{{ log.source }}</span></td>
            <td>{{ log.provider || '—' }}</td>
            <td>{{ log.status }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

<style scoped>
.admin { max-width: 1200px; margin: 0 auto; padding: 24px; font-family: system-ui, sans-serif; }
header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
.auth { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
button { padding: 8px 16px; border: none; border-radius: 8px; background: #0b3d91; color: #fff; cursor: pointer; }
button.secondary { background: #64748b; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
.stat { background: #f8fafc; border-radius: 8px; padding: 12px; }
.stat span { font-size: 12px; color: #64748b; text-transform: uppercase; }
.stat strong { display: block; font-size: 22px; margin-top: 4px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
.cell { max-width: 240px; word-break: break-word; }
.badge { padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge.kb { background: #dcfce7; color: #166534; }
.badge.ai { background: #dbeafe; color: #1e40af; }
.badge.filter { background: #fee2e2; color: #991b1b; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.error { color: #b91c1c; margin: 0; }
a { color: #0b3d91; }
</style>
