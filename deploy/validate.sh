#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# validate.sh — periksa konsistensi struktur Docker SEBELUM build
#
# Menangkap kesalahan yang biasanya baru ketahuan saat produksi:
#   - .env kurang key wajib / masih nilai development
#   - batas upload tidak konsisten (env vs php.ini vs nginx)
#   - volume yang di-mount tapi tidak dideklarasikan
#   - permintaan RAM melebihi kapasitas VPS
#   - script kehilangan bit executable / shebang
#
# Pemakaian: ./deploy/validate.sh
# Exit code 0 = siap deploy, 1 = ada masalah.
# ─────────────────────────────────────────────────────────────────────────────
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RED=$'\e[31m'; GRN=$'\e[32m'; YEL=$'\e[33m'; CYN=$'\e[36m'; RST=$'\e[0m'
fail=0
pass=0

ok()   { echo "  ${GRN}PASS${RST}  $*"; pass=$((pass+1)); }
bad()  { echo "  ${RED}FAIL${RST}  $*"; fail=$((fail+1)); }
warn() { echo "  ${YEL}WARN${RST}  $*"; }

echo "${CYN}══ 1. File wajib ══${RST}"
for f in Dockerfile docker-compose.yml .dockerignore .env.example; do
    [ -f "$f" ] && ok "$f ada" || bad "$f HILANG"
done
for f in deploy/php.ini deploy/php-fpm-pool.conf deploy/nginx.conf \
         deploy/entrypoint.sh deploy/deploy.sh deploy/backup.sh; do
    [ -f "$f" ] && ok "$f ada" || bad "$f HILANG"
done

