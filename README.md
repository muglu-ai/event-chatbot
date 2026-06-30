# SEMICON India Chatbot

Laravel + Vue.js event chatbot for [SEMICON India](https://www.semiconindia.org/), Docker-ready for DigitalOcean with minimal setup.

## Stack

| Layer | Tech |
|-------|------|
| API | Laravel 12 (PHP 8.4) |
| UI | Vue 3 + Vite |
| Widget | Embeddable `widget.js` (IIFE) |
| AI | Knowledge base (default) + Claude, OpenAI, Cursor CLI |
| Deploy | Docker Compose (nginx + php-fpm) |

## Features

- **50–80 tokens per chat** via compact knowledge-base answers
- **Event-only** — off-topic questions are rejected
- **AI providers** — `CHATBOT_AI_PROVIDER=auto|kb|claude|openai|cursor`
- **Admin panel** — `/admin` with all requests + token usage
- **Embeddable widget** — drop `widget.js` on any site

## Quick start (Docker — recommended for DigitalOcean)

```bash
cp .env.example .env
# Edit .env: set ADMIN_KEY, optional ANTHROPIC_API_KEY / OPENAI_API_KEY

docker compose up -d --build
```

Open **http://localhost:8080**

- Demo: `/`
- Admin: `/admin` (use `ADMIN_KEY` from `.env`)
- Widget: `/widget.js`

### DigitalOcean droplet

1. Create a Ubuntu droplet (1 GB RAM is enough to start)
2. Install Docker: `curl -fsSL https://get.docker.com | sh`
3. Clone repo, copy `.env`, set `APP_URL` to your domain
4. `docker compose up -d --build`
5. Point domain A-record to droplet IP (optional: add Caddy/Traefik for HTTPS)

## Local development

```bash
# Terminal 1 — Laravel API
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8000

# Terminal 2 — Vue frontend
cd frontend
npm install
npm run dev
```

Frontend proxies `/api` → `localhost:8000`.

## AI configuration

| Provider | Env vars | Notes |
|----------|----------|-------|
| **kb** | — | Rule-based only, zero API cost |
| **auto** | Any AI key | KB first, AI fallback on no match |
| **claude** | `ANTHROPIC_API_KEY` | Anthropic API |
| **openai** | `OPENAI_API_KEY` | OpenAI API |
| **cursor** | `CURSOR_CLI_ENABLED=true` | Runs `CURSOR_CLI_COMMAND` on server |

```env
CHATBOT_AI_PROVIDER=auto
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
CURSOR_CLI_ENABLED=false
CURSOR_CLI_COMMAND=cursor agent --print --output-format text
```

## Embed widget

```html
<script src="https://your-domain.com/widget.js"></script>
<script>
  SemiconChatbot.init({
    apiUrl: 'https://your-domain.com',
    title: 'SEMICON India Assistant',
    primaryColor: '#0b3d91'
  });
</script>
```

## API

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/chat` | — |
| GET | `/api/health` | — |
| GET | `/api/admin/logs` | `X-Admin-Key` header |
| GET | `/api/admin/stats` | `X-Admin-Key` header |

## Knowledge base

Edit `backend/storage/app/knowledge.json` with facts from [semiconindia.org](https://www.semiconindia.org/).
