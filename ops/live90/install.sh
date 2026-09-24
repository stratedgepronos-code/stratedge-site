#!/usr/bin/env bash
set -euo pipefail
# Installation additive. Never overwrites the previous live system or shared env.
if [ "$(id -u)" -ne 0 ]; then echo 'Lancer avec sudo sur le serveur.'; exit 1; fi
website_root="${1:-/var/www/stratedgepronos.fr/public_html}"
release_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
command -v php >/dev/null
command -v python3 >/dev/null
php -r 'if (PHP_VERSION_ID < 80100 || !extension_loaded("pdo_sqlite")) {fwrite(STDERR,"PHP 8.1+ et pdo_sqlite requis\n");exit(1);}'
test -f "$website_root/includes/auth.php"
test -f /etc/stratedge/live.env
for destination in /var/lib/stratedge/live90.sqlite /opt/stratedge/live90 /etc/systemd/system/seuil90-live.service; do
 if [ -e "$destination" ]; then echo "Installation arrêtée : $destination existe déjà. Aucun écrasement automatique."; exit 1; fi
done
while IFS= read -r -d '' source_file; do php -l "$source_file"; done < <(find "$website_root/admin/slate/live90" "$website_root/api/live90" -name '*.php' -print0)
python3 -m unittest discover -s "$release_root/tests" -q
# Public files are deployed by Git; provision only the separate runtime.
runuser -u www-data -- php -r '$e=parse_ini_file("/etc/stratedge/live.env",false,INI_SCANNER_RAW); if(empty($e["SE_LIVE_TOKEN"]) || (isset($e["SE90_DB"]) && $e["SE90_DB"]!=="/var/lib/stratedge/live90.sqlite")) {fwrite(STDERR,"Configuration Live90 inattendue : installation arrêtée\n");exit(1);}'
install -d -m 0755 /opt/stratedge/live90
install -m 0644 "$release_root/server/engine.py" /opt/stratedge/live90/engine.py
if [ ! -d /var/lib/stratedge ]; then install -d -o www-data -g www-data -m 0750 /var/lib/stratedge; fi
runuser -u www-data -- test -r /etc/stratedge/live.env
runuser -u www-data -- php -r 'require $argv[1]; db90(); echo "Base Live90 initialisée\n";' "$website_root/api/live90/core.php"
install -m 0644 "$release_root/server/seuil90-live.service" /etc/systemd/system/seuil90-live.service
systemctl daemon-reload
printf '%s\n' 'Installation terminée. Aucun ancien service modifié.' 'Ouvrir /panel-x9k3m/slate/live90/ puis activer le nouveau service selon INSTALLATION.md.'
