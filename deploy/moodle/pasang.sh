#!/usr/bin/env bash
# =============================================================================
#  Pasang setelan Moodle CBT di server ini (dijalankan oleh deploy/setelan-moodle.sh).
#    - plugin Moodle dari folder moodle/local (mis. local_cbtblokir)
#    - setelan, izin, tampilan, CSS tema, bahasa (deploy/moodle/setelan.php)
#  Aman dijalankan berulang kali. Di server cadangan HA hanya kode plugin yang
#  dipasang; setelan ikut tersalin dari server utama lewat replikasi database.
#
#  Pakai (sebagai root):  bash pasang.sh [folder-sumber]
# =============================================================================
set -euo pipefail

SRC="${1:-$(cd "$(dirname "$0")/../.." && pwd)}"
MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
CONFIG="$MOODLE_DIR/config.php"

log() { echo -e "\n\033[1;32m==> $*\033[0m"; }
die() { echo -e "\033[1;31m[GAGAL] $*\033[0m" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Jalankan sebagai root."
[ -f "$CONFIG" ] || die "Moodle tidak ditemukan di $MOODLE_DIR (jalankan install-moodle.sh dulu)."
[ -f "$SRC/deploy/moodle/setelan.php" ] || die "Berkas setelan tidak ditemukan di $SRC."

# Moodle 5.x menyimpan kode web di public/; versi lama langsung di folder Moodle.
WEB_DIR="$MOODLE_DIR/public"; [ -d "$WEB_DIR/local" ] || WEB_DIR="$MOODLE_DIR"

log "Plugin Moodle"
for plugin in "$SRC"/moodle/local/*/; do
    [ -f "$plugin/version.php" ] || continue
    name="$(basename "$plugin")"
    rm -rf "$WEB_DIR/local/$name"
    cp -r "$plugin" "$WEB_DIR/local/$name"
    find "$WEB_DIR/local/$name" -type f -exec sed -i 's/\r$//' {} +
    chown -R root:root "$WEB_DIR/local/$name"
    chmod -R a+rX "$WEB_DIR/local/$name"
    echo "  - local_$name disalin"
done

if [ "$(cat /etc/cbt-ha-peran 2>/dev/null)" = cadangan ]; then
    log "Server cadangan HA: setelan ikut tersalin dari server utama, tidak dipasang di sini"
    exit 0
fi

PHP=(runuser -u www-data -- php)
log "Pasang/perbarui plugin di database Moodle"
"${PHP[@]}" "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive | tail -2

log "Setelan Moodle"
# Salin ke folder sementara yang bisa dibaca www-data (folder home tidak selalu bisa).
TMP="$(mktemp -d /tmp/cbt-setelan.XXXXXX)"
mkdir -p "$TMP/lang"
for f in setelan.php boost.scss; do sed 's/\r$//' "$SRC/deploy/moodle/$f" > "$TMP/$f"; done
for f in "$SRC"/deploy/moodle/lang/*.php; do sed 's/\r$//' "$f" > "$TMP/lang/$(basename "$f")"; done
chown -R www-data:www-data "$TMP"
"${PHP[@]}" "$TMP/setelan.php" "$CONFIG"
rm -rf "$TMP"

echo
echo "============================================================"
echo "  SETELAN MOODLE CBT TERPASANG"
echo "  Buka Moodle dan tekan Ctrl+F5 supaya CSS baru dimuat."
echo "============================================================"
