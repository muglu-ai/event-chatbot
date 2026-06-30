<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  apiUrl: { type: String, default: '' },
  title: { type: String, default: 'SEMICON India Assistant' },
  subtitle: { type: String, default: 'Ask about the event' },
  placeholder: { type: String, default: 'Ask about dates, registration, venue…' },
  primaryColor: { type: String, default: '#0b3d91' },
})

const isOpen = ref(false)
const input = ref('')
const loading = ref(false)
const suggestions = ref([])
const messages = ref([])

const SESSION_KEY = 'semicon_chat_session'
const MESSAGES_KEY = 'semicon_chat_messages'

function apiBase() {
  return (props.apiUrl || window.location.origin).replace(/\/$/, '')
}

function getSessionId() {
  let id = localStorage.getItem(SESSION_KEY)
  if (!id) {
    id = crypto.randomUUID()
    localStorage.setItem(SESSION_KEY, id)
  }
  return id
}

function getHistoryForApi() {
  return messages.value
    .filter((m) => m.role === 'user' || m.role === 'bot')
    .map((m) => ({
      role: m.role === 'bot' ? 'assistant' : 'user',
      content: m.text,
    }))
}

function saveMessagesLocally() {
  localStorage.setItem(MESSAGES_KEY, JSON.stringify(messages.value))
}

function loadMessagesLocally() {
  try {
    const saved = JSON.parse(localStorage.getItem(MESSAGES_KEY) || '[]')
    if (Array.isArray(saved) && saved.length) {
      messages.value = saved
      return true
    }
  } catch {
    /* ignore */
  }
  return false
}

function setWelcome() {
  messages.value = [{
    role: 'bot',
    text: 'Hi! Ask me about SEMICON India 2026 — dates, registration, venue, visa, or exhibitors.',
  }]
}

async function fetchSuggestions(query = '') {
  try {
    const res = await fetch(`${apiBase()}/api/chat/suggestions?q=${encodeURIComponent(query)}`)
    if (!res.ok) return
    const data = await res.json()
    suggestions.value = data.suggestions || []
  } catch {
    suggestions.value = []
  }
}

async function loadSessionFromServer() {
  try {
    const res = await fetch(`${apiBase()}/api/chat/session/${getSessionId()}`)
    if (!res.ok) return
    const data = await res.json()
    if (data.messages?.length) {
      messages.value = data.messages.map((m) => ({
        role: m.role === 'assistant' ? 'bot' : 'user',
        text: m.content,
      }))
      saveMessagesLocally()
    }
    if (data.suggestions?.length) {
      suggestions.value = data.suggestions
    }
  } catch {
    /* ignore */
  }
}

let suggestTimer = null
watch(input, (val) => {
  clearTimeout(suggestTimer)
  suggestTimer = setTimeout(() => fetchSuggestions(val.trim()), 200)
})

onMounted(async () => {
  if (!loadMessagesLocally()) {
    setWelcome()
  }
  await fetchSuggestions('')
})

function toggle() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    loadSessionFromServer()
    fetchSuggestions(input.value.trim())
  }
}

function pickSuggestion(text) {
  input.value = text
  send()
}

