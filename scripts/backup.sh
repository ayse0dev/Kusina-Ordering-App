#!/usr/bin/env bash
# Backs up the Kusina database and uploaded photos. Run from anywhere; safe to run from cron.
#
#   ./scripts/backup.sh
#
# Settings (environment variables, all optional):
#   BACKUP_DIR     where to keep backups on this server   (default: ~/kusina-backups)
#   KEEP_DAYS      delete local backups older than this    (default: 14)
#   RCLONE_REMOTE  copy backups OFF the server with rclone, e.g. "b2:kusina-backups" (default: off)
#
# IMPORTANT: a backup that only lives on the same server is not a backup. Set RCLONE_REMOTE
# (see DEPLOY-ORACLE.md) or copy the files elsewhere regularly.
set -euo pipefail

cd "$(dirname "$0")/.."
OUT="${BACKUP_DIR:-$HOME/kusina-backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%F-%H%M)"
DC=(docker compose --env-file .env.production)

mkdir -p "$OUT"
umask 077   # backups contain customer data: owner-only

echo "[$(date -Is)] backing up database..."
"${DC[@]}" exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines "$MARIADB_DATABASE"' \
    | gzip > "$OUT/db-$STAMP.sql.gz"

echo "[$(date -Is)] backing up uploaded photos..."
"${DC[@]}" exec -T web tar czf - -C /var/www/html/storage/app public > "$OUT/uploads-$STAMP.tgz"

# Refuse to call it a success if a file is empty/tiny (a failed dump can still exit quietly through a pipe).
for f in "$OUT/db-$STAMP.sql.gz" "$OUT/uploads-$STAMP.tgz"; do
    if [ ! -s "$f" ] || [ "$(stat -c %s "$f")" -lt 100 ]; then
        echo "ERROR: $f is empty or too small; backup FAILED" >&2
        exit 1
    fi
done
if ! gzip -t "$OUT/db-$STAMP.sql.gz"; then echo "ERROR: database dump is corrupt" >&2; exit 1; fi

find "$OUT" -type f \( -name 'db-*.sql.gz' -o -name 'uploads-*.tgz' \) -mtime +"$KEEP_DAYS" -delete

if [ -n "${RCLONE_REMOTE:-}" ]; then
    echo "[$(date -Is)] copying to $RCLONE_REMOTE ..."
    rclone copy "$OUT" "$RCLONE_REMOTE" --include "*-$STAMP.*" --transfers 2
fi

echo "[$(date -Is)] done: $(ls -lh "$OUT"/*-"$STAMP".* | awk '{print $5, $9}' | tr '\n' ' ')"
