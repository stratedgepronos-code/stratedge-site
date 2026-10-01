#!/usr/bin/env bash
set -euo pipefail

BASE=/var/www/stratedgepronos.fr/public_html/admin
SIDEBAR="$BASE/sidebar.php"
ASSETS="$BASE/assets"
FLAG_JS="$ASSETS/tennis-country-flags.js"
BOOTSTRAP_JS="$ASSETS/tennis-ui-bootstrap.js"
RAW_BASE="https://raw.githubusercontent.com/stratedgepronos-code/stratedge-site/master"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="/home/alex/stratedge-backups/tennis-ui-$STAMP"

if [[ ! -f "$SIDEBAR" ]]; then
  echo "PATCH_ERROR missing=$SIDEBAR" >&2
  exit 1
fi

mkdir -p "$BACKUP" "$ASSETS"
cp -a "$SIDEBAR" "$BACKUP/sidebar.php"
[[ ! -f "$FLAG_JS" ]] || cp -a "$FLAG_JS" "$BACKUP/tennis-country-flags.js"
[[ ! -f "$BOOTSTRAP_JS" ]] || cp -a "$BOOTSTRAP_JS" "$BACKUP/tennis-ui-bootstrap.js"

curl -fsSL "$RAW_BASE/public_html/admin/assets/tennis-country-flags.js?ts=$STAMP" -o /tmp/tennis-country-flags.js
curl -fsSL "$RAW_BASE/public_html/admin/assets/tennis-ui-bootstrap.js?ts=$STAMP" -o /tmp/tennis-ui-bootstrap.js
install -m 0664 /tmp/tennis-country-flags.js "$FLAG_JS"
install -m 0664 /tmp/tennis-ui-bootstrap.js "$BOOTSTRAP_JS"

python3 - "$SIDEBAR" <<'PY'
import re
import sys
from pathlib import Path

sidebar = Path(sys.argv[1])
text = sidebar.read_text(encoding='utf-8')
start = '<!-- STRATEDGE_TENNIS_UI_BOOTSTRAP_START -->'
end = '<!-- STRATEDGE_TENNIS_UI_BOOTSTRAP_END -->'
block = '''<!-- STRATEDGE_TENNIS_UI_BOOTSTRAP_START -->
<?php if (($pageActive ?? '') === 'edge-finder-tennis'): ?>
<script defer src="/panel-x9k3m/assets/tennis-ui-bootstrap.js?v=20260725-2"></script>
<?php endif; ?>
<!-- STRATEDGE_TENNIS_UI_BOOTSTRAP_END -->'''
pattern = re.compile(re.escape(start) + r'.*?' + re.escape(end), re.DOTALL)
if pattern.search(text):
    text = pattern.sub(block, text)
else:
    text = text.rstrip() + '\n\n' + block + '\n'
sidebar.write_text(text, encoding='utf-8')
PY

php -l "$SIDEBAR"
grep -q 'STRATEDGE_TENNIS_UI_BOOTSTRAP_START' "$SIDEBAR"
grep -q 'tennis-ui-bootstrap.js?v=20260725-2' "$SIDEBAR"
grep -q 'setupDocument' "$FLAG_JS"
grep -q 'stratedge-tennis-mobile-filter-static' "$BOOTSTRAP_JS"

echo "PATCH_OK backup=$BACKUP"
echo "sidebar=$(stat -c%s "$SIDEBAR") flags=$(stat -c%s "$FLAG_JS") bootstrap=$(stat -c%s "$BOOTSTRAP_JS")"