async function send() {
  const text = input.value.trim()
  if (!text || loading.value) return

  messages.value.push({ role: 'user', text })
  input.value = ''
  loading.value = true
  saveMessagesLocally()

  try {
    const res = await fetch(`${apiBase()}/api/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: text,
        sessionId: getSessionId(),
        history: getHistoryForApi().slice(0, -1),
      }),
    })
    if (!res.ok) throw new Error('failed')
    const data = await res.json()

    if (data.wasImproved && data.improvedQuestion && data.improvedQuestion !== text && text.length < 25) {
      messages.value.push({
        role: 'bot',
        text: `↳ Understood as: “${data.improvedQuestion}”`,
        meta: true,
      })
    }

    messages.value.push({ role: 'bot', text: data.answer })
    if (data.suggestions?.length) {
      suggestions.value = data.suggestions
    }
  } catch {
    messages.value.push({ role: 'bot', text: 'Sorry, something went wrong. Please try again.' })
  } finally {
    loading.value = false
    saveMessagesLocally()
    fetchSuggestions('')
  }
}
</script>

<template>
  <div class="scb-root" :style="{ '--scb-primary': primaryColor }">
    <button class="scb-toggle" :style="{ background: primaryColor }" @click="toggle" aria-label="Open chat">
      💬
    </button>

    <div v-show="isOpen" class="scb-panel">
      <div class="scb-header" :style="{ background: primaryColor }">
        <h3>{{ title }}</h3>
        <p>{{ subtitle }}</p>
      </div>

      <div class="scb-messages">
        <div
          v-for="(msg, i) in messages"
          :key="i"
          :class="['scb-msg', msg.role, { meta: msg.meta }]"
        >
          {{ msg.text }}
        </div>
      </div>

      <div v-if="suggestions.length" class="scb-suggestions">
        <button
          v-for="(s, i) in suggestions"
          :key="i"
          type="button"
          class="scb-chip"
          :disabled="loading"
          @click="pickSuggestion(s)"
        >
          {{ s }}
        </button>
      </div>

      <div v-if="loading" class="scb-typing">Thinking…</div>

      <form class="scb-input-row" @submit.prevent="send">
        <input
          v-model="input"
          :placeholder="placeholder"
          :disabled="loading"
          autocomplete="off"
          @focus="fetchSuggestions(input.trim())"
        />
        <button type="submit" :disabled="loading">Send</button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.scb-root { font-family: system-ui, -apple-system, sans-serif; z-index: 99999; }
.scb-toggle {
  position: fixed; bottom: 24px; right: 24px; width: 56px; height: 56px;
  border-radius: 50%; border: none; cursor: pointer; color: #fff;
  box-shadow: 0 4px 20px rgba(0,0,0,.25); font-size: 24px;
}
.scb-panel {
  position: fixed; bottom: 92px; right: 24px; width: 340px; max-width: calc(100vw - 32px);
  height: 520px; max-height: calc(100vh - 120px); background: #fff; border-radius: 16px;
  box-shadow: 0 8px 40px rgba(0,0,0,.18); display: flex; flex-direction: column; overflow: hidden;
}
.scb-header { padding: 16px; color: #fff; }
.scb-header h3 { margin: 0; font-size: 16px; }
.scb-header p { margin: 4px 0 0; font-size: 12px; opacity: .85; }
.scb-messages { flex: 1; overflow-y: auto; padding: 12px; background: #f7f9fc; }
.scb-msg { margin-bottom: 10px; max-width: 88%; font-size: 13px; line-height: 1.45; }
.scb-msg.user { margin-left: auto; background: #e8eef8; padding: 8px 12px; border-radius: 12px 12px 4px 12px; }
.scb-msg.bot { background: #fff; padding: 8px 12px; border-radius: 12px 12px 12px 4px; border: 1px solid #e2e8f0; }
.scb-msg.meta { font-size: 11px; color: #64748b; background: transparent; border: none; padding: 0 4px; }
.scb-suggestions {
  display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 12px 0;
  max-height: 88px; overflow-y: auto;
}
.scb-chip {
  border: 1px solid #cbd5e1; background: #fff; color: #334155;
  border-radius: 999px; padding: 4px 10px; font-size: 11px; cursor: pointer;
  line-height: 1.3; text-align: left;
}
.scb-chip:hover:not(:disabled) { border-color: var(--scb-primary, #0b3d91); color: var(--scb-primary, #0b3d91); }
.scb-chip:disabled { opacity: .6; cursor: not-allowed; }
.scb-input-row { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e2e8f0; }
.scb-input-row input {
  flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 13px;
}
.scb-input-row button {
  border: none; border-radius: 8px; padding: 0 14px; color: #fff; cursor: pointer;
  background: var(--scb-primary, #0b3d91);
}
.scb-typing { font-size: 12px; color: #64748b; padding: 0 12px 8px; }
</style>
