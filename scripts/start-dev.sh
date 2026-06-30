#!/usr/bin/env bash
# ============================================================
# ClipTrend AI V7 — Dev Server Start Script
# Usage: bash scripts/start-dev.sh
# ============================================================
set -e
cd "$(dirname "$0")/.."

GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo ""
echo -e "${CYAN}  ClipTrend AI V7 — AI Shorts Studio${NC}"
echo -e "${CYAN}  Free Stack: Whisper (offline) + Gemini (text-only)${NC}"
echo ""

# Check php-local.ini
if [ ! -f "php-local.ini" ]; then
    echo -e "${YELLOW}⚠  Membuat php-local.ini...${NC}"
    cat > php-local.ini << 'INI'
memory_limit = 2048M
upload_max_filesize = 2048M
post_max_size = 2100M
max_execution_time = 3600
max_input_time = 3600
INI
fi

# Load .env for status display
set -a; source .env 2>/dev/null; set +a

# Clear caches
echo -e "${CYAN}→ Membersihkan cache...${NC}"
php -c php-local.ini artisan config:clear -q 2>/dev/null || true
php -c php-local.ini artisan route:clear -q 2>/dev/null || true
php -c php-local.ini artisan view:clear -q 2>/dev/null || true

# Run pending migrations
echo -e "${CYAN}→ Memeriksa database...${NC}"
php -c php-local.ini artisan migrate --no-interaction -q 2>/dev/null && echo -e "${GREEN}✓ Database siap${NC}" || true

echo ""
echo -e "  AI Provider  : ${GREEN}${AI_PROVIDER:-real}${NC}"
echo -e "  Queue Mode   : ${GREEN}${QUEUE_CONNECTION:-sync}${NC}"

# Check Whisper
if command -v whisper &>/dev/null; then
    MODELS=$(ls ~/.cache/whisper/*.pt 2>/dev/null | xargs -I{} basename {} .pt | tr '\n' ' ')
    echo -e "  Whisper      : ${GREEN}offline ✓${NC} (models: ${MODELS:-belum ter-cache})"
else
    echo -e "  Whisper      : ${YELLOW}tidak ditemukan${NC}"
fi

# Check FFmpeg
FFPATH="${FFMPEG_BIN:-ffmpeg}"
if command -v "$FFPATH" &>/dev/null || [ -f "$FFPATH" ]; then
    echo -e "  FFmpeg       : ${GREEN}${FFPATH} ✓${NC}"
else
    echo -e "  FFmpeg       : ${RED}tidak ditemukan di ${FFPATH}${NC}"
fi

echo ""
echo -e "${GREEN}🚀 Server: http://127.0.0.1:8000${NC}"
echo -e "   Login: admin@cliptrend.local / password"
echo -e "   Admin: http://127.0.0.1:8000/admin/dashboard"
echo -e "${YELLOW}   Ctrl+C untuk berhenti.${NC}"
echo ""

# Trap to automatically kill the background queue worker when the script exits
trap 'echo -e "${YELLOW}🛑 Stopping background queue worker...${NC}"; kill $(jobs -p) 2>/dev/null || true; exit' INT TERM EXIT

echo -e "${CYAN}→ Menjalankan background queue worker...${NC}"
php -c php-local.ini artisan queue:work --queue=analysis,rendering,default --tries=3 --timeout=1800 > storage/logs/queue-worker.log 2>&1 &
echo -e "${GREEN}✓ Queue worker siap (logging ke storage/logs/queue-worker.log)${NC}"
echo ""

export PHP_CLI_SERVER_WORKERS=6
php -c php-local.ini -S 127.0.0.1:8000 -t public
