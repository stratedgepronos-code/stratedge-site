#!/usr/bin/env bash
# Mise à jour ciblée du moteur V3 déjà installé ; aucune configuration modifiée.
set -euo pipefail
repo_root="${1:?Racine du dépôt requise}"
source_file="$repo_root/live90/server/scenario_engine.py"
target_file=/opt/stratedge/live90/scenario_engine.py
service_name=seuil90-live.service
previous_sha=3cc13817efc1535a2d958e44b9c7dc160700bf5868c7a853dff7e7526ae8e538

if [[ ! -f "$target_file" ]]; then
  echo "LIVE90_CARDS_SKIP: moteur non installé"
  exit 0
fi
if cmp -s "$source_file" "$target_file"; then
  echo "LIVE90_CARDS_OK: moteur déjà à jour"
  exit 0
fi
if [[ "$EUID" != 0 ]]; then
  echo "LIVE90_CARDS_ERROR: exécuter avec sudo" >&2
  exit 1
fi
if [[ "$(sha256sum "$target_file" | cut -d ' ' -f 1)" != "$previous_sha" ]]; then
  echo "LIVE90_CARDS_ERROR: version installée différente ; comparaison nécessaire avant remplacement" >&2
  exit 1
fi
python3 - "$source_file" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1])
compile(p.read_text(), str(p), 'exec')
PY
was_active=0
if systemctl is-active --quiet "$service_name"; then was_active=1; fi
backup_dir=$(mktemp -d /var/backups/stratedge-live90-cards.XXXXXX)
cp -p "$target_file" "$backup_dir/scenario_engine.py"
stage_file=$(mktemp /opt/stratedge/live90/.scenario_engine.XXXXXX)
trap 'rm -f "$stage_file"' EXIT
install -m 0644 -o root -g root "$source_file" "$stage_file"
mv -f "$stage_file" "$target_file"
if [[ "$was_active" == 1 ]]; then
  if ! systemctl restart "$service_name" || ! systemctl is-active --quiet "$service_name"; then
    cp -p "$backup_dir/scenario_engine.py" "$stage_file"
    mv -f "$stage_file" "$target_file"
    systemctl restart "$service_name" || true
    echo "LIVE90_CARDS_ERROR: ancien moteur restauré après échec du redémarrage" >&2
    exit 1
  fi
fi
cmp "$source_file" "$target_file"
echo "LIVE90_CARDS_OK: correctif installé ; service auparavant actif=$was_active ; sauvegarde=$backup_dir"
