#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# deploy.sh — deploy/update Sistem Pemberkasan di VPS (Docker)
#
# Pemakaian:
#   ./deploy/deploy.sh            # deploy penuh (build + migrate + cache)
#   ./deploy/deploy.sh --quick    # tanpa rebuild image (hanya kode + migrate)
#   ./deploy/deploy.sh --rollback # kembali ke image sebelumnya
#
# Aman dijalankan berulang. Berhenti di error pertama.
# ─────────────────────────────────────────────────────────────────────────────
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE="docker compose"
BACKUP_DIR="${ROOT}/backup"
STAMP="$(date +%Y%m%d-%H%M%S)"
MODE="${1:-full}"

RED=$'\e[31m'; GRN=$'\e[32m'; YEL=$'\e[33m'; CYN=$'\e[36m'; RST=$'\e[0m'
log()  { echo "${CYN}[deploy]${RST} $*"; }
ok()   { echo "${GRN}[ ok ]${RST} $*"; }
warn() { echo "${YEL}[warn]${RST} $*"; }
die()  { echo "${RED}[fail]${RST} $*" >&2; exit 1; }

trap 'die "gagal di baris $LINENO: $BASH_COMMAND"' ERR

# ── 0. Prasyarat ─────────────────────────────────────────────────────────────
command -v docker >/dev/null || die "docker tidak terpasang"
docker compose version >/dev/null 2>&1 || die "plugin 'docker compose' tidak tersedia"
[ -f .env ] || die ".env tidak ada. Salin dari .env.example lalu isi."
[ -f docker-compose.yml ] || die "docker-compose.yml tidak ada"

# ── 1. Validasi konfigurasi (berhenti sebelum menyentuh container) ───────────
log "Memvalidasi .env ..."
need=("APP_KEY" "JWT_SECRET" "DB_DATABASE" "DB_PASSWORD" "DB_ROOT_PASSWORD")
for key in "${need[@]}"; do
    val="$(grep -E "^${key}=" .env | head -1 | cut -d= -f2- | tr -d '\r')"
    [ -n "$val" ] || die "${key} kosong di .env"
done
grep -qE '^APP_KEY=base64:.{40,}' .env || die "APP_KEY tidak valid (harus mulai 'base64:')"
[ "$(grep -E '^JWT_SECRET=' .env | cut -d= -f2- | tr -d '\r' | wc -c)" -ge 32 ] \
    || die "JWT_SECRET terlalu pendek (minimal 32 karakter)"

if grep -qE '^APP_ENV=local' .env; then warn "APP_ENV=local — seharusnya production"; fi
if grep -qE '^APP_DEBUG=true' .env; then die "APP_DEBUG=true berbahaya di produksi"; fi
ok "konfigurasi valid"

# ── 2. Swap check (RAM 2 GB wajib punya swap) ────────────────────────────────
if [ -r /proc/meminfo ]; then
    swap_kb=$(awk '/SwapTotal/{print $2}' /proc/meminfo)
    mem_kb=$(awk '/MemTotal/{print $2}' /proc/meminfo)
    if [ "$mem_kb" -lt 3000000 ] && [ "$swap_kb" -lt 1000000 ]; then
        warn "RAM < 3 GB dan swap < 1 GB. Build bisa OOM. Lihat DEPLOY.md langkah 1."
    fi
fi

# ── 3. Rollback mode ─────────────────────────────────────────────────────────
if [ "$MODE" = "--rollback" ]; then
    log "Rollback: memakai tag image terakhir yang tersimpan (pemberkasan-app:previous)"
    docker image inspect pemberkasan-app:previous >/dev/null 2>&1 \
        || die "tidak ada image 'pemberkasan-app:previous' untuk rollback"
    docker tag pemberkasan-app:latest pemberkasan-app:failed 2>/dev/null || true
    docker tag pemberkasan-app:previous pemberkasan-app:latest
    $COMPOSE up -d --no-build app
    ok "rollback selesai — cek 'docker compose logs -f app'"
    exit 0
fi

# ── 4. Backup sebelum migrasi ────────────────────────────────────────────────
mkdir -p "$BACKUP_DIR"
if $COMPOSE ps --status running --quiet db 2>/dev/null | grep -q .; then
    log "Backup database sebelum deploy ..."
    if $COMPOSE exec -T db sh -c \
        'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick "$MYSQL_DATABASE"' \
        | gzip > "$BACKUP_DIR/db-pre-deploy-$STAMP.sql.gz"; then
        ok "backup: backup/db-pre-deploy-$STAMP.sql.gz ($(du -h "$BACKUP_DIR/db-pre-deploy-$STAMP.sql.gz" | cut -f1))"
    else
        die "backup gagal — deploy dibatalkan"
    fi
else
    warn "db belum jalan — backup dilewati (deploy pertama?)"
fi

# ── 5. Simpan image lama untuk rollback ─────────────────────────────────────
if docker image inspect pemberkasan-app:latest >/dev/null 2>&1; then
    docker tag pemberkasan-app:latest pemberkasan-app:previous
    log "image lama ditandai sebagai :previous (untuk --rollback)"
fi

# ── 6. Ambil kode terbaru ───────────────────────────────────────────────────
if [ -d .git ]; then
    log "git pull ..."
    git fetch --all --prune
    git pull --ff-only || warn "git pull gagal — lanjut dengan kode saat ini"
fi

# ── 7. Build & jalankan ─────────────────────────────────────────────────────
if [ "$MODE" = "--quick" ]; then
    log "Mode cepat: tanpa rebuild image"
    $COMPOSE up -d app nginx
else
    log "Build image ..."
    $COMPOSE build --pull app
    log "Menjalankan stack ..."
    $COMPOSE up -d
fi

# ── 8. Tunggu sehat ────────────────────────────────────────────────────────
log "Menunggu app healthcheck ..."
for i in $(seq 1 40); do
    st="$(docker inspect --format '{{.State.Health.Status}}' \
        "$($COMPOSE ps -q app 2>/dev/null)" 2>/dev/null || echo starting)"
    [ "$st" = "healthy" ] && break
    [ "$i" -eq 40 ] && die "app tidak sehat setelah 80 detik. Cek: docker compose logs app"
    sleep 2
done
ok "app healthy"

# ── 9. Verifikasi hasil ────────────────────────────────────────────────────
log "Verifikasi endpoint ..."
code="$(curl -s -o /dev/null -w '%{http_code}' -L --max-time 15 http://127.0.0.1/ || true)"
case "$code" in
    200) ok "HTTP 200 di /login" ;;
    000) warn "tidak bisa terhubung lewat 127.0.0.1:80 — cek proxy/firewall" ;;
    *)   die "respons tak terduga: HTTP $code. Cek: docker compose logs nginx" ;;
esac

$COMPOSE exec -T app php artisan migrate:status 2>/dev/null | grep -q Pending \
    && warn "masih ada migrasi Pending" \
    || ok "semua migrasi sudah jalan"

# ── 10. Bersihkan image lama (simpan 2 generasi) ────────────────────────────
docker image prune -f --filter "label!=keep" >/dev/null 2>&1 || true

echo
ok "Deploy selesai."
echo "   Log    : docker compose logs -f app"
echo "   Rollback: ./deploy/deploy.sh --rollback"
echo "   Backup : $BACKUP_DIR/"
