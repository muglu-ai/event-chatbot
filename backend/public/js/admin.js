(function () {
  const apiBase = window.location.origin.replace(/\/$/, '');
  const keyInput = document.getElementById('admin-key');
  const loadBtn = document.getElementById('load-dashboard');
  const refreshBtn = document.getElementById('refresh-dashboard');
  const errorEl = document.getElementById('admin-error');
  const statsSection = document.getElementById('stats-section');
  const logsSection = document.getElementById('logs-section');
  const statsGrid = document.getElementById('stats-grid');
  const logsBody = document.getElementById('logs-body');

  const statLabels = [
    { key: 'total_requests', label: 'Total requests' },
    { key: 'total_tokens', label: 'Total tokens' },
    { key: 'avg_tokens', label: 'Avg tokens / chat' },
    { key: 'improved_count', label: 'Questions improved' },
    { key: 'learned_count', label: 'Auto-learned Q&A' },
    { key: 'kb_count', label: 'KB answers' },
    { key: 'ai_count', label: 'AI answers' },
    { key: 'rejected_count', label: 'Off-topic rejected' },
  ];

  keyInput.value = localStorage.getItem('semicon_admin_key') || '';

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.classList.toggle('hidden', !msg);
  }

  async function fetchJson(path) {
    const res = await fetch(`${apiBase}${path}`, {
      headers: { 'X-Admin-Key': keyInput.value.trim() },
    });
    if (res.status === 401) throw new Error('Invalid admin key');
    if (!res.ok) throw new Error('Request failed');
    return res.json();
  }

  function renderStats(stats) {
    statsGrid.innerHTML = statLabels.map(({ key, label }) => `
      <div class="stat">
        <span>${label}</span>
        <strong>${stats[key] ?? 0}</strong>
      </div>
    `).join('');
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
  }

  function renderLogs(logs) {
    logsBody.innerHTML = logs.map((log) => `
      <tr>
        <td>${log.id}</td>
        <td>${escapeHtml(log.created_at)}</td>
        <td class="cell">${escapeHtml(log.original_question || log.question)}</td>
        <td class="cell">${escapeHtml(log.improved_question || '—')}</td>
        <td class="cell">${escapeHtml(log.answer)}</td>
        <td>${log.tokens_used}</td>
        <td><span class="badge ${escapeHtml(log.source)}">${escapeHtml(log.source)}</span></td>
        <td>${escapeHtml(log.provider || '—')}</td>
        <td>${escapeHtml(log.status)}</td>
      </tr>
    `).join('');
  }

  async function loadDashboard() {
    showError('');
    try {
      const [stats, logsData] = await Promise.all([
        fetchJson('/api/admin/stats'),
        fetchJson('/api/admin/logs?limit=200'),
      ]);
      renderStats(stats);
      renderLogs(logsData.logs);
      statsSection.classList.remove('hidden');
      logsSection.classList.remove('hidden');
      localStorage.setItem('semicon_admin_key', keyInput.value.trim());
    } catch (e) {
      showError(e.message);
      statsSection.classList.add('hidden');
      logsSection.classList.add('hidden');
    }
  }

  loadBtn.addEventListener('click', loadDashboard);
  refreshBtn.addEventListener('click', loadDashboard);

  if (keyInput.value) {
    loadDashboard();
  }
})();