echo
echo "${CYN}══ 2. Script executable & shebang ══${RST}"
for f in deploy/*.sh; do
    # Tiga bentuk shebang sah:
    #   #!/usr/bin/env bash  → script operator (deploy/backup/validate)
    #   #!/usr/bin/env sh     → script portable
    #   #!/bin/sh            → script yang jalan DI DALAM image (Alpine tanpa bash)
    shebang=$(head -1 "$f")
    if echo "$shebang" | grep -qE '^#!/(usr/)?bin/(env )?(bash|sh)$'; then
        ok "$(basename "$f") shebang: ${shebang#\#\!}"
    else
        bad "$(basename "$f") shebang tidak dikenal: $shebang"
    fi
    if [ -x "$f" ]; then
        ok "$(basename "$f") executable"
    else
        bad "$(basename "$f") tidak executable → chmod +x $f"
    fi
    if head -1 "$f" | grep -q 'bash'; then
        bash -n "$f" 2>/dev/null && ok "$(basename "$f") sintaks bash valid" \
            || bad "$(basename "$f") sintaks bash error"
    else
        sh -n "$f" 2>/dev/null && ok "$(basename "$f") sintaks POSIX sh valid" \
            || bad "$(basename "$f") sintaks sh error"
    fi
done

echo
echo "${CYN}══ 3. Sintaks compose & YAML ══${RST}"
if command -v docker >/dev/null && docker compose version >/dev/null 2>&1; then
    docker compose config --quiet 2>/dev/null && ok "docker compose config valid" \
        || bad "docker compose config error"
else
    warn "docker tidak tersedia — lewati validasi compose (jalankan di server)"
fi

echo
echo "${CYN}══ 4. Konsistensi batas upload ══${RST}"
php_umf=$(grep -oP '^upload_max_filesize\s*=\s*\K\S+' deploy/php.ini 2>/dev/null | tr -d ';')
php_pms=$(grep -oP '^post_max_size\s*=\s*\K\S+' deploy/php.ini 2>/dev/null | tr -d ';')
ngx_cmb=$(grep -oP 'client_max_body_size\s+\K\S+(?=;)' deploy/nginx.conf 2>/dev/null)
env_mfs=$(grep -oP '^MAX_FILE_SIZE=\K\d+' .env 2>/dev/null || grep -oP '^MAX_FILE_SIZE=\K\d+' .env.example 2>/dev/null)

to_mb() { echo "${1%[Mm]}" | cut -d. -f1; }

[ -n "$php_umf" ] && ok "php.ini upload_max_filesize = $php_umf" || bad "php.ini upload_max_filesize tidak terbaca"
[ -n "$php_pms" ] && ok "php.ini post_max_size = $php_pms" || bad "php.ini post_max_size tidak terbaca"
[ -n "$ngx_cmb" ] && ok "nginx client_max_body_size = $ngx_cmb" || bad "nginx client_max_body_size tidak terbaca"
[ -n "$env_mfs" ] && ok "env MAX_FILE_SIZE = $env_mfs KB" || bad "MAX_FILE_SIZE tidak ditemukan"

if [ -n "$env_mfs" ] && [ -n "$php_umf" ]; then
    env_mb=$((env_mfs / 1024))
    [ "$env_mb" -le "$(to_mb "$php_umf")" ] \
        && ok "MAX_FILE_SIZE ($env_mb MB) <= upload_max_filesize" \
        || bad "MAX_FILE_SIZE ($env_mb MB) > upload_max_filesize ($php_umf) → upload besar ditolak PHP"
fi
if [ -n "$php_pms" ] && [ -n "$ngx_cmb" ]; then
    [ "$(to_mb "$php_pms")" -le "$(to_mb "$ngx_cmb")" ] \
        && ok "post_max_size <= client_max_body_size" \
        || bad "post_max_size ($php_pms) > client_max_body_size ($ngx_cmb) → error 413"
fi

echo
echo "${CYN}══ 5. Anggaran RAM ══${RST}"
mem_line=$(grep -oP 'memory:\s*\K\S+' docker-compose.yml 2>/dev/null)
total=0
for m in $mem_line; do
    mb=$(echo "$m" | tr -d 'M')
    case "$mb" in ''|*[!0-9]*) continue;; esac
    total=$((total + mb))
done
fpm_workers=$(grep -oP 'pm\.max_children\s*=\s*\K\d+' deploy/php-fpm-pool.conf 2>/dev/null)
php_mem=$(grep -oP '^memory_limit\s*=\s*\K\d+' deploy/php.ini 2>/dev/null)
echo "  Total batas memori container : ${total} MB"
[ -n "$fpm_workers" ] && [ -n "$php_mem" ] && \
    echo "  FPM worst-case (${fpm_workers}x${php_mem}M)   : $((fpm_workers * php_mem)) MB"
if [ "$total" -le 1600 ]; then
    ok "anggaran ${total} MB muat di VPS 2 GB"
else
    bad "anggaran ${total} MB terlalu besar untuk RAM 2 GB"
fi
if [ -n "$fpm_workers" ] && [ -n "$php_mem" ] && [ $((fpm_workers * php_mem)) -gt 768 ]; then
    warn "FPM worst-case melebihi limit container app (768M) — worker bisa di-kill OOM"
fi

echo
echo "${CYN}══ 6. .env (jika ada) ══${RST}"
if [ -f .env ]; then
    env_is_local=0
    grep -qE '^APP_ENV=local' .env && env_is_local=1

    if [ "$env_is_local" = "1" ]; then
        echo "  (mode lokal terdeteksi — sebagian pemeriksaan produksi dilewati)"
        # Di dev, MySQL lokal biasanya root tanpa password dan APP_DEBUG=true itu normal.
        for key in APP_KEY JWT_SECRET DB_DATABASE; do
            val=$(grep -E "^${key}=" .env | head -1 | cut -d= -f2- | tr -d '\r')
            [ -n "$val" ] && ok "$key terisi" || bad "$key kosong (login akan gagal)"
        done
        warn "APP_DEBUG=true & DB_PASSWORD kosong wajar di lokal — pakai .env.deploy untuk Docker"
        # Yang tetap berbahaya: JWT_SECRET kosong karena membuat login crash
        jl=$(grep -E '^JWT_SECRET=' .env | cut -d= -f2- | tr -d '\r' | wc -c)
        [ "$jl" -ge 33 ] && ok "JWT_SECRET panjang memadai" \
            || bad "JWT_SECRET kosong/terlalu pendek → error 'Secret is not set'"
    else
        for key in APP_KEY JWT_SECRET DB_DATABASE DB_PASSWORD DB_ROOT_PASSWORD; do
            val=$(grep -E "^${key}=" .env | head -1 | cut -d= -f2- | tr -d '\r')
            [ -n "$val" ] && ok "$key terisi" || bad "$key kosong"
        done
        grep -qE '^APP_DEBUG=true' .env && bad "APP_DEBUG=true di produksi" || ok "APP_DEBUG aman"
        grep -qE '^DB_HOST=127\.0\.0\.1' .env && \
            bad "DB_HOST=127.0.0.1 — di dalam Docker harus 'db' (lihat .env.deploy)" || ok "DB_HOST sesuai Docker"
        grep -qE '^SESSION_DRIVER=file' .env && \
            warn "SESSION_DRIVER=file — session hilang saat restart (disarankan redis)" || ok "SESSION_DRIVER redis"
        jl=$(grep -E '^JWT_SECRET=' .env | cut -d= -f2- | tr -d '\r' | wc -c)
        [ "$jl" -ge 33 ] && ok "JWT_SECRET panjang memadai" \
            || bad "JWT_SECRET kosong/terlalu pendek"
    fi
else
    warn ".env belum ada — salin .env.example lalu isi (wajib sebelum deploy)"
fi

echo
echo "${CYN}══ 7. Volume & mount ══${RST}"
declared=$(sed -n '/^volumes:/,/^[a-z]/p' docker-compose.yml | grep -oP '^  \K[a-z][\w-]*(?=:)' | sort -u)
for v in $(grep -oP '^\s+- \K[a-z][\w-]*(?=:/)' docker-compose.yml | sort -u); do
    echo "$declared" | grep -qx "$v" \
        && ok "volume '$v' dideklarasikan" \
        || bad "volume '$v' di-mount tapi tidak ada di blok volumes:"
done

echo
echo "${CYN}══ 8. Keamanan dasar ══${RST}"
[ -f .dockerignore ] && grep -qx '.env' .dockerignore \
    && ok ".env tidak ikut masuk image" || bad ".env TIDAK ada di .dockerignore"
grep -q 'expose_php = Off' deploy/php.ini && ok "expose_php=Off" || bad "expose_php masih On"
grep -q 'opcache.enable = 1' deploy/php.ini && ok "OPcache aktif" || warn "OPcache nonaktif"
grep -q 'client_max_body_size' deploy/nginx.conf && ok "batas body nginx diset" || bad "client_max_body_size tidak ada"
grep -q 'location ~ /\\.' deploy/nginx.conf && ok "akses file dot diblokir" || warn ".env/.git bisa diakses lewat nginx"

echo
echo "${CYN}─────────────────────────────────────────────${RST}"
if [ "$fail" -eq 0 ]; then
    echo "${GRN}LULUS${RST} — ${pass} pemeriksaan lolos, siap deploy."
    exit 0
else
    echo "${RED}GAGAL${RST} — ${fail} masalah, ${pass} lolos. Perbaiki dulu sebelum build."
    exit 1
fi
