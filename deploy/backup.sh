#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# backup.sh — backup database + dokumen + .env
#
# Pemakaian:
#   ./deploy/backup.sh                 # backup penuh
#   ./deploy/backup.sh --db-only       # hanya database
#   ./deploy/backup.sh --docs-only     # hanya dokumen PDF
#   ./deploy/backup.sh --restore FILE  # pulihkan database dari .sql.gz
#
# Retensi: simpan 14 backup terakhir dari tiap jenis.
# Jadwal harian (crontab -e):
#   0 2 * * * /path/project/deploy/backup.sh >> /var/log/pemberkasan-backup.log 2>&1
# ─────────────────────────────────────────────────────────────────────────────
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE="docker compose"
BACKUP_DIR="${ROOT}/backup"
KEEP="${BACKUP_KEEP:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
MODE="${1:-all}"

GRN=$'\e[32m'; YEL=$'\e[33m'; CYN=$'\e[36m'; RED=$'\e[31m'; RST=$'\e[0m'
log() { echo "${CYN}[backup]${RST} $*"; }
ok()  { echo "${GRN}[ ok ]${RST} $*"; }
warn(){ echo "${YEL}[warn]${RST} $*"; }
die() { echo "${RED}[fail]${RST} $*" >&2; exit 1; }

trap 'die "gagal di baris $LINENO: $BASH_COMMAND"' ERR

mkdir -p "$BACKUP_DIR"

# ── Restore mode ─────────────────────────────────────────────────────────────
if [ "$MODE" = "--restore" ]; then
    FILE="${2:-}"
    [ -n "$FILE" ] || die "pemakaian: ./deploy/backup.sh --restore backup/db-....sql.gz"
    [ -f "$FILE" ] || die "file tidak ditemukan: $FILE"
    echo "${YEL}PERINGATAN:${RST} database saat ini akan DITIMPA dari $FILE"
    read -r -p "Ketik 'ya' untuk lanjut: " confirm
    [ "$confirm" = "ya" ] || die "dibatalkan"
    gunzip -c "$FILE" | $COMPOSE exec -T db sh -c \
        'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
    ok "restore dari $FILE selesai"
    exit 0
fi

# ── Database ─────────────────────────────────────────────────────────────────
if [ "$MODE" = "all" ] || [ "$MODE" = "--db-only" ]; then
    $COMPOSE ps --status running --quiet db | grep -q . || die "container db tidak berjalan"
    OUT="$BACKUP_DIR/db-$STAMP.sql.gz"
    log "dump database ..."
    $COMPOSE exec -T db sh -c \
        'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines "$MYSQL_DATABASE"' \
        | gzip > "$OUT"
    # sanity: file tidak boleh kosong / dump rusak
    [ -s "$OUT" ] || die "file backup kosong"
    gzip -t "$OUT" || die "file backup rusak (gzip test gagal)"
    ok "$OUT ($(du -h "$OUT" | cut -f1))"
fi

# ── Dokumen PDF ──────────────────────────────────────────────────────────────
if [ "$MODE" = "all" ] || [ "$MODE" = "--docs-only" ]; then
    PROJ="$(basename "$ROOT" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')"
    VOL="${PROJ}_documents"
    docker volume inspect "$VOL" >/dev/null 2>&1 || die "volume $VOL tidak ada"
    OUT="$BACKUP_DIR/documents-$STAMP.tar.gz"
    log "arsip volume dokumen ..."
    docker run --rm \
        -v "$VOL":/src:ro \
        -v "$BACKUP_DIR":/backup \
        alpine:3.20 tar czf "/backup/$(basename "$OUT")" -C /src .
    [ -s "$OUT" ] || die "arsip dokumen kosong"
    ok "$OUT ($(du -h "$OUT" | cut -f1))"
fi

# ── Konfigurasi (.env) ───────────────────────────────────────────────────────
if [ "$MODE" = "all" ]; then
    OUT="$BACKUP_DIR/env-$STAMP.txt"
    # .env berisi password — batasi permission
    (umask 077; cp .env "$OUT")
    ok "$OUT (hanya root yang bisa baca)"
fi

# ── Retensi ─────────────────────────────────────────────────────────────────
for prefix in db documents env; do
    count=$(find "$BACKUP_DIR" -maxdepth 1 -name "${prefix}-*" -type f | wc -l)
    if [ "$count" -gt "$KEEP" ]; then
        find "$BACKUP_DIR" -maxdepth 1 -name "${prefix}-*" -type f \
            -printf '%T@ %p\n' | sort -n | head -n -"$KEEP" | cut -d' ' -f2- \
            | while read -r old; do rm -f "$old"; log "hapus backup lama: $(basename "$old")"; done
    fi
done

echo
ok "Backup selesai di $BACKUP_DIR (retensi $KEEP terakhir per jenis)"
echo "   Total ukuran: $(du -sh "$BACKUP_DIR" | cut -f1)"
