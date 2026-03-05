# Restaure les images uploads/ depuis l'historique Git (commit avant la suppression)
# Usage: exécuter depuis la racine du repo (Site StratEdge)
# Les fichiers sont remis sur le disque mais pas committés (uploads/ reste dans .gitignore).

$ErrorActionPreference = "Stop"
$commit = "7bdd4de"  # commit qui contenait encore public_html/uploads/

Write-Host "Restauration de public_html/uploads/ depuis le commit $commit..."
git checkout $commit -- public_html/uploads/
git restore --staged public_html/uploads/
Write-Host "Terminé. Les fichiers sont dans public_html/uploads/ (non committés)."
Write-Host "Ensuite : 1) Envoyer uploads/ sur le serveur (FTP ou Panel > Restauration images)"
Write-Host "          2) Migration SQL (image_data, locked_image_data) si pas fait"
Write-Host "          3) Panel > Restauration images > Sauvegarder tout en BDD"
