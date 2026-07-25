#!/usr/bin/env bash
set -euo pipefail

BASE=/var/www/stratedgepronos.fr/public_html/admin
MOD="$BASE/edge-finder-tennis"
WRAPPER="$MOD/index.php"
CSS="$MOD/assets/tennis-v4.css"
FLAG_JS="$BASE/assets/tennis-country-flags.js"
RAW_BASE="https://raw.githubusercontent.com/stratedgepronos-code/stratedge-site/master"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="$MOD/.backups/ui-fix-$STAMP"

for required in "$WRAPPER" "$CSS"; do
  if [[ ! -f "$required" ]]; then
    echo "PATCH_ERROR missing=$required" >&2
    exit 1
  fi
done

mkdir -p "$BACKUP" "$BASE/assets"
cp -a "$WRAPPER" "$BACKUP/index.php"
cp -a "$CSS" "$BACKUP/tennis-v4.css"
[[ ! -f "$FLAG_JS" ]] || cp -a "$FLAG_JS" "$BACKUP/tennis-country-flags.js"

curl -fsSL "$RAW_BASE/public_html/admin/assets/tennis-country-flags.js?ts=$STAMP" -o /tmp/tennis-country-flags.js
install -m 0644 /tmp/tennis-country-flags.js "$FLAG_JS"

python3 - "$WRAPPER" "$CSS" <<'PY'
import re
import sys
from pathlib import Path

wrapper = Path(sys.argv[1])
css = Path(sys.argv[2])

script_tag = '<script src="/panel-x9k3m/assets/tennis-country-flags.js?v=20260725-iframe4"></script>'
text = wrapper.read_text(encoding='utf-8')
text = re.sub(
    r'\s*<script[^>]*tennis-country-flags\.js[^>]*></script>',
    '',
    text,
    flags=re.IGNORECASE,
)
if '</body>' not in text:
    raise SystemExit('Balise </body> introuvable dans le wrapper Tennis')
text = text.replace('</body>', f'    {script_tag}\n</body>', 1)
wrapper.write_text(text, encoding='utf-8')

marker_start = '/* STRATEDGE_MOBILE_FILTER_STATIC_START */'
marker_end = '/* STRATEDGE_MOBILE_FILTER_STATIC_END */'
block = '''/* STRATEDGE_MOBILE_FILTER_STATIC_START */
@media (max-width: 760px) {
  .toolbar,
  .filter-panel,
  .filters-panel,
  [class*="filter-panel"] {
    position: static !important;
    inset: auto !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    z-index: auto !important;
    transform: none !important;
    max-height: none !important;
    overflow: visible !important;
    margin-bottom: 18px !important;
  }
}
/* STRATEDGE_MOBILE_FILTER_STATIC_END */'''

css_text = css.read_text(encoding='utf-8')
pattern = re.compile(re.escape(marker_start) + r'.*?' + re.escape(marker_end), re.DOTALL)
if pattern.search(css_text):
    css_text = pattern.sub(block, css_text)
else:
    css_text = css_text.rstrip() + '\n\n' + block + '\n'
css.write_text(css_text, encoding='utf-8')
PY

php -l "$WRAPPER"
grep -q 'tennis-country-flags.js?v=20260725-iframe4' "$WRAPPER"
grep -q 'STRATEDGE_MOBILE_FILTER_STATIC_START' "$CSS"
grep -q 'setupDocument' "$FLAG_JS"

echo "PATCH_OK backup=$BACKUP"
echo "wrapper=$(stat -c%s "$WRAPPER") css=$(stat -c%s "$CSS") js=$(stat -c%s "$FLAG_JS")"
