<script setup>
import { ref, onMounted } from 'vue'
import ChatWidget from '@/components/ChatWidget.vue'

const apiUrl = import.meta.env.VITE_API_URL || window.location.origin
</script>

<template>
  <div class="page">
    <section class="hero">
      <h1>SEMICON India 2026</h1>
      <p>Transform Tomorrow — September 17–19, 2026 · Yashobhoomi, New Delhi</p>
    </section>

    <section class="card">
      <h2>Embed the chatbot widget</h2>
      <p>Add this snippet before <code>&lt;/body&gt;</code> on semiconindia.org:</p>
      <pre>&lt;script src="https://your-domain.com/widget.js"&gt;&lt;/script&gt;
&lt;script&gt;
  SemiconChatbot.init({
    apiUrl: 'https://your-domain.com',
    title: 'SEMICON India Assistant',
    primaryColor: '#0b3d91'
  });
&lt;/script&gt;</pre>
      <p>Try the floating chat button on this page.</p>
      <router-link to="/admin">Open admin panel →</router-link>
    </section>

    <ChatWidget :api-url="apiUrl" subtitle="Event info only · low-token answers" />
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: linear-gradient(160deg, #0b3d91 0%, #1e5fbf 40%, #f1f5f9 40%); }
.hero { max-width: 720px; margin: 80px auto 0; padding: 0 24px; color: #fff; }
.hero h1 { font-size: 36px; margin-bottom: 8px; }
.card {
  max-width: 720px; margin: 32px auto; background: #fff; border-radius: 16px;
  padding: 24px; box-shadow: 0 8px 30px rgba(0,0,0,.12);
}
pre { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 13px; }
a { color: #0b3d91; }
</style>
