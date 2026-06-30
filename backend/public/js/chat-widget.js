(function () {
  const SESSION_KEY = 'semicon_chat_session';
  const MESSAGES_KEY = 'semicon_chat_messages';

  function apiBase(config) {
    return (config.apiUrl || window.location.origin).replace(/\/$/, '');
  }

  function getSessionId() {
    let id = localStorage.getItem(SESSION_KEY);
    if (!id) {
      id = crypto.randomUUID();
      localStorage.setItem(SESSION_KEY, id);
    }
    return id;
  }

  function createWidget(config) {
    const primaryColor = config.primaryColor || '#0b3d91';
    const title = config.title || 'SEMICON India Assistant';
    const subtitle = config.subtitle || 'Ask about the event';
    const placeholder = config.placeholder || 'Ask about dates, registration, venue…';

    const root = document.createElement('div');
    root.className = 'scb-root';
    root.style.setProperty('--scb-primary', primaryColor);

    root.innerHTML = `
      <button class="scb-toggle" style="background:${primaryColor}" aria-label="Open chat">💬</button>
      <div class="scb-panel hidden">
        <div class="scb-header" style="background:${primaryColor}">
          <h3></h3>
          <p></p>
        </div>
        <div class="scb-messages"></div>
        <div class="scb-suggestions"></div>
        <div class="scb-typing hidden">Thinking…</div>
        <form class="scb-input-row">
          <input autocomplete="off" />
          <button type="submit">Send</button>
        </form>
      </div>
    `;

    root.querySelector('.scb-header h3').textContent = title;
    root.querySelector('.scb-header p').textContent = subtitle;
    const input = root.querySelector('.scb-input-row input');
    input.placeholder = placeholder;

    const toggle = root.querySelector('.scb-toggle');
    const panel = root.querySelector('.scb-panel');
    const messagesEl = root.querySelector('.scb-messages');
    const suggestionsEl = root.querySelector('.scb-suggestions');
    const typingEl = root.querySelector('.scb-typing');
    const form = root.querySelector('.scb-input-row');

    let messages = [];
    let suggestions = [];
    let loading = false;
    let suggestTimer = null;
    let isOpen = false;

    function getHistoryForApi() {
      return messages
        .filter((m) => m.role === 'user' || m.role === 'bot')
        .map((m) => ({
          role: m.role === 'bot' ? 'assistant' : 'user',
          content: m.text,
        }));
    }

    function saveMessagesLocally() {
      localStorage.setItem(MESSAGES_KEY, JSON.stringify(messages));
    }

    function loadMessagesLocally() {
      try {
        const saved = JSON.parse(localStorage.getItem(MESSAGES_KEY) || '[]');
        if (Array.isArray(saved) && saved.length) {
          messages = saved;
          return true;
        }
      } catch {
        /* ignore */
      }
      return false;
    }

    function setWelcome() {
      messages = [{
        role: 'bot',
        text: 'Hi! Ask me about SEMICON India 2026 — dates, registration, venue, visa, or exhibitors.',
      }];
    }

    function renderMessages() {
      messagesEl.innerHTML = messages.map((msg) => {
        const cls = ['scb-msg', msg.role];
        if (msg.meta) cls.push('meta');
        return `<div class="${cls.join(' ')}">${escapeHtml(msg.text)}</div>`;
      }).join('');
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderSuggestions() {
      suggestionsEl.innerHTML = suggestions.map((s) =>
        `<button type="button" class="scb-chip" ${loading ? 'disabled' : ''}>${escapeHtml(s)}</button>`
      ).join('');
      suggestionsEl.querySelectorAll('.scb-chip').forEach((btn, i) => {
        btn.addEventListener('click', () => pickSuggestion(suggestions[i]));
      });
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    async function fetchSuggestions(query = '') {
      try {
        const res = await fetch(`${apiBase(config)}/api/chat/suggestions?q=${encodeURIComponent(query)}`);
        if (!res.ok) return;
        const data = await res.json();
        suggestions = data.suggestions || [];
        renderSuggestions();
      } catch {
        suggestions = [];
        renderSuggestions();
      }
    }

    async function loadSessionFromServer() {
      try {
        const res = await fetch(`${apiBase(config)}/api/chat/session/${getSessionId()}`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.messages?.length) {
          messages = data.messages.map((m) => ({
            role: m.role === 'assistant' ? 'bot' : 'user',
            text: m.content,
          }));
          saveMessagesLocally();
          renderMessages();
        }
        if (data.suggestions?.length) {
          suggestions = data.suggestions;
          renderSuggestions();
        }
      } catch {
        /* ignore */
      }
    }

    function pickSuggestion(text) {
      input.value = text;
      send();
    }

    async function send() {
      const text = input.value.trim();
      if (!text || loading) return;

      messages.push({ role: 'user', text });
      input.value = '';
      loading = true;
      input.disabled = true;
      typingEl.classList.remove('hidden');
      saveMessagesLocally();
      renderMessages();
      renderSuggestions();

      try {
        const res = await fetch(`${apiBase(config)}/api/chat`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            message: text,
            sessionId: getSessionId(),
            history: getHistoryForApi().slice(0, -1),
          }),
        });
        if (!res.ok) throw new Error('failed');
        const data = await res.json();

        if (data.wasImproved && data.improvedQuestion && data.improvedQuestion !== text && text.length < 25) {
          messages.push({
            role: 'bot',
            text: `↳ Understood as: “${data.improvedQuestion}”`,
            meta: true,
          });
        }

        messages.push({ role: 'bot', text: data.answer });
        if (data.suggestions?.length) {
          suggestions = data.suggestions;
        }
      } catch {
        messages.push({ role: 'bot', text: 'Sorry, something went wrong. Please try again.' });
      } finally {
        loading = false;
        input.disabled = false;
        typingEl.classList.add('hidden');
        saveMessagesLocally();
        renderMessages();
        renderSuggestions();
        fetchSuggestions('');
      }
    }

    toggle.addEventListener('click', () => {
      isOpen = !isOpen;
      panel.classList.toggle('hidden', !isOpen);
      if (isOpen) {
        loadSessionFromServer();
        fetchSuggestions(input.value.trim());
      }
    });

    input.addEventListener('input', () => {
      clearTimeout(suggestTimer);
      suggestTimer = setTimeout(() => fetchSuggestions(input.value.trim()), 200);
    });

    input.addEventListener('focus', () => fetchSuggestions(input.value.trim()));

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      send();
    });

    if (!loadMessagesLocally()) {
      setWelcome();
    }
    renderMessages();
    fetchSuggestions('');

    return root;
  }

  window.SemiconChatbot = {
    init(config = {}) {
      const mount = document.createElement('div');
      document.body.appendChild(mount);
      mount.appendChild(createWidget(config));
    },
    mount(el, config = {}) {
      const target = typeof el === 'string' ? document.querySelector(el) : el;
      if (target) target.appendChild(createWidget(config));
    },
  };
})();
