#!/usr/bin/env bash
# ============================================================
# Deploy MarkazHub (PHP) ke shared hosting via FTP + import DB.
#
# Bisa dijalankan dari mesin mana pun yang punya akses internet
# (komputer Anda, server CI, atau environment ini jika egress dibuka).
#
# Kredensial dibaca dari environment variable (JANGAN ditaruh di file):
#   FTP_HOST   contoh: 153.92.9.1
#   FTP_USER   contoh: markazhub@markazhub.mkz.my.id
#   FTP_PASS   password FTP
#   FTP_DIR    folder tujuan di server (default: public_html)
#   DB_HOST DB_NAME DB_USER DB_PASS  -> untuk import schema.sql (opsional)
#
# Cara pakai:
#   export FTP_HOST=... FTP_USER=... FTP_PASS=...
#   ./deploy.sh
# ============================================================
set -euo pipefail

# Pakai ${VAR-default} (tanpa titik dua) supaya FTP_DIR='' (root) dihormati,
# bukan diganti default. Akun FTP di sini sudah chroot ke web root.
FTP_DIR="${FTP_DIR-public_html}"
SRC="$(cd "$(dirname "$0")" && pwd)"

: "${FTP_HOST:?set FTP_HOST}"
: "${FTP_USER:?set FTP_USER}"
: "${FTP_PASS:?set FTP_PASS}"

echo "==> Upload file aplikasi ke ftp://$FTP_HOST/$FTP_DIR"

# Daftar file yang diupload (kecuali file yang tidak perlu di server).
cd "$SRC"
find . -type f \
  ! -name 'deploy.sh' \
  ! -name 'README.md' \
  ! -name 'config.sample.php' \
  ! -path './.git/*' \
| while read -r f; do
    rel="${f#./}"
    # FTP_DIR boleh kosong (akun FTP yang sudah chroot ke web root).
    if [[ -n "$FTP_DIR" ]]; then dest="$FTP_DIR/$rel"; else dest="$rel"; fi
    echo "    -> $dest"
    curl -sS --ftp-pasv --ftp-create-dirs -T "$f" \
      --user "$FTP_USER:$FTP_PASS" \
      "ftp://$FTP_HOST/$dest"
  done

echo "==> Upload selesai."

# Import database bila kredensial MySQL tersedia & mysql client ada.
if [[ -n "${DB_HOST:-}" && -n "${DB_NAME:-}" && -n "${DB_USER:-}" && -n "${DB_PASS:-}" ]]; then
  if command -v mysql >/dev/null 2>&1; then
    echo "==> Import schema.sql ke database $DB_NAME @ $DB_HOST"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SRC/schema.sql"
    echo "==> Import database selesai."
  else
    echo "!! mysql client tidak ada; lewati import DB. Import schema.sql manual via phpMyAdmin."
  fi
else
  echo "!! Kredensial DB tidak di-set; lewati import. Import schema.sql via phpMyAdmin."
fi

echo "==> Selesai. Buka https://markazhub.mkz.my.id"
