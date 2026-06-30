import { createApp } from 'vue'
import ChatWidget from './components/ChatWidget.vue'

const SemiconChatbot = {
  init(config = {}) {
    const mount = document.createElement('div')
    document.body.appendChild(mount)
    createApp(ChatWidget, {
      apiUrl: config.apiUrl || '',
      title: config.title || 'SEMICON India Assistant',
      subtitle: config.subtitle || 'Ask about the event',
      placeholder: config.placeholder || 'Ask about dates, registration, venue…',
      primaryColor: config.primaryColor || '#0b3d91',
    }).mount(mount)
  },
}

window.SemiconChatbot = SemiconChatbot

export default SemiconChatbot
