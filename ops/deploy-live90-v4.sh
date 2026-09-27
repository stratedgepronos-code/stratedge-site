#!/usr/bin/env bash
# Remplacement ciblé du moteur Live90 ; schéma SQLite additif et sauvegardes privées.
set -euo pipefail
repo_root="${1:?Racine du dépôt requise}"
target_dir=/opt/stratedge/live90
service_name=seuil90-live.service
if [[ "$EUID" != 0 ]]; then echo 'LIVE90_V4_ERROR: sudo requis' >&2; exit 1; fi
if [[ ! -f "$target_dir/engine.py" ]]; then echo 'LIVE90_V4_ERROR: installation Live90 absente' >&2; exit 1; fi
for file in engine.py live_v4.py; do
  python3 - "$repo_root/live90/server/$file" <<'PY'
import pathlib,sys
p=pathlib.Path(sys.argv[1]);compile(p.read_text(),str(p),'exec')
PY
done
if cmp -s "$repo_root/live90/server/engine.py" "$target_dir/engine.py" && cmp -s "$repo_root/live90/server/live_v4.py" "$target_dir/live_v4.py"; then
  echo 'LIVE90_V4_OK: fichiers moteur déjà à jour'
  exit 0
fi
backup_dir=$(mktemp -d /var/backups/stratedge-live90-v4.XXXXXX)
cp -p "$target_dir/engine.py" "$backup_dir/engine.py"
if [[ -f "$target_dir/live_v4.py" ]]; then cp -p "$target_dir/live_v4.py" "$backup_dir/live_v4.py"; fi
php_core="$repo_root/public_html/api/live90/core.php"
db_path=$(php -r 'require $argv[1]; echo config90()["SE90_DB"] ?? "/var/lib/stratedge/live90.sqlite";' "$php_core")
python3 - "$db_path" "$backup_dir/live90.sqlite" <<'PY'
import pathlib,sqlite3,sys
source=sqlite3.connect(pathlib.Path(sys.argv[1]).as_uri()+'?mode=ro',uri=True,timeout=30)
target=sqlite3.connect(sys.argv[2]);source.backup(target);target.close();source.close()
pathlib.Path(sys.argv[2]).chmod(0o600)
PY
was_active=0
if systemctl is-active --quiet "$service_name"; then was_active=1; fi
# Tous les fichiers source requis par PHP restent lisibles après un checkout.
find "$repo_root/live90/server" "$repo_root/public_html/admin/slate/live90" "$repo_root/public_html/api/live90" -type d -exec chmod a+rx {} +
find "$repo_root/live90/server" "$repo_root/public_html/admin/slate/live90" "$repo_root/public_html/api/live90" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.html' -o -name '*.py' \) -exec chmod a+r {} +
chmod a+r "$repo_root/live90/PROMPT_ANALYSTE_V4.md" "$repo_root/live90/FILTRES_LIVE_V4.md" "$repo_root/live90/live90-packball.user.js"
rollback(){
  trap - ERR
  cp -p "$backup_dir/engine.py" "$target_dir/.engine.rollback"
  mv -f "$target_dir/.engine.rollback" "$target_dir/engine.py"
  if [[ -f "$backup_dir/live_v4.py" ]]; then cp -p "$backup_dir/live_v4.py" "$target_dir/live_v4.py"; fi
  if [[ "$was_active" == 1 ]]; then systemctl restart "$service_name" || true; fi
  echo "LIVE90_V4_ERROR: ancien point d’entrée restauré ; sauvegarde=$backup_dir" >&2
  exit 1
}
trap rollback ERR
# Installer la dépendance avant de commuter le point d’entrée.
for file in live_v4.py engine.py; do
  stage_file=$(mktemp "$target_dir/.v4-stage.XXXXXX")
  install -m 0644 -o root -g root "$repo_root/live90/server/$file" "$stage_file"
  mv -f "$stage_file" "$target_dir/$file"
done
sudo -u www-data /usr/bin/python3 "$target_dir/live_v4.py" "$db_path" --init
if [[ "$was_active" == 1 ]]; then
  systemctl restart "$service_name"
  systemctl is-active --quiet "$service_name"
fi
trap - ERR
cmp "$repo_root/live90/server/live_v4.py" "$target_dir/live_v4.py"
echo "LIVE90_V4_OK: moteur installé ; service auparavant actif=$was_active ; sauvegarde=$backup_dir"
# Le diagnostic ne lit ni n’affiche les clés ; aucune alerte de test envoyée.
sudo -u www-data /usr/bin/python3 "$target_dir/live_v4.py" "$db_path" --diagnostic
